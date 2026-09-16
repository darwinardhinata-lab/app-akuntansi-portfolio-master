<?php

namespace App\Modules\Customs\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CeisaSignatureService - Layanan tanda tangan untuk request ke CEISA H2H.
 * 
 * TODO: Ganti implementasi sesuai spesifikasi resmi DJBC setelah diterima.
 * Saat ini menggunakan HMAC-SHA256 sebagai contoh dasar.
 */
class CeisaSignatureService
{
    /**
     * Tandatangani payload untuk dikirim ke CEISA.
     *
     * @param array $payload Data yang akan ditandatangani
     * @return array ['signature' => string, 'body' => string]
     * @throws \RuntimeException Jika modul tidak diaktifkan
     */
    public function sign(array $payload): array
    {
        if (! config('customs.enabled')) {
            throw new \RuntimeException('Modul CEISA H2H tidak diaktifkan. Set CEISA_ENABLED=true di .env');
        }

        // TODO: Ganti sesuai spesifikasi resmi DJBC setelah diterima
        $method = config('customs.signing.method', 'hmac');

        return match ($method) {
            'hmac' => $this->signHmac($payload),
            'x509' => $this->signX509($payload),
            default => throw new \RuntimeException("Metode signing tidak dikenali: {$method}"),
        };
    }

    /**
     * HMAC-SHA256 signature.
     * TODO: Ganti sesuai spesifikasi resmi DJBC setelah diterima.
     */
    private function signHmac(array $payload): array
    {
        $apiSecret = config('customs.signing.api_secret');
        
        if (empty($apiSecret)) {
            throw new \RuntimeException('CEISA_API_SECRET tidak diatur di .env');
        }

        $payloadJson = json_encode($payload, JSON_UNESCAPED_SLASHES);
        $timestamp = now()->timestamp;

        // HMAC atas payload + timestamp
        $dataToSign = $payloadJson . $timestamp;
        $signature = hash_hmac('sha256', $dataToSign, $apiSecret);

        return [
            'signature' => $signature,
            'body' => $payloadJson,
            'timestamp' => $timestamp,
        ];
    }

    /**
     * X.509 Digital Signature.
     * TODO: Ganti sesuai spesifikasi resmi DJBC setelah diterima.
     * Requires: composer require robrichards/xmlseclibs
     */
    private function signX509(array $payload): array
    {
        // TODO: Implementasi X.509 signing
        throw new \RuntimeException('X.509 signing belum diimplementasi. Hubungi tim untuk spesifikasi resmi.');
    }
}
