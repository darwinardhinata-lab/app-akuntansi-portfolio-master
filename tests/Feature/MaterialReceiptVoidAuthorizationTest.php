<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MaterialReceiptVoidAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_and_non_finance_users_cannot_void_even_when_allowlisted(): void
    {
        foreach (['STAFF', 'ADMIN', 'FINANCE'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            config(['platform.mrn_void_user_ids' => $role === 'FINANCE' ? [] : [$user->id]]);
            $this->actingAs($user)->post(route('mfg.material-receipts.void', 999), ['reason' => 'Audit correction'])->assertForbidden();
        }
        $this->assertDatabaseCount('journal_headers', 0);
    }

    public function test_authorized_finance_must_supply_reason_before_lookup(): void
    {
        $user = User::factory()->create(['role' => 'FINANCE']);
        config(['platform.mrn_void_user_ids' => [$user->id]]);
        $this->actingAs($user)->postJson(route('mfg.material-receipts.void', 999), ['reason' => ' '])->assertUnprocessable();
    }
}