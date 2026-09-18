<?php

namespace Tests\Feature;

use App\Modules\Customs\Services\CeisaSignatureService;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Task E (Fase 2): HMAC signature harus deterministik dan bisa diverifikasi manual.
 * Tidak ada HTTP request sama sekali di test ini — murni perhitungan signing.
 */
class CeisaSignatureServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'customs.signing.method' => 'hmac',
            'customs.signing.client_id' => 'test-client-id',
            'customs.signing.client_secret' => 'test-secret',
            'customs.signing.access_token' => 'test-token',
        ]);
    }

    /**
     * Memverifikasi formula "String to Sign = HTTP_METHOD:PATH_URL:ACCESS_TOKEN:TIMESTAMP"
     * di-HMAC-SHA256 dengan client_secret lalu di-Base64 — signature dihitung ulang
     * MANUAL di test ini dan dibandingkan dengan hasil service.
     */
    public function test_hmac_signature_matches_manual_calculation(): void
    {
        // Freeze waktu supaya timestamp deterministik.
        Carbon::setTestNow('2026-09-18 10:00:00');
        $expectedTimestamp = (string) now()->timestamp;

        $signer = new CeisaSignatureService();
        $result = $signer->sign(['foo' => 'bar'], 'POST', '/ceisa/v1/pib');

        // Perhitungan manual di test ini sendiri (bukan memanggil service):
        $manualSignature = base64_encode(
            hash_hmac('sha256', "POST:/ceisa/v1/pib:test-token:{$expectedTimestamp}", 'test-secret', true)
        );

        $this->assertSame($manualSignature, $result['headers']['beacukai-signature']);
        $this->assertSame($expectedTimestamp, $result['headers']['timestamp']);
        $this->assertSame('test-client-id', $result['headers']['client-id']);
        $this->assertSame('Bearer test-token', $result['headers']['Authorization']);

        // Deterministik: panggilan kedua dengan waktu yang dibekukan sama harus identik.
        $result2 = $signer->sign(['foo' => 'bar'], 'POST', '/ceisa/v1/pib');
        $this->assertSame($result['headers']['beacukai-signature'], $result2['headers']['beacukai-signature']);

        Carbon::setTestNow();
    }

    public function test_hmac_throws_when_credentials_missing(): void
    {
        config(['customs.signing.client_secret' => null]);

        $signer = new CeisaSignatureService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('CEISA_CLIENT_SECRET dan/atau CEISA_ACCESS_TOKEN belum diatur');

        $signer->sign(['foo' => 'bar'], 'POST', '/ceisa/v1/pib');
    }

    /**
     * Stub RSA/X.509 sengaja melempar exception sampai dikonfirmasi modul mana
     * yang butuh tanda tangan asimetris (lihat GAPS_TO_CONFIRM.md).
     */
    public function test_asymmetric_signing_throws_not_implemented(): void
    {
        config(['customs.signing.method' => 'rsa']);

        $signer = new CeisaSignatureService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Signing RSA/X.509 belum diimplementasi');

        $signer->sign(['foo' => 'bar'], 'POST', '/ceisa/v1/pib');
    }
}