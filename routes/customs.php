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
        Route::get('/{document}/edit', [CustomsDocumentController::class, 'edit'])->name('edit');
        Route::post('/{document}/submit', [CustomsDocumentController::class, 'submit'])->name('submit');
        Route::post('/{document}/retry', [CustomsDocumentController::class, 'retry'])->name('retry');
        Route::post('/{document}/void', [CustomsDocumentController::class, 'void'])->name('void');
        Route::get('/{document}/print', [CustomsDocumentController::class, 'print'])->name('print');
    });

    /*
    | Webhook Route - CEISA H2H Callback
    |
    | FIX (Fase 1-B / H2): dipindahkan ke DALAM blok config('customs.enabled') agar
    | tidak aktif ketika modul CEISA dinonaktifkan (default: false).
    | Tetap DI LUAR grup middleware 'auth' karena dipanggil oleh server CEISA
    | (tanpa session) — verifikasi dilakukan lewat signature di controller (Fase 2).
    */
    Route::post('/webhook/customs/ceisa', [CustomsDocumentController::class, 'handleWebhook'])
        ->name('customs.webhook');
}


