<?php

use App\Modules\PaymentPlan\Http\Controllers\WorkflowController;
use Illuminate\Support\Facades\Route;

// Web routing retains ERP session and CSRF; controller also enforces default-off flag.
Route::middleware('auth')->prefix('payment-plan/api')->name('payment-plan-v33.')->group(function () {
    Route::get('data', [WorkflowController::class, 'data'])->name('data');
    Route::get('purchase-bills', [WorkflowController::class, 'purchaseBills'])->name('purchase-bills');
    Route::post('request', [WorkflowController::class, 'create'])->name('request');
    Route::post('attachment', [WorkflowController::class, 'attachment'])->name('attachment');
    Route::get('attachment/{id}', [WorkflowController::class, 'download'])->name('download');
    Route::post('action', [WorkflowController::class, 'action'])->name('action');
});
Route::get('/payment-plan/bank', [WorkflowController::class, 'workspace'])->middleware('auth')->name('payment-plan-v33.workspace');
Route::get('/payment-plan/bank/{id}/print', [WorkflowController::class, 'printRequest'])->middleware('auth')->name('payment-plan-v33.print');