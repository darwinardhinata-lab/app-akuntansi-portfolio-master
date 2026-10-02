<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Manufacturing\Services\MaklunIssueReversalService;
use App\Support\MaklunReversalAuthorization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class MaklunReversalAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoints_deny_non_allowlisted_users_even_admin(): void
    {
        foreach (['ADMIN', 'STAFF', 'FINANCE'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $this->actingAs($user);
            config(['platform.maklun_reversal_user_ids' => $role === 'FINANCE' ? [] : [$user->id]]);
            foreach (['mfg.knit-orders.void-yarn-issue', 'mfg.knit-orders.void-grey-fabric-receipt', 'mfg.processing-orders.void-fabric-issue', 'mfg.processing-orders.void-fabric-receipt'] as $name) {
                $this->postJson(route($name, 999), ['reason' => 'Koreksi disahkan Finance'])->assertForbidden();
            }
            $this->assertFalse(MaklunReversalAuthorization::allowed($user));
        }
        $this->assertDatabaseCount('system_logs', 0);
    }

    public function test_allowlisted_finance_requires_reason_before_reading_document(): void
    {
        $user = User::factory()->create(['role' => 'FINANCE']);
        $this->actingAs($user);
        config(['platform.maklun_reversal_user_ids' => [$user->id]]);
        foreach (['', '  ', 'short'] as $reason) {
            $this->postJson(route('mfg.knit-orders.void-yarn-issue', 999), ['reason' => $reason])
                ->assertUnprocessable()->assertJsonValidationErrors('reason');
        }
        $this->assertTrue(MaklunReversalAuthorization::allowed($user));
        $this->assertDatabaseCount('mfg_material_ledgers', 0);
    }

    public function test_service_itself_rejects_unauthorized_caller(): void
    {
        $this->expectException(HttpException::class);
        app(MaklunIssueReversalService::class)->reverse(true, 999, 'Koreksi disahkan Finance');
    }
}
