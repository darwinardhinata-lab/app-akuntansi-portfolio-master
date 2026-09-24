<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Modules\Platform\Models\Permission;
use App\Modules\Platform\Models\Role;
use Database\Seeders\PlatformPermissionSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/** Explicit operator action; never runs automatically during seeding or login. */
class SetPartyAccess extends Command
{
    protected $signature = 'platform:party-access {user : ID user} {company : ID company} {--allow=* : view, create, update; omitted removes this dedicated grant}';
    protected $description = 'Set explicit Party permissions for one user/company without changing shared roles';

    public function handle(): int
    {
        $actions = array_values(array_unique($this->option('allow')));
        if (array_diff($actions, ['view', 'create', 'update'])) {
            $this->error('Permission hanya view, create, update.');
            return self::FAILURE;
        }
        $user = User::find($this->argument('user'));
        $company = $user?->companies()->where('companies.active', true)->whereKey($this->argument('company'))->first();
        if (! $company) {
            $this->error('User harus memiliki membership company aktif terlebih dahulu.');
            return self::FAILURE;
        }
        DB::transaction(function () use ($user, $company, $actions) {
            (new PlatformPermissionSeeder)->run();
            $role = Role::firstOrCreate(['code' => "PARTY_U{$user->id}_C{$company->id}"], [
                'name' => "Party access user {$user->id} / company {$company->id}",
                'description' => 'Dedicated grant; do not share this role with other users/companies.',
            ]);
            $role = Role::whereKey($role->id)->lockForUpdate()->firstOrFail();
            // Refuse to mutate a dedicated role if it was accidentally shared.
            $foreignAssignment = DB::table('user_role')->where('role_id', $role->id)
                ->where(fn ($q) => $q->where('user_id', '!=', $user->id)->orWhere('company_id', '!=', $company->id))->exists();
            if ($foreignAssignment) {
                throw new \RuntimeException('Role khusus ini digunakan scope lain. Perbaiki assignment sebelum melanjutkan.');
            }
            $ids = Permission::whereIn('code', array_map(fn ($a) => 'party.'.$a, $actions))->pluck('id')->all();
            $role->permissions()->sync($ids);
            $assignment = [
                'user_id' => $user->id, 'role_id' => $role->id, 'company_id' => $company->id,
            ];
            if (! DB::table('user_role')->where($assignment)->exists()) {
                DB::table('user_role')->insert($assignment + ['created_at' => now(), 'updated_at' => now()]);
            }
            \App\Models\SystemLog::create([
                'user_id' => null, 'action' => 'SET_ACCESS', 'module' => 'Platform Party',
                'description' => 'CLI operator; user_id='.$user->id.'; company_id='.$company->id.'; allow='.implode(',', $actions),
            ]);
        });
        $this->info('Grant khusus diperbarui. Hak dari role lain tetap berlaku; periksa semuanya saat mencabut akses.');

        return self::SUCCESS;
    }
}
