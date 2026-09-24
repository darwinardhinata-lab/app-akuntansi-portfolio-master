<?php

namespace Database\Seeders;

use App\Models\CompanyProfile;
use App\Models\MasterDivisi;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\OrgUnit;
use App\Modules\Platform\Models\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Tahap 1 Fondasi - Bagian 7 desain penggabungan ERP Marvel + Akuntansi.
 *
 * Membuat data awal skema Platform baru dari data lama yang sudah ada,
 * TANPA mengubah/menghapus data lama:
 *  - company_profiles (BBW)      -> satu baris companies
 *  - master_divisi                -> org_units tipe DEPARTMENT (PLACEHOLDER,
 *    lihat catatan AS05 di bawah - bukan keputusan final)
 *  - users.role (ADMIN/FINANCE/STAFF) -> roles + user_role
 *
 * Idempoten: aman dijalankan berkali-kali (status guard di setiap blok).
 * Dibungkus DB::transaction agar tidak menyisakan data setengah jadi.
 */
class PlatformFoundationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $company = $this->seedDefaultCompany();
            $roles = $this->seedLegacyRoles();
            $this->seedOrgUnitsFromDivisi($company);
            $this->backfillUserCompanyAndRole($company, $roles);
        });
    }

    private function seedDefaultCompany(): Company
    {
        // Status guard: jangan buat company kedua kalau sudah pernah di-seed.
        $existing = Company::where('code', 'BBW')->first();
        if ($existing) {
            return $existing;
        }

        $profile = CompanyProfile::first();

        return Company::create([
            'code' => 'BBW',
            'name' => $profile?->company_name ?? 'CV. Berkarya Bersama Warna',
            'base_currency' => 'IDR',
            'timezone' => 'Asia/Jakarta',
            'legacy_company_profile_id' => $profile?->id,
            'active' => true,
        ]);
    }

    /**
     * @return array<string, Role>
     */
    private function seedLegacyRoles(): array
    {
        $definitions = [
            'ADMIN' => 'Administrator',
            'FINANCE' => 'Finance',
            'STAFF' => 'Staff',
        ];

        $roles = [];
        foreach ($definitions as $code => $name) {
            $roles[$code] = Role::firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'description' => "Dimigrasikan dari users.role='{$code}' (skema lama)."]
            );
        }

        return $roles;
    }

    private function seedOrgUnitsFromDivisi(Company $company): void
    {
        // AS05 (Bagian 21 desain): "mapping divisi lama, urutan role, delegasi
        // dan batas nominal" belum dikonfirmasi HR/management. org_units di
        // bawah ini adalah PLACEHOLDER 1:1 dari master_divisi, tipe
        // DEPARTMENT untuk semua - BUKAN keputusan struktur organisasi final.
        // Jangan pakai org_units ini untuk approval/cost-center enforcement
        // sebelum AS05 dikonfirmasi.
        MasterDivisi::all()->each(function (MasterDivisi $divisi) use ($company) {
            OrgUnit::firstOrCreate(
                ['company_id' => $company->id, 'code' => $divisi->kode_divisi],
                [
                    'parent_id' => null,
                    'type' => 'DEPARTMENT',
                    'name' => $divisi->nama_divisi,
                    'active' => (bool) $divisi->status_aktif,
                ]
            );
        });
    }

    /**
     * @param  array<string, Role>  $roles
     */
    private function backfillUserCompanyAndRole(Company $company, array $roles): void
    {
        User::all()->each(function (User $user) use ($company, $roles) {
            // user_company: status guard via attachIfMissing manual (unique constraint juga menjaga)
            if (! $company->users()->where('users.id', $user->id)->exists()) {
                $company->users()->attach($user->id, ['is_default' => true]);
            }

            $legacyRoleCode = $user->role ?? 'STAFF';
            $role = $roles[$legacyRoleCode] ?? null;

            if (! $role) {
                // Nilai role lama di luar ADMIN/FINANCE/STAFF - jangan tebak,
                // catat saja supaya pemilik data yang memutuskan (Bagian 6).
                Log::warning("PlatformFoundationSeeder: user #{$user->id} punya role lama '{$legacyRoleCode}' yang tidak dikenal, dilewati.");

                return;
            }

            $alreadyLinked = DB::table('user_role')
                ->where('user_id', $user->id)
                ->where('role_id', $role->id)
                ->where('company_id', $company->id)
                ->exists();

            if (! $alreadyLinked) {
                DB::table('user_role')->insert([
                    'user_id' => $user->id,
                    'role_id' => $role->id,
                    'company_id' => $company->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });
    }
}
