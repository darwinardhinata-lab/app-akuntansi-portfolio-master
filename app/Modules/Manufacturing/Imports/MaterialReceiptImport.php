<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Modules\Manufacturing\Models\MaterialPurchaseOrder;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;

/**
 * Import MRN massal dari Excel/CSV.
 *
 * BEDA dengan import master data (Yarn/Fabric/dst.): setiap baris di sini
 * adalah TRANSAKSI yang harus tetap memicu jurnal (Jurnal #1) dan kartu stok
 * yang benar — jadi import ini TIDAK melakukan insert langsung ke tabel,
 * melainkan MENGELOMPOKKAN baris per nomor referensi lalu memanggil
 * `MaterialReceiptService::createAndPost()` yang SAMA PERSIS dipakai form
 * input manual. Ini memastikan MRN hasil import punya jurnal & validasi yang
 * identik dengan MRN hasil input manual — tidak ada jalur pintas.
 *
 * Kolom: REF SEMENTARA, TANGGAL, KODE SUPPLIER, NO DOKUMEN SUPPLIER, PAJAK,
 *        TIPE ITEM (YARN/FABRIC/AUXILIARY), KODE ITEM, NAMA ITEM, QTY, SATUAN, RATE, LOT,
 *        NO MATERIAL PO (optional), PO DETAIL ID (optional), TANGGAL DOKUMEN SUPPLIER (optional)
 *
 * "REF SEMENTARA" mengelompokkan baris-baris yang merupakan 1 MRN yang sama
 * (1 MRN bisa berisi banyak item/baris) — SATU nomor MRN resmi akan
 * digenerate otomatis oleh Service, REF SEMENTARA cuma penanda pengelompokan
 * di file Excel, bukan nomor final.
 */
class MaterialReceiptImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    private array $errors = [];
    private int $successCount = 0;

    public function getCsvSettings(): array
    {
        return ['delimiter' => ';'];
    }

    public function startRow(): int
    {
        return 7;
    }

    public function collection(Collection $rows)
    {
        $service = app(MaterialReceiptService::class);
        $groups = [];

        foreach ($rows->values() as $index => $row) {
            $ref = trim((string) ($row[0] ?? ''));
            if (empty($ref) || strtoupper($ref) === 'REF SEMENTARA') {
                continue;
            }
            $groups[$ref][] = ['data' => $row, 'line' => $index + $this->startRow()];
        }

        foreach ($groups as $ref => $groupRows) {
            try {
                $first = $groupRows[0]['data'];
                $documentDate = trim((string) ($first[14] ?? ''));
                if ($documentDate !== '') {
                    $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $documentDate);
                    if (!$parsed || $parsed->format('Y-m-d') !== $documentDate) {
                        throw new \RuntimeException('Tanggal dokumen supplier harus YYYY-MM-DD.');
                    }
                }
                $supplier = Supplier::where('supplier_code', trim($first[2] ?? ''))->first();
                if (!$supplier) {
                    throw new \RuntimeException('Supplier tidak ditemukan pada baris '.$groupRows[0]['line']);
                }
                $poNumber = trim((string) ($first[12] ?? ''));
                $po = $poNumber === '' ? null : MaterialPurchaseOrder::where('po_number', $poNumber)->first();
                if ($poNumber !== '' && !$po) throw new \RuntimeException('Material PO tidak ditemukan.');

                $items = [];
                foreach ($groupRows as $entry) {
                    $row = $entry['data'];
                    $line = $entry['line'];
                    foreach ([1, 2, 3, 4, 12, 14] as $column) {
                        if (trim((string) ($row[$column] ?? '')) !== trim((string) ($first[$column] ?? ''))) {
                            throw new \RuntimeException("Baris {$line}: header dalam grup tidak konsisten.");
                        }
                    }
                    $itemType = strtoupper(trim($row[5] ?? ''));
                    $itemCode = trim($row[6] ?? '');
                    $item = null;
                    if ($itemType === 'YARN') {
                        $item = Yarn::where('yarn_code', $itemCode)->first();
                    } elseif ($itemType === 'FABRIC') {
                        $item = Fabric::where('fabric_code', $itemCode)->first();
                    } elseif ($itemType === 'AUXILIARY') {
                        $item = AuxiliaryMaterial::where('material_code', $itemCode)->first();
                    }
                    if (!$item) {
                        throw new \RuntimeException("Baris {$line}: item '{$itemCode}' ({$itemType}) tidak ditemukan; seluruh grup ditolak.");
                    }
                    $detailId = trim((string) ($row[13] ?? ''));
                    if ($detailId !== '' && (!ctype_digit($detailId) || (int) $detailId <= 0)) {
                        throw new \RuntimeException("Baris {$line}: po_detail_id tidak valid.");
                    }
                    $date = trim((string) ($first[1] ?? ''));
                    $parsedDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                    if (!$parsedDate || $parsedDate->format('Y-m-d') !== $date) {
                        throw new \RuntimeException("Baris {$line}: tanggal harus YYYY-MM-DD.");
                    }

                    $items[] = [
                        'item_type'  => $itemType,
                        'yarn_id'    => $itemType === 'YARN' ? $item->id : null,
                        'fabric_id'  => $itemType === 'FABRIC' ? $item->id : null,
                        'auxiliary_material_id' => $itemType === 'AUXILIARY' ? $item->id : null,
                        'po_detail_id' => $detailId === '' ? null : (int) $detailId,
                        'item_name'  => trim($row[7] ?? $itemCode),
                        'qty'        => $this->decimal($row[8] ?? '', false, $line),
                        'unit'       => trim($row[9] ?? 'KGS'),
                        'rate'       => $this->decimal($row[10] ?? '', true, $line),
                        'lot_number' => trim($row[11] ?? '') ?: null,
                    ];
                }

                if (empty($items)) {
                    $this->errors[] = "Ref {$ref}: tidak ada item valid, MRN dilewati.";
                    continue;
                }

                $service->createAndPost([
                    'receipt_date'    => $first[1] ?? now()->format('Y-m-d'),
                    'supplier_id'     => $supplier->id,
                    'po_id' => $po?->id,
                    'supplier_doc_date' => trim((string) ($first[14] ?? '')) ?: null,
                    'created_by' => auth()->id(),
                    'supplier_doc_no' => trim($first[3] ?? '') ?: null,
                    'tax_amount'      => $this->decimal($first[4] ?? '0', true, $groupRows[0]['line']),
                    'remarks'         => "Import massal dari Excel (ref file: {$ref})",
                ], $items);

                $this->successCount++;
            } catch (\Exception $e) {
                $this->errors[] = "Ref {$ref}: GAGAL - " . $e->getMessage();
            }
        }
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    private function decimal(mixed $value, bool $allowZero, int $line): string
    {
        $value = trim((string) $value);
        if (!preg_match('/^\d+(?:[.,]\d{1,2})?$/D', $value)) {
            throw new \RuntimeException("Baris {$line}: angka harus tanpa pemisah ribuan, maksimal 2 desimal.");
        }
        $value = str_replace(',', '.', $value);
        if (strlen(ltrim(explode('.', $value)[0], '0')) > 18) {
            throw new \RuntimeException("Baris {$line}: angka melebihi kapasitas database.");
        }
        if (!$allowZero && bccomp($value, '0', 2) <= 0) {
            throw new \RuntimeException("Baris {$line}: qty wajib lebih dari nol.");
        }
        return $value;
    }

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}
