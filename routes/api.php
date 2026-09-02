<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\JubelioWebhookController;

// FIX #030: Webhook endpoint untuk external API calls (Jubelio)
// P0-2: Rate limiting + signature verification middleware
// Route ini dipindahkan dari web.php ke api.php (lebih tepat untuk webhook eksternal)
Route::post('/jubelio/webhook/sales', [JubelioWebhookController::class, 'handleSalesWebhook'])
    ->middleware(['throttle:60,1', \App\Http\Middleware\VerifyJubelioSignature::class]);