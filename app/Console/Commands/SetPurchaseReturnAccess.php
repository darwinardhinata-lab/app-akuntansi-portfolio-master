<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Database\Seeders\PlatformPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Explicit operator assignment; never grants users implicitly on seeding or login. */
class SetPurchaseReturnAccess extends Command
{
    protected $signature = 'platform:purchase-return-access {company : Active MGI company ID} {--user= : User ID to assign or revoke} {--role= : Existing platform role code, or SUPERADMIN to create it} {--revoke : Remove assignment/grant}';
    protected $description = 'Assign purchase-return create access to a selected user or role in the operational company';

    public function handle(): int
    {
        $userId = $this->option('user');
        $roleCode = $this->option('role');
        if (($userId === null && $roleCode === null) || ($userId !== null && $roleCode !== null)
            || ($userId !== null && (! ctype_digit((string) $userId) || (int) $userId < 1))) {
            $this->error('Pilih tepat satu --user=<ID> atau --role=<kode>.');
            return self::FAILURE;
        }
        $company = Company::whereKey($this->argument('company'))->where('code', 'MGI')->where('active', true)->first();
        if (! $company || Company::count() !== 1) {
            $this->error('Hanya satu perusahaan operasional MGI aktif yang didukung.');
            return self::FAILURE;
        }
        $user = $userId === null ? null : User::whereKey($userId)->first();
        if ($userId !== null && (! $user || ! $user->companies()->whereKey($company->id)->exists())) {
            $this->error('User harus merupakan member perusahaan MGI sebelum grant.');
            return self::FAILURE;
        }
        if ($roleCode !== null && ! preg_match('/\A[A-Z][A-Z0-9_]{0,49}\z/', (string) $roleCode)) {
            $this->error('Kode role tidak valid.');
            return self::FAILURE;
        }
        if ($roleCode !== null && $roleCode !== 'SUPERADMIN' && ! Role::where('code', $roleCode)->exists()) {
            $this->error('Role harus sudah tersedia; hanya SUPERADMIN yang boleh dibuat di sini.');
            return self::FAILURE;
        }

        DB::transaction(function () use ($company, $user, $roleCode) {
            (new PlatformPermissionSeeder)->run();
            $permission = Permission::where('code', 'purchase_return.create')->sole();
            $code = $user ? 'PURCHASE_RETURN_U'.$user->id.'_C'.$company->id : $roleCode;
            $role = Role::firstOrCreate(['code' => $code], [
                'name' => $user ? 'Purchase return access for user '.$user->id.' / MGI' : $code,
                'description' => $user ? 'Dedicated user/company grant; do not share.' : 'Explicit platform role; no automatic users.',
            ]);
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            if ($user) {
                $foreign = DB::table('user_role')->where('role_id', $role->id)
                    ->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhere('company_id', '!=', $company->id))->exists();
                if ($foreign) throw new \RuntimeException('Dedicated role shared with another user/company.');
                $assignment = ['user_id' => $user->id, 'role_id' => $role->id, 'company_id' => $company->id];
                if ($this->option('revoke')) {
                    DB::table('user_role')->where($assignment)->delete();
                } elseif (! DB::table('user_role')->where($assignment)->exists()) {
                    DB::table('user_role')->insert($assignment + ['created_at' => now(), 'updated_at' => now()]);
                }
            } else {
                // A role's permission is global; its user_role assignments remain company-scoped.
                if ($this->option('revoke')) $role->permissions()->detach($permission->id);
                else $role->permissions()->syncWithoutDetaching([$permission->id]);
            }
            if ($user && ! $this->option('revoke')) $role->permissions()->syncWithoutDetaching([$permission->id]);
            \App\Models\SystemLog::create(['user_id' => null, 'action' => 'SET_ACCESS', 'module' => 'Purchase Return',
                'description' => 'CLI operator; company_id='.$company->id.'; '.($user ? 'user_id='.$user->id : 'role='.$roleCode)
                    .'; revoke='.($this->option('revoke') ? 'yes' : 'no')]);
        });
        $this->info('Permission diperbarui; assignment pada role lain tetap berlaku. Tinjau seluruh grant saat mencabut akses.');
        return self::SUCCESS;
    }
}