<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class PurchaseOrderInboundRouteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbound_requires_authentication(): void
    {
        $this->get(route('inbound.index'))->assertRedirect(route('login'));
    }

    public function test_inbound_renders_active_receiving_view_and_keeps_status_scope_during_search(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);
        foreach (['APPROVED', 'PARTIAL', 'RECEIVED', 'DRAFT', 'CANCELLED'] as $status) {
            PurchaseOrder::create([
                'po_number' => 'PO-INBOUND-'.$status, 'transaction_date' => '2026-10-07',
                'contact_name' => 'Supplier Matching', 'status' => $status,
            ]);
        }
        foreach (['id', 'en', 'zh_CN'] as $locale) {
            app()->setLocale($locale);
            $this->get(route('inbound.index', ['search' => 'Supplier Matching']))
                ->assertOk()->assertViewIs('purchase_order.index')
                ->assertViewHas('uangMukaSet')
                ->assertViewHas('orders', fn ($orders) => $orders->count() === 3)
                ->assertSee('PO-INBOUND-APPROVED')->assertSee('PO-INBOUND-PARTIAL')
                ->assertSee('PO-INBOUND-RECEIVED')->assertDontSee('PO-INBOUND-DRAFT')
                ->assertDontSee('PO-INBOUND-CANCELLED');
        }
    }

    public function test_inbound_filter_cannot_select_draft_but_regular_index_can(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);
        PurchaseOrder::create([
            'po_number' => 'PO-DRAFT-ONLY', 'transaction_date' => '2026-10-07',
            'contact_name' => 'Supplier', 'status' => 'DRAFT',
        ]);
        $this->get(route('inbound.index', ['status' => 'DRAFT']))->assertOk()
            ->assertViewHas('orders', fn ($orders) => $orders->isEmpty());
        $this->get(route('po.index', ['status' => 'DRAFT']))->assertOk()->assertSee('PO-DRAFT-ONLY');
    }

    public function test_inbound_combines_status_and_date_filters(): void
    {
        $this->withoutMiddleware();
        View::share('errors', new ViewErrorBag);
        foreach ([['MATCH', 'PARTIAL', '2026-10-07'], ['OLD', 'PARTIAL', '2026-09-01'], ['OTHER', 'APPROVED', '2026-10-07']] as [$number, $status, $date]) {
            PurchaseOrder::create([
                'po_number' => 'PO-FILTER-'.$number, 'transaction_date' => $date,
                'contact_name' => 'Supplier', 'status' => $status,
            ]);
        }
        $this->get(route('inbound.index', [
            'status' => 'PARTIAL', 'start_date' => '2026-10-01', 'end_date' => '2026-10-31',
        ]))->assertOk()->assertViewHas('orders', fn ($orders) => $orders->count() === 1)
            ->assertSee('PO-FILTER-MATCH')->assertDontSee('PO-FILTER-OLD')->assertDontSee('PO-FILTER-OTHER');
    }
}