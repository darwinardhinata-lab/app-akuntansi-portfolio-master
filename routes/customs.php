<?php

use App\Modules\Customs\Http\Controllers\CustomsDocumentController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes - Modul Kepabeanan (Customs/CEISA H2H)
|--------------------------------------------------------------------------
|
| Route ini di-require dari web.php utama.
| Semua route internal membutuhkan autentikasi kecuali webhook.
|
*/

if (config('customs.enabled')) {
    Route::prefix('customs')->name('customs.')->middleware(['auth'])->group(function () {
        Route::get('/', [CustomsDocumentController::class, 'index'])->name('index');
        Route::get('/documents', [CustomsDocumentController::class, 'index'])->name('documents.index');
        Route::get('/create/{sourceType}/{sourceId}', [CustomsDocumentController::class, 'create'])->name('create');
        Route::get('/export', [CustomsDocumentController::class, 'export'])->name('export');
        Route::get('/{document}', [CustomsDocumentController::class, 'show'])->name('show');
        Route::put('/{document}', [CustomsDocumentController::class, 'update'])->name('update');
        Route::get('/{document}/edit', [CustomsDocumentController::class, 'show'])->name('edit');
        Route::post('/{document}/submit', [CustomsDocumentController::class, 'submit'])->name('submit');
        Route::post('/{document}/retry', [CustomsDocumentController::class, 'retry'])->name('retry');
        Route::post('/{document}/void', [CustomsDocumentController::class, 'void'])->name('void');
        Route::get('/{document}/print', [CustomsDocumentController::class, 'print'])->name('print');

        // Laporan Bea Cukai (8 Laporan IT Inventory / TPB)
        Route::prefix('reports')->name('reports.')->group(function () {
            Route::get('/inbound', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'inbound'])->name('inbound');
            Route::get('/outbound', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'outbound'])->name('outbound');
            Route::get('/mutation-raw', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'mutationRaw'])->name('mutation-raw');
            Route::get('/wip', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'wip'])->name('wip');
            Route::get('/mutation-finished', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'mutationFinished'])->name('mutation-finished');
            Route::get('/mutation-capital', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'mutationCapital'])->name('mutation-capital');
            Route::get('/mutation-reject', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'mutationReject'])->name('mutation-reject');
            Route::get('/activity-log', [\App\Modules\Customs\Http\Controllers\CustomsReportController::class, 'activityLog'])->name('activity-log');
        });
    });
}

/*
|--------------------------------------------------------------------------
| Webhook Route - CEISA H2H Callback
|--------------------------------------------------------------------------
|
| Route ini TIDAK memerlukan autentikasi session, tetapi harus diverifikasi
| melalui signature dari CEISA (implementasi di controller).
|
*/
Route::post('/webhook/customs/ceisa', [CustomsDocumentController::class, 'handleWebhook'])
    ->name('customs.webhook');


