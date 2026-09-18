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
     * Kirim dokumen ke CEISA H2H melalui endpoint sandbox/produksi.
     *
     * @param string $documentType 'PIB' atau 'PEB'
     * @param array  $payload      Data dokumen
     * @param string $correlationId ID untuk tracking
     * @return array ['http_status' => int, 'body' => array, 'raw' => string]
     * @throws \RuntimeException Jika modul tidak diaktifkan atau path tidak ditemukan
     */
    public function submit(string $documentType, array $payload, string $correlationId): array
    {
        if (! config('customs.enabled')) {
            throw new \RuntimeException('Modul Customs (CEISA H2H) tidak aktif. Set CEISA_ENABLED=true di .env untuk mengaktifkan (hanya untuk testing manual terverifikasi).');
        }

        $path = config("customs.endpoints.paths.{$documentType}");

        if (! $path) {
            throw new \RuntimeException("Path endpoint untuk dokumen tipe {$documentType} tidak ditemukan di config.");
        }

        $signed = $this->signer->sign($payload, 'POST', $path);

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders(array_merge($signed['headers'], [
                'Content-Type' => 'application/json',
                'X-Correlation-Id' => $correlationId,
            ]))
            ->timeout(config('customs.timeout'))
            ->retry(
                config('customs.retry.times'),
                fn ($attempt) => config('customs.retry.backoff_seconds')[$attempt - 1] ?? 300,
                throw: false,
            )
            ->post($path, $signed['body']);

        return [
            'http_status' => $response->status(),
            'body' => $response->json() ?? [],
            'raw' => $response->body(),
        ];
    }

    /**
     * Cek status dokumen di CEISA.
     *
     * @param string $documentType 'PIB' atau 'PEB'
     * @param string $nomorAju     Nomor ajuan dari CEISA
     * @return array ['http_status' => int, 'body' => array, 'raw' => string]
     * @throws \RuntimeException Jika modul tidak diaktifkan
     */
    public function checkStatus(string $documentType, string $nomorAju): array
    {
        if (! config('customs.enabled')) {
            throw new \RuntimeException('Modul Customs (CEISA H2H) tidak aktif. Set CEISA_ENABLED=true di .env untuk mengaktifkan.');
        }

        $path = config("customs.endpoints.paths.{$documentType}") . '/' . $nomorAju;
        // [BELUM PASTI - TODO] Format path GET untuk cek status ini adalah asumsi REST
        // umum, BUKAN dari spesifikasi resmi — wajib dikonfirmasi ke dokumentasi DJBC.

        $signed = $this->signer->sign([], 'GET', $path);

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders(array_merge($signed['headers'], [
                'X-Correlation-Id' => (string) \Illuminate\Support\Str::uuid(),
            ]))
            ->timeout(config('customs.timeout'))
            ->get($path);

        return [
            'http_status' => $response->status(),
            'body' => $response->json() ?? [],
            'raw' => $response->body(),
        ];
    }

    private function baseUrl(): string
    {
        $env = config('customs.default_environment', 'sandbox');
        return config("customs.endpoints.{$env}");
    }
}
