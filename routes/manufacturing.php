<?php

use Illuminate\Support\Facades\Route;
use App\Modules\Manufacturing\Http\Controllers\WorkOrderController;
use App\Modules\Manufacturing\Http\Controllers\MaterialReceiptController;
use App\Modules\Manufacturing\Http\Controllers\KnitOrderController;
use App\Modules\Manufacturing\Http\Controllers\ProcessingOrderController;
use App\Modules\Manufacturing\Http\Controllers\CuttingOrderController;
use App\Modules\Manufacturing\Http\Controllers\StitchingOrderController;
use App\Modules\Manufacturing\Http\Controllers\FinishingStageController;
use App\Modules\Manufacturing\Http\Controllers\BarcodeLabelController;
use App\Modules\Manufacturing\Http\Controllers\YarnController;
use App\Modules\Manufacturing\Http\Controllers\FabricController;
use App\Modules\Manufacturing\Http\Controllers\SupplierController;
use App\Modules\Manufacturing\Http\Controllers\ManufacturingProcessController;

/*
|--------------------------------------------------------------------------
| Rute Modul Manufaktur (MFG/SPK)
|--------------------------------------------------------------------------
| File ini SENGAJA dipisah dari routes/web.php agar mudah di-diff/dihapus
| terpisah jika suatu saat dibutuhkan, dan supaya web.php Anda yang sudah
| besar tidak makin panjang. Cara pasang: tambahkan baris ini di dalam
| Route::middleware(['auth'])->group(function () { ... }) pada web.php:
|
|     require base_path('routes/manufacturing.php');
|
| (satu baris saja, di mana pun di dalam grup 'auth' — urutan tidak masalah
| karena tidak ada rute yang saling menimpa nama/prefix dengan modul lain).
*/

Route::prefix('manufaktur/spk')->name('mfg.work-orders.')->controller(WorkOrderController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::get('/{id}', 'show')->name('show');
    Route::post('/{id}/complete', 'complete')->name('complete');
    Route::post('/{id}/void-completion', 'voidCompletion')->name('void-completion');
});

Route::prefix('manufaktur/mrn')->name('mfg.material-receipts.')->controller(MaterialReceiptController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::get('/create', 'create')->name('create');
    Route::post('/', 'store')->name('store');
    Route::post('/import', 'import')->middleware('throttle:5,1')->name('import');
    Route::get('/download-template', 'downloadTemplate')->name('download-template');
    Route::get('/{id}', 'show')->name('show');
    Route::post('/{id}/void', 'void')->name('void');
});

Route::prefix('manufaktur/knit-order')->name('mfg.knit-orders.')->controller(KnitOrderController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::post('/{id}/issue-yarn', 'issueYarn')->name('issue-yarn');
    Route::post('/{id}/receive-grey-fabric', 'receiveGreyFabric')->name('receive-grey-fabric');
    Route::post('/yarn-issue/{id}/void', 'voidYarnIssue')->name('void-yarn-issue');
    Route::post('/grey-fabric-receipt/{id}/void', 'voidGreyFabricReceipt')->name('void-grey-fabric-receipt');
});

Route::prefix('manufaktur/processing-order')->name('mfg.processing-orders.')->controller(ProcessingOrderController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::post('/{id}/issue-fabric', 'issueFabric')->name('issue-fabric');
    Route::post('/{id}/receive-fabric', 'receiveFabric')->name('receive-fabric');
    Route::post('/fabric-issue/{id}/void', 'voidFabricIssue')->name('void-fabric-issue');
    Route::post('/fabric-receipt/{id}/void', 'voidFabricReceipt')->name('void-fabric-receipt');
});

Route::prefix('manufaktur/cutting-order')->name('mfg.cutting-orders.')->controller(CuttingOrderController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::post('/{id}/check', 'recordCheck')->name('check');
    Route::post('/{id}/void', 'void')->name('void');
    Route::post('/check/{id}/void', 'voidCheck')->name('void-check');
});

Route::prefix('manufaktur/stitching-order')->name('mfg.stitching-orders.')->controller(StitchingOrderController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::post('/{id}/void', 'void')->name('void');
});

Route::prefix('manufaktur/finishing')->name('mfg.finishing-stages.')->controller(FinishingStageController::class)->group(function () {
    Route::post('/', 'store')->name('store');
});

Route::prefix('manufaktur/barcode')->name('mfg.barcode-labels.')->controller(BarcodeLabelController::class)->group(function () {
    Route::post('/', 'store')->name('store');
    Route::post('/print', 'print')->name('print');
});

Route::prefix('manufaktur/laporan')->name('mfg.reports.')->group(function () {
    Route::get('/hpp', [\App\Modules\Manufacturing\Http\Controllers\ManufacturingReportController::class, 'hpp'])->name('hpp');
});

// --- Master Data Manufaktur ---
Route::prefix('manufaktur/yarn')->name('mfg.yarns.')->controller(YarnController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
    Route::post('/import', 'import')->middleware('throttle:5,1')->name('import');
    Route::get('/download-template', 'downloadTemplate')->name('download-template');
});

Route::prefix('manufaktur/fabric')->name('mfg.fabrics.')->controller(FabricController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
    Route::post('/import', 'import')->middleware('throttle:5,1')->name('import');
    Route::get('/download-template', 'downloadTemplate')->name('download-template');
});

Route::prefix('manufaktur/supplier')->name('mfg.suppliers.')->controller(SupplierController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
    Route::post('/import', 'import')->middleware('throttle:5,1')->name('import');
    Route::get('/download-template', 'downloadTemplate')->name('download-template');
});

Route::prefix('manufaktur/process')->name('mfg.processes.')->controller(ManufacturingProcessController::class)->group(function () {
    Route::get('/', 'index')->name('index');
    Route::post('/', 'store')->name('store');
    Route::put('/{id}', 'update')->name('update');
    Route::delete('/{id}', 'destroy')->name('destroy');
    Route::post('/import', 'import')->middleware('throttle:5,1')->name('import');
    Route::get('/download-template', 'downloadTemplate')->name('download-template');
});
