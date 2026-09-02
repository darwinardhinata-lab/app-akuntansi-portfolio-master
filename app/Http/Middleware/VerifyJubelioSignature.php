<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\SystemLog;

class VerifyJubelioSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        $secret = env('JUBELIO_WEBHOOK_SECRET');

        if (empty($secret)) {
            SystemLog::record('ERROR', 'Webhook Security', 'JUBELIO_WEBHOOK_SECRET belum dikonfigurasi di .env');
            return response()->json(['status' => 'error', 'message' => 'Internal Server Error: Webhook secret not configured.'], 500);
        }

        $signatureHeader = $request->header('Authorization');

        if (!$signatureHeader) {
            SystemLog::record('WARNING', 'Webhook Security', 'Akses webhook ditolak: Missing Signature Header dari IP ' . $request->ip());
            return response()->json(['status' => 'error', 'message' => 'Unauthorized: Missing Signature Header'], 401);
        }

        $payload = $request->getContent();
        $calculatedSignature = hash_hmac('sha256', $payload, $secret);

        if (!hash_equals($calculatedSignature, $signatureHeader)) {
            SystemLog::record('WARNING', 'Webhook Security', 'Akses webhook ditolak: Invalid Signature dari IP ' . $request->ip());
            return response()->json(['status' => 'error', 'message' => 'Forbidden: Invalid Signature'], 403);
        }

        return $next($request);
    }
}
