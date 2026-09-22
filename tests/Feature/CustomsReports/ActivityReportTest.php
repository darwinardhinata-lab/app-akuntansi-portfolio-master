<?php

namespace Tests\Feature\CustomsReports;

use App\Models\SystemLog;
use App\Models\User;
use App\Modules\CustomsReports\Services\ActivityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_activity_report_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $this->makeActivity($user, 'Aktivitas lama', '2026-09-01 08:00:00');
        $included = $this->makeActivity($user, 'Aktivitas dalam rentang', '2026-09-15 12:00:00');
        $this->makeActivity($user, 'Aktivitas baru', '2026-10-01 08:00:00');

        $activities = app(ActivityReportService::class)->query('2026-09-10', '2026-09-20');

        $this->assertCount(1, $activities);
        $this->assertSame($included->id, $activities->first()->id);
    }

    public function test_activity_report_extracts_transaction_number_from_description(): void
    {
        $transactionNumber = app(ActivityReportService::class)
            ->extractTransactionNumber('Membuat SPK: WO/AFI/26/2800001');

        $this->assertSame('WO/AFI/26/2800001', $transactionNumber);
    }

    public function test_activity_report_handles_description_without_transaction_number(): void
    {
        $transactionNumber = app(ActivityReportService::class)
            ->extractTransactionNumber('Login: Andi Prasetyo');

        $this->assertNull($transactionNumber);
    }

    private function makeActivity(User $user, string $description, string $createdAt): SystemLog
    {
        return SystemLog::create([
            'user_id' => $user->id,
            'action' => 'CREATE',
            'module' => 'Manufacturing',
            'description' => $description,
            'ip_address' => '127.0.0.1',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
        ]);
    }
}