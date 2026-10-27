<?php

namespace Database\Seeders;

use App\Modules\Platform\Models\Permission;
use Illuminate\Database\Seeder;

class PlatformPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Catalog only: no implied grants to ADMIN, FINANCE or STAFF.
        foreach (['view', 'propose', 'approve', 'cancel'] as $action) {
            Permission::firstOrCreate(['code' => 'ap.reconcile.'.$action], ['resource' => 'ap.reconcile', 'action' => $action, 'description' => 'AP reconciliation: '.$action]);
        }
        Permission::firstOrCreate(['code' => 'erp.panel.access'], ['resource' => 'erp.panel', 'action' => 'access', 'description' => 'ERP panel access']);
        foreach (['view', 'create', 'update'] as $action) {
            Permission::firstOrCreate(['code' => 'division.'.$action], ['resource' => 'division', 'action' => $action, 'description' => 'Divisi: '.$action]);
        }
        foreach (['view', 'create', 'update'] as $action) {
            Permission::firstOrCreate(['code' => 'party.'.$action], [
                'resource' => 'party', 'action' => $action,
                'description' => 'Master Party: '.$action,
            ]);
        }
    }
}
