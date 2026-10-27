<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class LegacySyncUiRetiredTest extends TestCase
{
    public function test_old_company_sync_routes_are_not_registered_or_dispatchable(): void
    {
        Bus::fake();
        // Retirement must hold even if a stale environment turns the legacy flag on.
        config(['platform.legacy_sync_enabled' => true]);
        $this->withoutMiddleware();
        foreach ([
            'jurnal.sync_temp' => '/jurnal/sync-temp',
            'po.sync_temp' => '/purchase-order/sync-temp',
            'so.sync_temp' => '/sales-order/sync-temp',
            'invoice.sync_temp' => '/sales-invoices/sync-temp',
            'purchase-bills.sync_temp' => '/purchase-bills/sync-temp',
            'product.sync_dashboard' => '/master-barang/sync-dashboard',
        ] as $name => $path) {
            $this->assertFalse(Route::has($name), $name);
            // Existing GET /{id} readers may match this text, yielding 405 rather than 404.
            $this->assertContains($this->postJson($path)->status(), [404, 405], $path);
        }
        Bus::assertNothingDispatched();
    }

    public function test_views_no_longer_contain_legacy_sync_controls_or_handlers(): void
    {
        foreach (['journal', 'sales_order', 'sales_invoice', 'purchase_order', 'purchase_bill', 'product'] as $module) {
            $source = file_get_contents(resource_path('views/'.$module.'/index.blade.php'));
            foreach (['btnSyncTemp', 'btnSyncSOTemp', 'sync_temp', 'sync_dashboard', 'Sync ke Temp', 'Sync Dashboard'] as $obsolete) {
                $this->assertStringNotContainsString($obsolete, $source, $module);
            }
            // Compile changed templates to catch dangling Blade/route syntax.
            $this->assertNotEmpty(app('blade.compiler')->compileString($source));
        }
    }
}