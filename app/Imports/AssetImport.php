<?php

namespace App\Imports;

use App\Models\Asset;
use App\Models\JournalDetail;
use App\Models\SystemLog;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithStartRow;
use Maatwebsite\Excel\Concerns\WithCustomCsvSettings;
use App\Support\NumberParser;

class AssetImport implements ToCollection, WithStartRow, WithCustomCsvSettings
{
    private $delimiter = ',';
    private $importedCount = 0;
    private $skippedCount = 0;
    private $errors = [];

    public function __construct($filePath = null)
    {
        if ($filePath && file_exists($filePath)) {
            $handle = fopen($filePath, 'r');
            if ($handle) {
                $firstLine = fgets($handle);
                fclose($handle);
                if ($firstLine && str_contains($firstLine, ';')) {
                    $this->delimiter = ';';
                }
            }
        }
    }

    public function startRow(): int
    {
        return 2;
    }

    public function getCsvSettings(): array
    {
        return [
            'delimiter' => $this->delimiter
        ];
    }

    public function collection(Collection $rows)
    {
        DB::beginTransaction();

        try {
            foreach ($rows as $rowIndex => $row) {
                $r = is_object($row) ? $row->toArray() : (is_array($row) ? $row : []);

                // Debug: Log raw row data (aktifkan untuk debugging)
                Log::debug('AssetImport Row ' . ($rowIndex + 2) . ': ' . json_encode($r));

                // Expected columns:
                // 0=No, 1=Kode Aset, 2=Nama Aset, 3=Kategori, 4=Qty, 5=Tgl Pemakaian,
                // 6=Nilai Perolehan, 7=Akumulasi Penyusutan, 8=Nilai Buku, 9=Nilai Sisa, 10=Status
                $assetCode = $this->getCellValue($r, 1);
                $assetName = $this->getCellValue($r, 2);
                $category = $this->getCellValue($r, 3);
                $quantity = (int) $this->getCellValue($r, 4, '1');
                $purchaseDate = $this->getCellValue($r, 5);
                $purchasePrice = NumberParser::parseDecimal($this->getCellValue($r, 6));
                $accumulation = (int) $this->getCellValue($r, 7);
                $residualValue = NumberParser::parseDecimal($this->getCellValue($r, 9));
                $status = $this->getCellValue($r, 10);

                // Skip empty rows
                if (empty($assetCode) && empty($assetName)) {
                    $this->skippedCount++;
                    continue;
                }

                if (empty($assetCode) || empty($assetName) || empty($purchaseDate) || $purchasePrice <= 0) {
                    $this->errors[] = "Baris data tidak lengkap: Kode=$assetCode, Nama=$assetName";
                    $this->skippedCount++;
                    continue;
                }

                // Validate purchase date
                $dateTimestamp = strtotime(str_replace('/', '-', $purchaseDate));
                if (!$dateTimestamp) {
                    $this->errors[] = "Format tanggal tidak valid untuk aset: $assetCode ($purchaseDate)";
                    $this->skippedCount++;
                    continue;
                }
                $formattedDate = date('Y-m-d', $dateTimestamp);

                $isActive = strtolower($status) === 'aktif' ? 1 : 0;

                // Use updateOrCreate to handle duplicates gracefully
                $assetData = [
                    'asset_code'         => $assetCode,
                    'asset_name'         => $assetName,
                    'category'           => $category ?: null,
                    'quantity'           => max(1, $quantity),
                    'purchase_date'      => $formattedDate,
                    'purchase_price'     => $purchasePrice,
                    'residual_value'     => $residualValue,
                    'useful_life_months' => $accumulation,
                    'is_active'          => $isActive,
                ];

                // Check if asset_code already exists
                $existingAsset = Asset::where('asset_code', $assetCode)->first();
                if ($existingAsset) {
                    // Update existing
                    $existingAsset->update($assetData);
                } else {
                    // Create new
                    Asset::create($assetData);
                }

                $this->importedCount++;
            }

            DB::commit();

            SystemLog::record('IMPORT', 'Aset Tetap', "Import aset berhasil. {$this->importedCount} aset terekam, {$this->skippedCount} baris dilewati.");

        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get cell value with proper handling for different cell types.
     */
    private function getCellValue(array $row, int $index, string $default = ''): string
    {
        $value = $row[$index] ?? $default;
        
        // Handle different cell types
        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }
        
        if (is_numeric($value)) {
            return (string) $value;
        }
        
        return trim((string) $value);
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}