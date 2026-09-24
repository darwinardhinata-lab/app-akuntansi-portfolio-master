<?php

use App\Modules\Platform\Http\Controllers\CompanyContextController;
use App\Modules\Platform\Http\Controllers\PartyController;
use App\Modules\Platform\Http\Middleware\RequireCompany;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->prefix('platform')->name('platform.')->group(function () {
    Route::get('/company', [CompanyContextController::class, 'edit'])->name('company.edit');
    Route::post('/company', [CompanyContextController::class, 'update'])->name('company.update');

    Route::middleware(RequireCompany::class)->group(function () {
        Route::get('/parties', [PartyController::class, 'index'])->name('parties.index');
        Route::get('/parties/create', [PartyController::class, 'create'])->name('parties.create');
        Route::post('/parties', [PartyController::class, 'store'])->name('parties.store');
        Route::get('/parties/{id}/edit', [PartyController::class, 'edit'])->whereNumber('id')->name('parties.edit');
        Route::put('/parties/{id}', [PartyController::class, 'update'])->whereNumber('id')->name('parties.update');
    });
});
