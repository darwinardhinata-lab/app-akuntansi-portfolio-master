<?php

namespace Tests\Feature;

use App\Modules\PaymentPlan\Services\LegacyFinancialFence;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SupplierCreditReversalDateTest extends SupplierCreditReversalTest
{
    public function test_reversal_date_cannot_precede_original_credit(): void
    {
        $this->dateProbe('2026-01-01', false);
    }

    public function test_reversal_on_current_mgi_business_date_restores_maker_capacity(): void
    {
        $this->dateProbe('2026-11-19', true);
    }

    public function test_future_reversal_cannot_restore_maker_capacity_early(): void
    {
        $this->dateProbe('2026-11-20', false);
    }

    private function dateProbe(string $date, bool $accepted): void
    {
        // UTC is still November 18; MGI business date has already reached November 19.
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 18:30:00', 'UTC'));
        try {
            $f = $this->reversalFixture(true);
            $f['company']->update(['timezone' => 'Asia/Jakarta']);
            $before = $this->reversalSnapshot($f);
            $response = $this->postJson($f['reverse_url'], [...$f['reverse_payload'], 'posting_date' => $date]);
            $after = $this->reversalSnapshot($f);
            $maker = DB::transaction(function () use ($f) {
                LegacyFinancialFence::lock(true);
                return app(PurchaseBillAllocation::class)->source($f['bill'], $f['company']->id);
            });
            $journalDate = DB::table('journal_headers')->where('journal_id', $response->json('journal_id'))->value('transaction_date');
            $canReserve = false;
            DB::beginTransaction();
            try {
                LegacyFinancialFence::lock(true);
                $id = (string) Str::uuid();
                DB::table('ppl_requests')->insert(['id' => $id, 'company_id' => $f['company']->id, 'owner_user_id' => $f['finance']->id,
                    'doc' => 'DATE-MAKER', 'status' => 'submitted', 'payload' => json_encode(['purchaseBillId' => $f['bill']])]);
                app(PurchaseBillAllocation::class)->reserve(['id' => $id, 'purchaseBillId' => $f['bill'], 'settlement' => ['gross' => '190.00']], $f['company']->id);
                $canReserve = true;
            } catch (\App\Modules\PaymentPlan\Domain\RuleViolation $e) {
                $this->assertSame('overpayment', $e->getMessage());
            } finally { DB::rollBack(); }
            if ($path = getenv('CREDIT_DATE_EVIDENCE')) file_put_contents($path, json_encode([
                'requested_date' => $date, 'mgi_today' => now('Asia/Jakarta')->toDateString(), 'utc_today' => now('UTC')->toDateString(),
                'status' => $response->status(), 'journal_date' => $journalDate, 'residual_before' => $before['projection']['residual'],
                'residual_after' => $after['projection']['residual'], 'maker_capacity' => $maker['residual'],
                'maker_190_accepted' => $canReserve, 'financial_state_unchanged' => $before === $after], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
            $response->assertStatus($accepted ? 200 : 409);
            $this->assertSame($accepted ? '200.50' : '180.50', $maker['residual']);
            $this->assertSame($accepted, $canReserve);
            if ($accepted) $this->assertSame($date, substr($journalDate, 0, 10));
            else $this->assertEquals($before, $after);
        } finally { $this->travelBack(); }
    }
}