<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class JournalBalanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unbalanced_journal_is_rejected()
    {
        // Bypass auth middleware for tests
        $this->withoutMiddleware();

        $payload = [
            'transaction_date' => now()->format('Y-m-d'),
            'evidence_number'  => 'TEST-EV-001',
            'description'      => 'Unbalanced journal test',
            'details' => [
                [
                    'account_code' => '11100',
                    'position'     => 'DEBET',
                    'amount'       => 100.00,
                ],
                // No corresponding KREDIT -> unbalanced
            ],
        ];

        $response = $this->post(route('jurnal.store'), $payload);

        // Expected behavior: the application SHOULD reject unbalanced journal and set session error.
        // This test asserts that no journal header was created and an error message exists in session.
        $this->assertDatabaseMissing('journal_headers', [
            'evidence_number' => 'TEST-EV-001'
        ]);

        $response->assertSessionHas('error');
    }
}