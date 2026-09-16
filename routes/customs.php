<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Routes - Modul Kepabeanan (Customs/CEISA H2H)
|--------------------------------------------------------------------------
|
| Route ini di-require dari web.php utama.
| Semua route membutuhkan autentikasi kecuali webhook.
|
*/

if (config('customs.enabled')) {
    Route::prefix('customs')->name('customs.')->middleware(['auth'])->group(function () {
        // Route diisi bertahap di fase berikutnya - sengaja kosong dulu
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
Route::post('/webhook/customs/ceisa', function () {
    return response()->json(['status' => 'OK']);
})->name('customs.webhook');

