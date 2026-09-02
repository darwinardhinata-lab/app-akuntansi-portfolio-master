<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Jobs\SyncProductDashboardJob;
use App\Jobs\SyncSODashboardToTempJob;
use App\Jobs\SyncPODashboardToTempJob;
use App\Jobs\SyncInvDashboardToTempJob;
use App\Jobs\SyncBillDashboardToTempJob;
use App\Jobs\SyncDashboardToTempJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// =========================================================================
// AUTOMATIC SCHEDULER UNTUK SINKRONISASI DATA DARI DASHBOARD KE AKUNTANSI
// (Berjalan setiap jam dengan jeda menit agar beban server seimbang)
// =========================================================================

Schedule::job(new SyncProductDashboardJob())->hourlyAt(30);   // Berjalan tiap jam di menit ke-00 (01:00, 02:00, ...)
Schedule::job(new SyncSODashboardToTempJob())->hourlyAt(30);  // Berjalan tiap jam di menit ke-10 (01:10, 02:10, ...)
Schedule::job(new SyncPODashboardToTempJob())->hourlyAt(30);   // Berjalan tiap jam di menit ke-20 (01:20, 02:20, ...)
Schedule::job(new SyncInvDashboardToTempJob())->hourlyAt(30);  // Berjalan tiap jam di menit ke-30 (01:30, 02:30, ...)
Schedule::job(new SyncBillDashboardToTempJob())->hourlyAt(30); // Berjalan tiap jam di menit ke-40 (01:40, 02:40, ...)
Schedule::job(new SyncDashboardToTempJob())->hourlyAt(30);  // Berjalan setiap 30 menit (menit ke-00 dan ke-30)

