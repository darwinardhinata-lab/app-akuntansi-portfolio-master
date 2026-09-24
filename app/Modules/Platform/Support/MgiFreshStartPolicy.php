<?php

namespace App\Modules\Platform\Support;

use InvalidArgumentException;

/** The old database is never cleared; only these records enter the new database. */
class MgiFreshStartPolicy
{
    public const NAME = 'PT. Magicase Group Indonesia';
    public const CODE = 'MGI';
    public const EXACT_COPY = [
        'accounts', 'account_translations', 'coa_type_translations', 'users',
        'roles', 'permissions', 'role_permission', 'user_role', 'user_company', 'migrations',
    ];
    public const ACCOUNT_COLUMNS = [
        'account_code', 'account_name', 'coa_type', 'normal_balance', 'report_pos',
        'coa_id', 'account_id', 'created_at', 'updated_at',
    ];

    public static function validateTarget(string $source, string $target): void
    {
        if (! preg_match('/\Amgi_fresh_[a-z0-9_]{1,45}\z/D', $target) || strcasecmp($source, $target) === 0) {
            throw new InvalidArgumentException('Target harus database BARU dengan nama mgi_fresh_..., berbeda dari sumber.');
        }
    }

    public static function mode(string $table): string
    {
        if (in_array($table, self::EXACT_COPY, true)) {
            return 'COPY_EXACT';
        }
        return match ($table) {
            'companies', 'company_profiles' => 'NEW_IDENTITY',
            'master_divisi' => 'USER_REFERENCES_ONLY',
            default => 'EMPTY',
        };
    }

    public static function profile(array $row): array
    {
        $allowed = ['id', 'company_name', 'npwp', 'website', 'address', 'province', 'city',
            'country', 'postal_code', 'phone', 'email', 'logo', 'employee_pin', 'created_at', 'updated_at'];
        if (array_diff(array_keys($row), $allowed)) {
            throw new InvalidArgumentException('Kolom profil tambahan ditemukan; review sebelum fresh start.');
        }
        foreach ($row as $key => $value) {
            if (! in_array($key, ['id', 'company_name', 'created_at', 'updated_at'], true)) {
                $row[$key] = null;
            }
        }
        $row['company_name'] = self::NAME;

        return $row;
    }

    public static function company(array $row): array
    {
        $allowed = ['id', 'code', 'name', 'base_currency', 'timezone', 'legacy_company_profile_id', 'active', 'created_at', 'updated_at'];
        if (array_diff(array_keys($row), $allowed)) {
            throw new InvalidArgumentException('Kolom company tambahan ditemukan; review sebelum fresh start.');
        }
        $row['code'] = self::CODE;
        $row['name'] = self::NAME;
        $row['base_currency'] = 'IDR';
        $row['timezone'] = 'Asia/Jakarta';
        $row['active'] = 1;

        return $row;
    }
}
