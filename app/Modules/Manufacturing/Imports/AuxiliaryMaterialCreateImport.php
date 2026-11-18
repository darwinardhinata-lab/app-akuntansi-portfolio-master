<?php

namespace App\Modules\Manufacturing\Imports;

use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AuxiliaryMaterialCreateImport
{
    public const UNIT = 'PCS';

    public static function exactMeters(string $value): bool
    {
        // Excess trailing zeroes are exactly representable. Never cast to float.
        return preg_match('/\A0*[0-9]{1,18}(?:\.[0-9]{1,2}0*|\.0+)?\z/', $value) === 1;
    }

    public function inspect(UploadedFile $file): array
    {
        $rows = app(AuxiliaryMaterialPreview::class)->inspectFile($file, createOnly: true);
        foreach ($rows as &$row) {
            if ($row['errors'] === [] && ! $row['existing_duplicate']) {
                // Let the actual unique column's collation compare source and stored codes.
                $row['existing_duplicate'] = AuxiliaryMaterial::where('material_code', $row['data']['material_code'])->exists();
            }
        }
        return $rows;
    }

    public static function ready(array $rows): bool
    {
        return $rows !== [] && ! array_filter($rows, self::blocked(...));
    }

    private static function blocked(array $row): bool
    {
        return $row['errors'] !== [] || $row['existing_duplicate'] || $row['file_duplicate'];
    }

    public function save(UploadedFile $file): int
    {
        return DB::transaction(function () use ($file) {
            $rows = $this->inspect($file);
            if ($rows === []) {
                throw ValidationException::withMessages(['save' => 'File tidak berisi data untuk disimpan.']);
            }
            foreach ($rows as $row) {
                if (self::blocked($row)) {
                    $reason = $row['errors'][0] ?? ($row['existing_duplicate'] ? 'Kode sudah ada di master.' : 'Kode berulang di file.');
                    throw ValidationException::withMessages(['save' => "Baris {$row['row']}: {$reason} Tidak ada baris disimpan; periksa file dan preview ulang."]);
                }
            }
            foreach ($rows as $row) {
                $data = array_map(fn (string $value) => $value === '' ? null : $value, $row['data']);
                if ($data['meters_per_roll'] !== null) {
                    [$whole, $fraction] = array_pad(explode('.', $data['meters_per_roll'], 2), 2, '');
                    $data['meters_per_roll'] = (ltrim($whole, '0') ?: '0').'.'.str_pad(substr($fraction, 0, 2), 2, '0');
                }
                try {
                    AuxiliaryMaterial::create($data + ['stock_quantity' => '0.00', 'average_cost' => '0.00',
                        'inventory_account_code' => '114004', 'is_active' => true]);
                } catch (UniqueConstraintViolationException $e) {
                    throw ValidationException::withMessages(['save' => "Baris {$row['row']}: kode sudah ada di master atau berulang di file. Seluruh batch dibatalkan; preview ulang."]);
                }
            }
            return count($rows);
        });
    }
}