<?php

use App\Modules\Customs\Http\Controllers\KepabeanController;
use Illuminate\Support\Facades\Route;

// Local recording is available independently of CEISA transport.
if (config('customs.reports_enabled') || config('customs.enabled')) {
    Route::prefix('kepabean')->name('kepabean.')->middleware('auth')->group(function () {
        Route::get('/', [KepabeanController::class, 'dashboard'])->name('dashboard');
        Route::get('/documents', [KepabeanController::class, 'index'])->name('documents');
        Route::get('/documents/create', [KepabeanController::class, 'create'])->name('create');
        Route::post('/documents', [KepabeanController::class, 'store'])->name('store');
        Route::get('/documents/{document}', [KepabeanController::class, 'show'])->name('show');
        Route::get('/documents/{document}/edit', [KepabeanController::class, 'edit'])->name('edit');
        Route::put('/documents/{document}', [KepabeanController::class, 'update'])->name('update');
        Route::delete('/documents/{document}', [KepabeanController::class, 'destroy'])->name('destroy');
    });
}