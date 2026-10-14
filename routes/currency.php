<?php

// FIX: multicurrency — mengikuti flag dan auth Customs; tidak mengarang permission RBAC baru.
use App\Modules\Currency\Http\Controllers\CurrencyController;
use App\Modules\Currency\Http\Controllers\ExchangeRateController;
use Illuminate\Support\Facades\Route;

if (config('platform.multicurrency_enabled')) {
    Route::prefix('currency')->name('currency.')->middleware(['web', 'auth'])->group(function () {
        Route::get('/', [CurrencyController::class, 'index'])->name('index');
        Route::put('/master/{code}', [CurrencyController::class, 'update'])->name('update');
        Route::get('/rates', [ExchangeRateController::class, 'index'])->name('rates.index');
        Route::get('/rates/lookup', [ExchangeRateController::class, 'lookup'])->name('rates.lookup');
        Route::post('/rates', [ExchangeRateController::class, 'store'])->name('rates.store');
        Route::post('/rates/import', [ExchangeRateController::class, 'import'])->name('rates.import');
        Route::put('/rates/{id}', [ExchangeRateController::class, 'update'])->name('rates.update');
        Route::delete('/rates/{id}', [ExchangeRateController::class, 'destroy'])->name('rates.destroy');
    });
}