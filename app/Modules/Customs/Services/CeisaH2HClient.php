<?php

namespace App\Modules\Customs\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CeisaH2HClient - HTTP client untuk berkomunikasi dengan CEISA H2H.
 * 
 * Client ini SELALU melempar exception jika config('customs.enabled') false,
 * untuk mencegah koneksi ke server nyata sebelum siap.
 */
class CeisaH2HClient
{
    public function __construct(
        private readonly CeisaSignatureService $signer
    ) {}

    /**
     * Kirim dokumen ke CEISA H2H.
     *
     * @param string $documentType 'PIB' atau 'PEB'
     * @param array $payload Data dokumen
     * @param string $correlationId ID untuk tracking
     * @return array ['http_status' => int, 'body' => array, 'raw' => string]
     * @throws \RuntimeException Jika modul tidak diaktifkan
     */
    public function submit(string $documentType, array $payload, string $correlationId): array
    {
        if (! config('customs.enabled')) {
            throw new \RuntimeException('Modul CEISA H2H tidak diaktifkan. Set CEISA_ENABLED=true di .env untuk mengaktifkan.');
        }

        $signed = $this->signer->sign($payload);

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders([
                'Content-Type' => 'application/json',
                'X-Correlation-Id' => $correlationId,
                'X-Signature' => $signed['signature'],
                'X-Timestamp' => $signed['timestamp'] ?? time(),
            ])
            ->timeout(config('customs.timeout', 30))
            ->retry(
                config('customs.retry.times', 3),
                fn ($attempt) => config('customs.retry.backoff_seconds')[$attempt - 1] ?? 300,
                throw: false,
            )
            ->post($this->endpointFor($documentType), $signed['body']);

        return [
            'http_status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ];
    }

    /**
     * Cek status dokumen di CEISA.
     *
     * @param string $nomorAju Nomor ajuan dari CEISA
     * @return array ['http_status' => int, 'body' => array, 'raw' => string]
     * @throws \RuntimeException Jika modul tidak diaktifkan
     */
    public function checkStatus(string $nomorAju): array
    {
        if (! config('customs.enabled')) {
            throw new \RuntimeException('Modul CEISA H2H tidak diaktifkan. Set CEISA_ENABLED=true di .env untuk mengaktifkan.');
        }

        $response = Http::baseUrl($this->baseUrl())
            ->timeout(config('customs.timeout', 30))
            ->get($this->statusEndpoint(), ['nomor_aju' => $nomorAju]);

        return [
            'http_status' => $response->status(),
            'body' => $response->json(),
            'raw' => $response->body(),
        ];
    }

    private function baseUrl(): string
    {
        $env = config('customs.default_environment', 'sandbox');
        return config("customs.endpoints.{$env}");
    }

    private function endpointFor(string $documentType): string
    {
        // TODO: Ganti sesuai endpoint resmi DJBC setelah diterima
        return '/api/v1/submit/' . strtolower($documentType);
    }

    private function statusEndpoint(): string
    {
        // TODO: Ganti sesuai endpoint resmi DJBC setelah diterima
        return '/api/v1/status';
    }
}
