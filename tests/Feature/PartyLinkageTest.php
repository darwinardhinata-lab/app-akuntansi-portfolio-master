<?php

namespace Tests\Feature;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Models\PartyRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyLinkageTest extends TestCase
{
    use RefreshDatabase;

    public function test_purchase_order_form_only_lists_active_supplier_parties(): void
    {
        $supplier = $this->partyWithRole('PT Supplier Aktif', 'SUPPLIER');
        $customer = $this->partyWithRole('PT Customer Tidak Ditampilkan', 'CUSTOMER');

        $this->withoutMiddleware()
            ->get(route('po.create'))
            ->assertOk()
            ->assertSee($supplier->legal_name)
            ->assertDontSee($customer->legal_name);
    }

    public function test_sales_order_form_only_lists_active_customer_parties(): void
    {
        $customer = $this->partyWithRole('PT Customer Aktif', 'CUSTOMER');
        $supplier = $this->partyWithRole('PT Supplier Tidak Ditampilkan', 'SUPPLIER');

        $this->withoutMiddleware()
            ->get(route('so.create'))
            ->assertOk()
            ->assertSee($customer->legal_name)
            ->assertDontSee($supplier->legal_name);
    }

    public function test_purchase_and_sales_orders_expose_party_relation(): void
    {
        $party = $this->partyWithRole('PT Party Relasi', 'CUSTOMER');

        $purchaseOrder = new PurchaseOrder(['party_id' => $party->id]);
        $salesOrder = new SalesOrder(['party_id' => $party->id]);

        $this->assertSame(Party::class, get_class($purchaseOrder->party()->getRelated()));
        $this->assertSame(Party::class, get_class($salesOrder->party()->getRelated()));
        $this->assertSame($party->id, $purchaseOrder->party_id);
        $this->assertSame($party->id, $salesOrder->party_id);
    }

    private function partyWithRole(string $legalName, string $role): Party
    {
        $company = Company::create([
            'code' => 'T' . str_pad((string) (Company::count() + 1), 3, '0', STR_PAD_LEFT),
            'name' => 'Test Company',
        ]);

        $party = Party::create([
            'company_id' => $company->id,
            'code' => 'P' . str_pad((string) (Party::count() + 1), 3, '0', STR_PAD_LEFT),
            'legal_name' => $legalName,
            'active' => true,
        ]);

        PartyRole::create(['party_id' => $party->id, 'role' => $role, 'active' => true]);

        return $party;
    }
}