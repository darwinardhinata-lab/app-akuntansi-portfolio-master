<?php

namespace Tests\Feature\ApAllocation;

use App\Models\PurchaseBill;
use App\Models\User;
use App\Modules\PaymentPlan\Domain\RuleViolation;
use App\Modules\PaymentPlan\Services\ApAllocationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApAllocationServiceTest extends TestCase
{
    use RefreshDatabase;

    private function seedData(): void
    {
        $this->seed(\Database\Seeders\PlatformPermissionSeeder::class);
        $this->seed(\Database\Seeders\CompanySeeder::class);
        $this->seed(\Database\Seeders\UserSeeder::class);
    }

    public function test_validate_three_way_match_success(): void
    {
        $this->seedData();

        $user = User::factory()->create();
        $company = $user->company()->first();
        $bill = PurchaseBill::factory()->create([
            'company_id' => $company->id,
            'journal_id' => 'J-DUMMY',
            'credit_account' => '211001',
            'grand_total' => '200.00',
            'outstanding_amount' => '200.00',
            'payment_status' => 'UNPAID',
        ]);

        $service = new ApAllocationService();

        $result = $service->validateThreeWayMatch($bill->id, $company->id);

        $this->assertTrue($result['matched']);
        $this->assertEquals('matched', $result['match_status']);
        $this->assertEmpty($result['exceptions']);
    }

    public function test_allocate_payment_success(): void
    {
        $this->seedData();

        $user = User::factory()->create(['role' => 'accountant']);
        $company = $user->company()->first();

        $bill = PurchaseBill::factory()->create([
            'company_id' => $company->id,
            'journal_id' => null,
            'payment_status' => 'UNPAID',
            'grand_total' => '100.00',
            'allocated_amount' => '0.00',
        ]);

        $service = new ApAllocationService();

        $input = [
            'company_id' => $company->id,
            'actor_user_id' => $user->getKey(),
            'mutation_id' => 'test-alloc:' . uniqid(),
            'allocations' => [
                [
                    'invoice_id' => $bill->id,
                    'amount' => '50.00',
                ],
            ],
        ];

        $result = $service->allocatePayment($input);

        $this->assertArrayHasKey('allocations', $result);
        $this->assertCount(1, $result['allocations']);
        $this->assertEquals('50.00', $result['total_allocated']);

        $this->assertEquals('50.00', $bill->fresh()->allocated_amount);
        $this->assertEquals('50.00', $bill->fresh()->outstanding_amount);
    }

    public function test_reverse_allocation_success(): void
    {
        $this->seedData();

        $user = User::factory()->create(['role' => 'finance_manager']);
        $company = $user->company()->first();

        $allocation = DB::table('ppl_ap_payment_allocations')->insert([
            'company_id' => $company->id,
            'invoice_id' => 1,
            'allocated_amount' => '50.00',
            'currency' => 'IDR',
            'allocated_at' => now(),
            'journal_id' => null,
            'idempotency_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ApAllocationService();

        $service->reverseAllocation(1, $company->id, $user, 'test reason');

        $this->assertEquals('0.00', DB::table('ppl_ap_payment_allocations')->where('id', 1)->value('allocated_amount'));
    }

    public function test_post_allocation_journal_success(): void
    {
        $this->seedData();

        $user = User::factory()->create(['role' => 'accountant']);
        $company = $user->company()->first();

        // Mock PostingService to avoid actual journal creation
        DB::shouldReceive('post')->once()->andReturn((object)[
            'journal_id' => 'J-12345',
            'sealed_at' => now(),
            'updated_at' => now(),
        ]);

        $allocation = DB::table('ppl_ap_payment_allocations')->insert([
            'company_id' => $company->id,
            'invoice_id' => 1,
            'allocated_amount' => '50.00',
            'currency' => 'IDR',
            'allocated_at' => now(),
            'journal_id' => null,
            'idempotency_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ApAllocationService();

        $journalId = $service->postAllocationJournal(1, $company->id, $user);

        $this->assertEquals('J-12345', $journalId);
        $this->assertTrue((bool) DB::table('ppl_ap_payment_allocations')
            ->where('id', 1)
            ->where('journal_id', 'J-12345')
            ->exists());
    }
public function test_waive_match_success(): void
    {
        $this->seedData();

        $user = User::factory()->create(['role' => 'finance_manager']);
        $company = $user->company()->first();

        $bill = PurchaseBill::factory()->create([
            'company_id' => $company->id,
            'journal_id' => null,
            'match_status' => 'exception',
            'match_tolerance_qty' => '0.00',
            'match_tolerance_price' => '0.00',
        ]);

        $service = new ApAllocationService();

        $service->waiveMatch($bill->id, $company->id, $user, 'test reason');

        $this->assertEquals('waived', $bill->fresh()->match_status);
        $this->assertEquals($user->getKey(), $bill->fresh()->matched_by);
        $this->assertEquals(now()->toDateString(), $bill->fresh()->matched_at);
    }

    public function test_validate_three_way_match_exception(): void
    {
        $this->seedData();

        $user = User::factory()->create(['role' => 'finance_manager']);
        $company = $user->company()->first();

        $allocation = DB::table('ppl_ap_payment_allocations')->insert([
            'company_id' => $company->id,
            'invoice_id' => 1,
            'allocated_amount' => '50.00',
            'currency' => 'IDR',
            'allocated_at' => now(),
            'journal_id' => null,
            'idempotency_key' => 'test-key',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $service = new ApAllocationService();

        $service->reverseAllocation(1, $company->id, $user, 'test reason');

        $this->assertEquals('0.00', DB::table('ppl_ap_payment_allocations')->where('id', 1)->value('allocated_amount'));
        $this->assertTrue(DB::table('ppl_ap_payment_allocations')->where('id', 1)->where('updated_at', '>=', $allocation['updated_at'])->exists());
    }

    public function test_post_allocation_journal_success(): void
    {
        $this->seedData();

        $user = User::factory()->create(['role' => 'accountant']);
        $company = $user->company()->first();

        // Mock PostingService to avoid actual journal creation
        DB::shouldReceive('post')->once()->andReturn((object)[
            'journal_id' => 'J-12345',
            'sealed_at' => now(),
            'updated_at' => now(),
        ]);

        $allocation = PurchaseBillAllocation::factory()->create([
            'company_id' => $company->id,
            'invoice_id' => 1,
            'allocated_amount' => '50.00',
            'journal_id' => null,
        ]);

        $service = new ApAllocationService();

        $journalId = $service->postAllocationJournal($allocation->id, $company->id, $user);

        $this->assertEquals('J-12345', $journalId);
        $this->assertTrue((bool) DB::table('ppl_ap_payment_allocations')
            ->where('id', $allocation->id)
            ->where('journal_id', 'J-12345')
            ->exists());
    }

        public function test_validate_three_way_match_exception(): void
    {
        $this->seedData();

        $user = User::factory()->create();
        $company = $user->company()->first();

        $bill = PurchaseBill::factory()->create([
            'company_id' => $company->id,
            'journal_id' => null,
            'match_status' => 'exception',
            'match_tolerance_qty' => '0.00',
            'match_tolerance_price' => '0.00',
        ]);

        $service = new ApAllocationService();

        $result = $service->validateThreeWayMatch($bill->id, $company->id);

        $this->assertFalse($result['matched']);
        $this->assertEquals('exception', $result['match_status']);
        $this->assertNotEmpty($result['exceptions']);
    }

    public function test_calculate_outstanding(): void
    {
        $this->seedData();

        $user = User::factory()->create();
        $company = $user->company()->first();

        $bill = PurchaseBill::factory()->create([
            'company_id' => $company->id,
            'grand_total' => '100.00',
            'allocated_amount' => '40.00',
        ]);

        $service = new ApAllocationService();

        $outstanding = $service->calculateOutstanding($bill->id, $company->id);

        $this->assertEquals('60.00', $outstanding);
    }
}