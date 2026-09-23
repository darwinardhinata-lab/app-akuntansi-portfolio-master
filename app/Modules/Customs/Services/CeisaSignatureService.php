<?php

namespace App\Modules\Customs\Services;

/**
 * CeisaSignatureService - Layanan tanda tangan untuk request ke CEISA H2H.
 *
 * Formula (sesuai spesifikasi resmi yang diberikan user):
 *   String to Sign = HTTP_METHOD:PATH_URL:ACCESS_TOKEN:TIMESTAMP
 *   Signature      = Base64(HMAC-SHA256(String to Sign, client_secret))
 */
class CeisaSignatureService
{
    /**
     * Tandatangani payload untuk dikirim ke CEISA.
     *
     * @param array  $payload    Data yang akan ditandatangani (body request)
     * @param string $httpMethod HTTP method (POST/GET)
     * @param string $pathUrl    Path endpoint, mis. '/ceisa/v1/pib'
     * @return array ['body' => array, 'headers' => array]
     *
     * CATATAN Fase 2: signature method berubah dari sign(array $payload) versi
     * Fase 1 menjadi butuh 2 parameter tambahan ($httpMethod, $pathUrl) karena
     * string-to-sign butuh keduanya. Semua pemanggil (CeisaH2HClient) sudah
     * disesuaikan.
     */
    public function sign(array $payload, string $httpMethod = 'POST', string $pathUrl = ''): array
    {
        return match (config('customs.signing.method')) {
            'hmac' => $this->signHmac($payload, $httpMethod, $pathUrl),
            'rsa' => $this->signAsymmetric($payload, $httpMethod, $pathUrl),
            default => throw new \RuntimeException(
                'Metode signing tidak dikenal: ' . config('customs.signing.method')
            ),
        };
    }

    private function signHmac(array $payload, string $httpMethod, string $pathUrl): array
    {
        $clientSecret = config('customs.signing.client_secret');
        $accessToken = config('customs.signing.access_token');

        if (! $clientSecret || ! $accessToken) {
            throw new \RuntimeException(
                'CEISA_CLIENT_SECRET dan/atau CEISA_ACCESS_TOKEN belum diatur di .env — signing HMAC tidak dapat dilakukan.'
            );
        }

        // [BELUM PASTI - TODO] format timestamp (epoch detik vs ms vs ISO8601)
        // belum dikonfirmasi — perlu dicek ulang ke dokumentasi resmi DJBC.
        $timestamp = (string) now()->timestamp;

        $stringToSign = "{$httpMethod}:{$pathUrl}:{$accessToken}:{$timestamp}";
        $signature = base64_encode(hash_hmac('sha256', $stringToSign, $clientSecret, true));

        return [
            'body' => $payload,
            'headers' => [
                config('customs.signing.signature_header') => $signature,
                config('customs.signing.client_id_header') => config('customs.signing.client_id'),
                config('customs.signing.timestamp_header') => $timestamp,
                config('customs.signing.authorization_header') => 'Bearer ' . $accessToken,
            ],
        ];
    }

    /**
     * [BELUM PASTI - TODO] Beberapa modul CEISA disebutkan membutuhkan tanda tangan
     * asimetris (RSA) dengan Private Key sertifikat X.509 perusahaan, tapi TIDAK
     * dijelaskan modul/endpoint mana persisnya. Sengaja dibuat melempar exception
     * dulu daripada menebak implementasi yang salah.
     */
    private function signAsymmetric(array $payload, string $httpMethod, string $pathUrl): array
    {
        throw new \RuntimeException(
            'Signing RSA/X.509 belum diimplementasi — perlu konfirmasi modul CEISA mana ' .
            'yang membutuhkan tanda tangan asimetris sebelum bisa dikerjakan.'
        );
    }
}
