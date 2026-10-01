<?php

namespace Tests\Unit;

use App\Models\JournalHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JournalEvidenceNumberTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_journal_generates_internal_evidence_and_preserves_source_transaction_number(): void
    {
        $first = JournalHeader::create([
            'transaction_date' => '2026-10-01',
            'evidence_number' => 'DOKUMEN-FISIK-001',
            'journal_type' => 'MANUAL',
        ]);
        $second = JournalHeader::create([
            'transaction_date' => '2026-10-01',
            'source_doc_no' => 'DOKUMEN-FISIK-002',
            'journal_type' => 'MANUAL',
        ]);

        $this->assertSame('GJ-20261001-0001', $first->evidence_number);
        $this->assertSame('DOKUMEN-FISIK-001', $first->source_doc_no);
        $this->assertSame('GJ-20261001-0002', $second->evidence_number);
        $this->assertSame('DOKUMEN-FISIK-002', $second->source_doc_no);
    }
}