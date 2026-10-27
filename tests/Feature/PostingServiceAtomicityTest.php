<?php

namespace Tests\Feature;

use App\Support\PostingService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostingServiceAtomicityTest extends TestCase
{
    use RefreshDatabase;

    public function test_base_currency_detail_failure_does_not_leave_an_orphan_header(): void
    {
        try {
            PostingService::post(['transaction_date' => '2026-10-01'], [
                ['account_code' => '111001', 'position' => 'DEBET', 'amount' => 10, 'invalid_column' => true],
                ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => 10, 'invalid_column' => true],
            ]);
            $this->fail('Invalid detail insert must fail.');
        } catch (QueryException $exception) {
            $this->assertStringContainsString('invalid_column', $exception->getMessage());
        }
        $this->assertDatabaseCount('journal_headers', 0);
        $this->assertDatabaseCount('journal_details', 0);
    }
}