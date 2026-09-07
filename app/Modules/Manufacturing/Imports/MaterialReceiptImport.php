<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Services\MaterialReceiptService;
use App\Support\NumberParser;
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
 *        TIPE ITEM (YARN/FABRIC), KODE ITEM, NAMA ITEM, QTY, SATUAN, RATE, LOT
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

        foreach ($rows as $row) {
            $ref = trim((string) ($row[0] ?? ''));
            if (empty($ref) || strtoupper($ref) === 'REF SEMENTARA') {
                continue;
            }
            $groups[$ref][] = $row;
        }

        foreach ($groups as $ref => $groupRows) {
            try {
                $first = $groupRows[0];
                $supplier = Supplier::where('supplier_code', trim($first[2] ?? ''))->first();
                if (!$supplier) {
                    $this->errors[] = "Ref {$ref}: supplier kode '{$first[2]}' tidak ditemukan.";
                    continue;
                }

                $items = [];
                foreach ($groupRows as $row) {
                    $itemType = strtoupper(trim($row[5] ?? ''));
                    $itemCode = trim($row[6] ?? '');
                    $item = null;
                    if ($itemType === 'YARN') {
                        $item = Yarn::where('yarn_code', $itemCode)->first();
                    } elseif ($itemType === 'FABRIC') {
                        $item = Fabric::where('fabric_code', $itemCode)->first();
                    }
                    if (!$item) {
                        $this->errors[] = "Ref {$ref}: item '{$itemCode}' ({$itemType}) tidak ditemukan, baris dilewati.";
                        continue;
                    }

                    $items[] = [
                        'item_type'  => $itemType,
                        'yarn_id'    => $itemType === 'YARN' ? $item->id : null,
                        'fabric_id'  => $itemType === 'FABRIC' ? $item->id : null,
                        'item_name'  => trim($row[7] ?? $itemCode),
                        'qty'        => NumberParser::parseDecimal($row[8] ?? '0'),
                        'unit'       => trim($row[9] ?? 'KGS'),
                        'rate'       => NumberParser::parseDecimal($row[10] ?? '0'),
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
                    'supplier_doc_no' => trim($first[3] ?? '') ?: null,
                    'tax_amount'      => NumberParser::parseDecimal($first[4] ?? '0'),
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

    public function getSuccessCount(): int
    {
        return $this->successCount;
    }
}
