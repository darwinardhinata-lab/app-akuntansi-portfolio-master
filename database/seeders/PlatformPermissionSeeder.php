<?php

namespace Database\Seeders;

use App\Modules\Platform\Models\Permission;
use Illuminate\Database\Seeder;

class PlatformPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Catalog only: no implied grants to ADMIN, FINANCE or STAFF.
        foreach (['view', 'create', 'update'] as $action) {
            Permission::firstOrCreate(['code' => 'party.'.$action], [
                'resource' => 'party', 'action' => $action,
                'description' => 'Master Party: '.$action,
            ]);
        }
    }
}
