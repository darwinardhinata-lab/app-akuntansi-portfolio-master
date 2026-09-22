<?php

use App\Modules\CustomsReports\Http\Controllers\ReportPeriodController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes - Modul Laporan Bea Cukai (CEISA)
|--------------------------------------------------------------------------
|
| Laporan manual CEISA (bukan H2H). Di-require dari web.php.
| 7 jenis laporan: Pemasukan, Pengeluaran, Mutasi Bahan Baku, WIP,
| Mutasi Barang Jadi, Mutasi Barang Modal, Mutasi Barang Reject.
| (Riwayat Aktivitas — Fase B terpisah, tidak masuk di sini.)
|
| Route DISEDIAAKAN ketika config('customs.enabled') = true (sama seperti
| H2H module), agar konsisten dengan sidebar menu. Di environment testing
| CEISA_ENABLED=true sehingga route terdaftar otomatis.
*/

if (config('customs.enabled')) {
    Route::prefix('laporan-ceisa')->name('customs-reports.')->middleware(['auth'])->group(function () {
        Route::get('/', [ReportPeriodController::class, 'index'])->name('index');
        Route::get('/create', [ReportPeriodController::class, 'create'])->name('create');
        Route::post('/', [ReportPeriodController::class, 'store'])->name('store');
        Route::get('/{period}', [ReportPeriodController::class, 'show'])->name('show');
        Route::get('/{period}/edit', [ReportPeriodController::class, 'edit'])->name('edit');
        Route::put('/{period}', [ReportPeriodController::class, 'update'])->name('update');
        Route::delete('/{period}', [ReportPeriodController::class, 'destroy'])->name('destroy');
        Route::post('/{period}/import', [ReportPeriodController::class, 'import'])->name('import');
        Route::get('/{period}/export', [ReportPeriodController::class, 'export'])->name('export');
        Route::get('/{period}/template', [ReportPeriodController::class, 'downloadTemplate'])->name('template');
        Route::post('/{period}/finalize', [ReportPeriodController::class, 'finalize'])->name('finalize');
        Route::post('/{period}/mark-uploaded', [ReportPeriodController::class, 'markUploaded'])->name('mark-uploaded');
        Route::post('/{period}/populate-from-h2h', [ReportPeriodController::class, 'populateFromH2H'])->name('populate-from-h2h');
    });
}
