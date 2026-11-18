<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class LegacyPaymentReversalUnsupportedTest extends TestCase
{
    use RefreshDatabase;

    public function test_allowlisted_entry_point_explicitly_rejects_before_transaction_or_write_including_retry(): void
    {
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.payment_post_user_ids' => [$user->id], 'platform.payment_reverse_user_ids' => [$user->id],
            'payment_plan_v33.bank_workflow' => true]);
        $this->actingAs($user);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void { $queries[] = $query->sql; });
        $depth = DB::transactionLevel();
        for ($n = 0; $n < 2; $n++) {
            $this->postJson(route('payment.reverse_legacy', 'legacy-posted-test'), ['reason' => 'Synthetic unsupported reversal'])
                ->assertStatus(409)->assertJsonPath('message', __('erp.payment_legacy_reversal_unsupported'));
            $this->assertSame($depth, DB::transactionLevel());
        }
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/\b(insert|update|delete|for update)\b/i', $query);
            $this->assertStringNotContainsString('transaksi_payment_plan', $query);
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('bill_payment_allocations', 0);
    }

    public function test_original_role_and_both_allowlists_are_not_bypassed(): void
    {
        $user = User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($user);
        $url = route('payment.reverse_legacy', 'test');
        $data = ['reason' => 'Synthetic unsupported reversal'];
        $this->postJson($url, $data)->assertForbidden();
        config(['platform.payment_post_user_ids' => [$user->id], 'platform.payment_reverse_user_ids' => []]);
        $this->postJson($url, $data)->assertForbidden();
        config(['platform.payment_reverse_user_ids' => [$user->id]]);
        $this->postJson($url, [])->assertUnprocessable();
        $user->update(['role' => 'ADMIN']);
        $this->postJson($url, $data)->assertForbidden();
    }
}