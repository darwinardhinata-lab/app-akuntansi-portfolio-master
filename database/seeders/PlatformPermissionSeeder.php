<?php

namespace Database\Seeders;

use App\Modules\Platform\Models\Permission;
use Illuminate\Database\Seeder;

class PlatformPermissionSeeder extends Seeder
{
    public function run(): void
    {
        // Catalog only: no implied grants to ADMIN, FINANCE or STAFF.
        Permission::firstOrCreate(['code' => 'purchase_bill.view'], [
            'resource' => 'purchase_bill', 'action' => 'view', 'description' => 'Read purchase bills with proven purchase-order ownership',
        ]);
        Permission::firstOrCreate(['code' => 'gl.view'], ['resource' => 'gl', 'action' => 'view', 'description' => 'Read-only single-MGI general ledger trace']);
        foreach (['view', 'propose', 'approve', 'cancel', 'audit'] as $action) {
            Permission::firstOrCreate(['code' => 'ap.reconcile.'.$action], ['resource' => 'ap.reconcile', 'action' => $action, 'description' => 'AP reconciliation: '.$action]);
        }
        Permission::firstOrCreate(['code' => 'erp.panel.access'], ['resource' => 'erp.panel', 'action' => 'access', 'description' => 'ERP panel access']);
        Permission::firstOrCreate(['code' => 'material.view'], ['resource' => 'material', 'action' => 'view', 'description' => 'Read-only material master lookup']);
        Permission::firstOrCreate(['code' => 'material.create'], ['resource' => 'material', 'action' => 'create', 'description' => 'Create material masters; does not enable unapproved import mappings']);
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
