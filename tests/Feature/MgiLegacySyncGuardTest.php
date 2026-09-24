<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MgiLegacySyncGuardTest extends TestCase
{
    public function test_disabled_sync_does_not_read_or_write_any_database(): void
    {
        config(['platform.legacy_sync_enabled' => false]);
        DB::shouldReceive('connection')->never();
        DB::shouldReceive('table')->never();
        $jobs = [
            \App\Jobs\SyncProductDashboardJob::class,
            \App\Jobs\SyncPODashboardToTempJob::class,
            \App\Jobs\SyncSODashboardToTempJob::class,
            \App\Jobs\SyncInvDashboardToTempJob::class,
            \App\Jobs\SyncBillDashboardToTempJob::class,
            \App\Jobs\SyncDashboardToTempJob::class,
            \App\Jobs\ProcessPendingTempJob::class,
            \App\Jobs\ProcessPendingPOTempJob::class,
            \App\Jobs\ProcessPendingSOTempJob::class,
            \App\Jobs\ProcessPendingInvTempJob::class,
            \App\Jobs\ProcessPendingBillTempJob::class,
        ];
        foreach ($jobs as $class) {
            $this->assertNull((new $class)->handle());
        }
    }

    public function test_prepare_command_refuses_sqlite_without_modifying_database(): void
    {
        $this->artisan('platform:prepare-mgi', ['target' => 'mgi_fresh_test'])
            ->expectsOutputToContain('hanya mendukung MySQL/MariaDB')->assertExitCode(1);
    }
}
