<?php

namespace Tests\Feature;

use App\Models\User;
use App\Modules\Platform\Models\Company;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class InspectPaymentPlanAttachmentsTest extends TestCase
{
    use RefreshDatabase;

    public function test_clean_inventory_includes_archived_evidence(): void
    {
        $disk = $this->privateDisk();
        $key = $this->attachment('archived evidence', archived: true);
        $disk->put($key, 'archived evidence');

        $this->artisan('ppl:attachments-inspect')
            ->expectsOutput($this->summary(valid: 1))
            ->assertSuccessful();

        $this->assertSame('archived evidence', $disk->get($key));
        $this->assertDatabaseCount('ppl_attachment_audits', 0);
    }

    public function test_mismatches_fail_without_repair_or_disclosing_keys(): void
    {
        $disk = $this->privateDisk();
        $this->attachment('missing');
        $changed = $this->attachment('original');
        $this->attachment('invalid', key: 'payment-plan/../outside');
        $this->attachment('wrong context', key: 'payment-plan/999999/unrelated');
        $disk->put($changed, 'changed');
        $disk->put('payment-plan/orphan', 'orphan');
        $disk->put('unrelated/file', 'outside scope');
        $files = $disk->allFiles();
        $rows = DB::table('ppl_attachments')->orderBy('id')->get()->toArray();

        $this->artisan('ppl:attachments-inspect')
            ->expectsOutput($this->summary(missing: 1, mismatch: 1, invalid: 2, orphan: 1))
            ->doesntExpectOutputToContain('payment-plan/')
            ->assertFailed();

        $this->assertEquals($rows, DB::table('ppl_attachments')->orderBy('id')->get()->toArray());
        $this->assertSame($files, $disk->allFiles());
        $this->assertSame('changed', $disk->get($changed));
        $this->assertSame('orphan', $disk->get('payment-plan/orphan'));
        $this->assertDatabaseCount('ppl_attachment_audits', 0);
    }

    public function test_unsupported_disk_is_rejected_without_accessing_it(): void
    {
        config(['filesystems.disks.local.driver' => 'unsupported']);

        $this->artisan('ppl:attachments-inspect')
            ->expectsOutput('Only the private local disk is supported.')
            ->assertFailed();
    }

    public function test_reconciliation_attachment_uses_its_own_context_key(): void
    {
        $disk = $this->privateDisk();
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic MGI', 'active' => true, 'base_currency' => 'IDR']);
        $user = User::factory()->create();
        $journal = (new \App\Models\JournalHeader())->forceCreate(['transaction_date' => '2026-01-01']);
        $bill = DB::table('purchase_bills')->insertGetId([
            'bill_number' => 'SYNTHETIC-INSPECT', 'bill_date' => '2026-01-01', 'vendor_name' => 'Synthetic',
            'credit_account' => '211001', 'grand_total' => '100.00', 'journal_id' => $journal->getKey(),
        ]);
        $reconciliation = DB::table('ppl_ap_reconciliations')->insertGetId([
            'company_id' => $company->id, 'purchase_bill_id' => $bill, 'journal_id' => $journal->getKey(),
            'amount' => '100.00', 'reason' => 'Synthetic', 'evidence' => 'Synthetic', 'proposed_by' => $user->id,
        ]);
        $id = (string) Str::uuid();
        $key = "payment-plan/{$company->id}/reconciliation/{$reconciliation}/{$id}";
        $disk->put($key, 'reconciliation evidence');
        DB::table('ppl_attachments')->insert([
            'id' => $id, 'reconciliation_id' => $reconciliation, 'company_id' => $company->id,
            'uploader_id' => $user->id, 'kind' => 'reconciliation', 'name' => 'synthetic.pdf',
            'mime' => 'application/pdf', 'sha256' => hash('sha256', 'reconciliation evidence'), 'storage_key' => $key,
        ]);

        $this->artisan('ppl:attachments-inspect')
            ->expectsOutput($this->summary(valid: 1))
            ->assertSuccessful();
        $this->assertDatabaseCount('ppl_attachment_audits', 0);
    }

    public function test_missing_private_root_is_not_a_pass_even_when_no_rows_exist(): void
    {
        config(['filesystems.disks.local.root' => sys_get_temp_dir().'/missing-ppl-root-'.Str::uuid()]);

        $this->artisan('ppl:attachments-inspect')
            ->expectsOutput('Private local disk root is unavailable.')
            ->doesntExpectOutputToContain('"counts"')
            ->assertExitCode(1);
    }

    private function privateDisk(): \Illuminate\Filesystem\FilesystemAdapter
    {
        $disk = Storage::fake('local');
        config(['filesystems.disks.local.root' => $disk->path('')]);

        return $disk;
    }

    private function attachment(string $contents, ?string $key = null, bool $archived = false): string
    {
        $company = Company::firstOrCreate(['code' => 'MGI'], ['name' => 'Synthetic MGI', 'active' => true, 'base_currency' => 'IDR']);
        $user = User::factory()->create();
        $request = (string) Str::uuid();
        $id = (string) Str::uuid();
        $key ??= "payment-plan/{$company->id}/{$request}/{$id}";
        DB::table('ppl_requests')->insert([
            'id' => $request, 'company_id' => $company->id, 'owner_user_id' => $user->id,
            'doc' => $request, 'status' => 'draft', 'payload' => '{}',
        ]);
        DB::table('ppl_attachments')->insert([
            'id' => $id, 'request_id' => $request, 'company_id' => $company->id, 'uploader_id' => $user->id,
            'kind' => 'request', 'name' => 'synthetic.pdf', 'mime' => 'application/pdf',
            'sha256' => hash('sha256', $contents), 'storage_key' => $key, 'archived_at' => $archived ? now() : null,
        ]);

        return $key;
    }

    private function summary(int $valid = 0, int $missing = 0, int $mismatch = 0, int $invalid = 0, int $orphan = 0): string
    {
        return json_encode(['counts' => [
            'valid' => $valid, 'missing_file' => $missing, 'hash_mismatch' => $mismatch,
            'invalid_key' => $invalid, 'unreadable_file' => 0, 'file_without_row' => $orphan,
        ], 'read_only' => true], JSON_THROW_ON_ERROR);
    }
}