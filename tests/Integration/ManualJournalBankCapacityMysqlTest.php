<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Modules\PaymentPlan\Services\ErpBankBalance;
use Symfony\Component\Process\Process;

class ManualJournalBankCapacityMysqlTest extends \Tests\Feature\ManualJournalBankCapacityTest
{
    private ?string $isolatedDatabase = null;

    public function test_maker_commit_first_rejects_waiting_manual_journal(): void
    {
        $this->overlap('maker');
    }

    public function test_manual_commit_first_rejects_waiting_maker_without_partial_ap_allocation(): void
    {
        $this->overlap('manual');
    }

    public function test_maker_first_rejects_waiting_receipt_delete(): void
    {
        $this->overlap('maker', 'delete');
    }

    public function test_receipt_delete_first_rejects_waiting_maker_without_partial_allocation(): void
    {
        $this->overlap('delete', 'delete');
    }

    public function test_maker_first_rejects_waiting_receipt_edit(): void
    {
        $this->overlap('maker', 'edit');
    }

    public function test_receipt_edit_first_rejects_waiting_maker(): void
    {
        $this->overlap('edit', 'edit');
    }

    public function test_maker_first_rejects_waiting_bulk_delete(): void
    {
        $this->overlap('maker', 'bulk');
    }

    public function test_bulk_delete_first_rejects_waiting_maker(): void
    {
        $this->overlap('bulk', 'bulk');
    }

    public static function importOrders(): array
    {
        return [['maker', 'csv'], ['csv', 'csv'], ['maker', 'xlsx'], ['xlsx', 'xlsx']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('importOrders')]
    public function test_import_maker_overlap(string $first, string $format): void
    {
        $this->overlap($first, $format);
    }

    public function test_maker_first_rejects_opening_replacement(): void
    {
        $this->overlap('maker', 'opening');
    }

    public function test_opening_first_rejects_maker(): void
    {
        $this->overlap('opening', 'opening');
    }

    public static function openingVariantOrders(): array
    {
        return [['maker', 'opening-csv'], ['opening-csv', 'opening-csv'],
            ['maker', 'opening-fx'], ['opening-fx', 'opening-fx']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('openingVariantOrders')]
    public function test_opening_variant_maker_overlap(string $first, string $variant): void
    {
        $this->overlap($first, $variant);
    }

    private function overlap(string $first, string $journalAction = 'manual'): void
    {
        $flags = ['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.erp_bank_balance' => true, 'payment_plan_v33.ap_allocations' => true,
            'payment_plan_v33.reservations' => true, 'platform.posting_intents_enabled' => true,
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false];
        config($flags);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        $users = [];
        foreach (['req' => 'REQUESTER', 'dir' => 'DIRECTOR', 'fs' => 'FINANCE_STAFF', 'mk' => 'MAKER'] as $key => $role) {
            $users[$key] = User::factory()->create(['role' => 'FINANCE']);
            $users[$key]->companies()->attach($company->id, ['is_default' => true]);
            $r = Role::create(['code' => 'PPL_'.$role, 'name' => $role]);
            $users[$key]->platformRoles()->attach($r->id, ['company_id' => $company->id]);
        }
        foreach (['111003' => 'DEBET', '310001' => 'KREDIT', '610001' => 'DEBET', '211001' => 'KREDIT'] as $code => $side) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic', 'normal_balance' => $side, 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
        }
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => '111003', 'active' => true]);
        $settings = ['version' => 1, 'erp_cutoff' => '2026-01-01', 'accounts' => [['id' => 'idr3', 'name' => 'Synthetic',
            'number' => 'TEST', 'currency' => 'IDR', 'payment' => true, 'opening' => '500.00', 'asOf' => '2026-01-01']], 'receipts' => []];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings)]);
        $opening = $journalAction === 'manual' ? '500.00' : '300.00';
        \App\Support\PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '111003', 'position' => 'DEBET', 'amount' => $opening], ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => $opening]]);
        $openingAction = str_starts_with($journalAction, 'opening');
        $receipt = ! $openingAction && !in_array($journalAction, ['manual', 'bulk'], true) ? \App\Support\PostingService::post([
            'transaction_date' => '2026-01-03', 'source_doc_no' => 'RACE-RECEIPT', 'journal_type' => 'MANUAL',
        ], [
            ['account_code' => '111003', 'position' => 'DEBET', 'amount' => '200.00'],
            ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '200.00'],
        ]) : null;
        $bulkIds = [];
        if ($journalAction === 'bulk') {
            foreach (['2026-01-03', '2026-01-04'] as $date) {
                $bulkIds[] = \App\Support\PostingService::post(['transaction_date' => $date, 'journal_type' => 'MANUAL'], [
                    ['account_code' => '111003', 'position' => 'DEBET', 'amount' => '100.00'],
                    ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '100.00'],
                ])->getKey();
            }
        }
        $openingDate = '2026-01-03';
        if ($openingAction) {
            $this->actingAs($users['fs'])->post(route('account.store_opening_balance'), [
                'transaction_date' => $openingDate, 'balances' => ['111003' => '200.00', '310001' => '200.00'],
            ])->assertSessionHas('success');
        }
        if ($journalAction === 'opening-fx') {
            config(['platform.multicurrency_enabled' => true]);
            $flags['platform.multicurrency_enabled'] = true;
            $this->seed(\Database\Seeders\CurrencySeeder::class);
            DB::table('accounts')->insert(['account_code' => '111004', 'account_name' => 'Synthetic USD',
                'currency_code' => 'USD', 'normal_balance' => 'DEBET', 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
            app(\App\Modules\Currency\Services\ExchangeRateService::class)->save([
                'currency_code' => 'USD', 'rate' => '50.00', 'rate_date' => $openingDate, 'type' => 'SPOT', 'source' => 'BI',
            ], null, $users['fs']->id);
        }
        $journal = \App\Support\PostingService::post(['transaction_date' => '2026-01-02'], [
            ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '600.00'], ['account_code' => '211001', 'position' => 'KREDIT', 'amount' => '600.00']]);
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number' => 'RACE-AP', 'bill_date' => '2026-01-02', 'vendor_name' => 'Synthetic',
            'credit_account' => '211001', 'grand_total' => '600.00', 'journal_id' => $journal->getKey(), 'payment_status' => 'UNPAID']);
        $id = (string) \Illuminate\Support\Str::uuid();
        $this->actingAs($users['req'])->postJson(route('payment-plan-v33.request'), ['id' => $id, 'payee' => 'Synthetic',
            'subject' => ['Test', 'Test', 'Test'], 'purpose' => ['Test', 'Test', 'Test'], 'amount' => '400.00',
            'recipientBank' => 'TEST', 'recipientAccount' => 'TEST', 'due' => now()->addDay()->toDateString(), 'purchaseBillId' => $bill])->assertCreated();
        $version = 1;
        foreach ([['submit', 'req', []], ['approve_request', 'dir', ['checked' => true]]] as [$action, $who, $input]) {
            $this->actingAs($users[$who])->postJson(route('payment-plan-v33.action'), ['id' => $id, 'version' => $version++, 'mutationId' => $id.$action,
                'action' => $action, 'input' => $input])->assertOk();
        }
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->actingAs($users['fs'])->post(route('payment-plan-v33.attachment'), ['id' => $id, 'version' => $version++, 'kind' => 'settlement',
            'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('settlement.pdf', "%PDF-1.4\nSynthetic test only")])->assertOk();
        foreach ([['prepare_settlement', 'fs', ['checked' => true, 'base' => '400.00', 'coa' => '211001', 'sourceId' => 'idr3']],
            ['approve_settlement', 'dir', ['checked' => true]]] as [$action, $who, $input]) {
            $this->actingAs($users[$who])->postJson(route('payment-plan-v33.action'), ['id' => $id, 'version' => $version++, 'mutationId' => $id.$action,
                'action' => $action, 'input' => $input])->assertOk();
        }
        $makerBefore = DB::table('ppl_requests')->where('id', $id)->first();
        $eventsBefore = DB::table('ppl_events')->count();
        $intentsBefore = DB::table('accounting_posting_intents')->count();
        $release = tempnam(sys_get_temp_dir(), 'manual_maker_');
        unlink($release);
        $workers = [];
        foreach ([$first, $first === 'maker' ? $journalAction : 'maker'] as $index => $action) {
            $payload = $action === 'maker' ? ['id' => $id, 'version' => $version, 'mutationId' => 'race-maker', 'action' => 'mark_maker', 'input' => ['reference' => 'RACE']]
                : ['transaction_date' => '2026-01-03', 'source_doc_no' => 'RACE-MANUAL', 'description' => 'Manual race outflow', 'details' => [
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'], ['account_code' => '111003', 'position' => 'KREDIT', 'amount' => '200.00']]];
            if ($action === 'delete') $payload = ['journal_id' => $receipt->getKey()];
            if ($action === 'edit') $payload = ['journal_id' => $receipt->getKey(), 'transaction_date' => '2026-01-03',
                'description' => 'Reclassify receipt', 'details' => [
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'],
                    ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '200.00'],
                ]];
            if ($action === 'bulk') $payload = ['ids' => $bulkIds];
            if ($action === 'opening') $payload = ['transaction_date' => $openingDate, 'balances' => ['610001' => '200.00', '310001' => '200.00']];
            if ($action === 'opening-csv') $payload = ['transaction_date' => $openingDate];
            if ($action === 'opening-fx') $payload = ['transaction_date' => $openingDate, 'balances' => ['111004' => '4.00', '310001' => '200.00']];
            $workers[] = new Process([PHP_BINARY, base_path('tests/Support/manual-journal-maker-worker.php')], base_path(), ['MANUAL_MAKER_FIXTURE' => json_encode([
                'connection' => config('database.connections.writer_test'), 'flags' => $flags, 'action' => $action, 'actor' => $users[$action === 'maker' ? 'mk' : 'fs']->id,
                'payload' => $payload, 'hold' => $index === 0, 'release' => $release], JSON_THROW_ON_ERROR)], null, 110);
        }
        try {
            $workers[0]->start();
            $this->waitForMarker($workers[0], 'UNCOMMITTED');
            $workers[1]->start();
            $this->waitForMarker($workers[1], 'READY:');
            preg_match('/READY:(\d+)/', $workers[0]->getOutput(), $holder);
            preg_match('/READY:(\d+)/', $workers[1]->getOutput(), $waiter);
            $observer = DB::connection('writer_admin');
            $observerId = (int) $observer->selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertCount(3, array_unique([(int) $holder[1], (int) $waiter[1], $observerId]));
            $wait = null;
            $deadline = microtime(true) + 35;
            while ($workers[1]->isRunning() && microtime(true) < $deadline) {
                $wait = $observer->selectOne('SELECT w.requesting_trx_id, w.blocking_trx_id, l.lock_table, l.lock_index, l.lock_data
                    FROM information_schema.innodb_lock_waits w JOIN information_schema.innodb_trx t ON t.trx_id = w.requesting_trx_id
                    JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
                    JOIN information_schema.innodb_locks l ON l.lock_id = w.requested_lock_id
                    WHERE t.trx_mysql_thread_id = ? AND b.trx_mysql_thread_id = ?', [(int) $waiter[1], (int) $holder[1]]);
                if ($wait) break;
                usleep(250000);
            }
            $this->assertNotNull($wait, $workers[0]->getOutput().$workers[1]->getOutput().$workers[1]->getErrorOutput()
                .json_encode($observer->select('SHOW FULL PROCESSLIST'))
                .json_encode($observer->select('SELECT * FROM information_schema.innodb_lock_waits')));
            $this->assertStringContainsString('ppl_financial_contexts', $wait->lock_table);
            $this->assertSame('PRIMARY', $wait->lock_index);
            $this->assertSame((string) $company->id, $wait->lock_data);
            touch($release);
            foreach ($workers as $worker) {
                $worker->wait();
                $this->assertSame(0, $worker->getExitCode(), $worker->getOutput().$worker->getErrorOutput());
            }
            $results = [];
            foreach ($workers as $worker) {
                preg_match('/RESULT:(.*)/', $worker->getOutput(), $m);
                $results[] = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
            }
            if ($first === 'maker') {
                $this->assertSame(200, $results[0]['status']);
                $this->assertNotEmpty($results[1]['error']);
                $this->assertStringContainsString('funds_insufficient', $results[1]['error']);
            } else {
                $this->assertNotEmpty($results[0]['success']);
                $this->assertSame(422, $results[1]['status']);
                $this->assertSame('funds_insufficient', $results[1]['body']['error']);
            }
            // New connection after both commits: no stale REPEATABLE READ snapshot.
            config(['database.connections.writer_final' => config('database.connections.writer_test')]);
            DB::setDefaultConnection('writer_final');
            $finalId = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotContains($finalId, [(int) $holder[1], (int) $waiter[1], $observerId]);
            $balance = app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings);
            $this->assertSame($first === 'maker' ? '500.00' : '300.00', $balance['actual']);
            $this->assertSame($first === 'maker' ? '400.00' : '0.00', $balance['reserved']);
            $this->assertSame($first === 'maker' ? '100.00' : '300.00', $balance['available']);
            $journalCount = match ($journalAction) {
                'delete' => $first === 'maker' ? 3 : 2,
                'edit', 'csv', 'xlsx', 'opening', 'opening-csv', 'opening-fx' => 3,
                'bulk' => $first === 'maker' ? 4 : 2,
                default => $first === 'maker' ? 2 : 3,
            };
            $this->assertDatabaseCount('journal_headers', $journalCount);
            $this->assertDatabaseCount('journal_details', $journalCount * 2);
            $this->assertDatabaseCount('accounting_posting_intents', $intentsBefore);
            if ($bulkIds) {
                $this->assertSame($first === 'maker' ? 2 : 0, DB::table('journal_headers')->whereIn('journal_id', $bulkIds)->count());
                $this->assertSame($first === 'maker' ? 4 : 0, DB::table('journal_details')->whereIn('journal_id', $bulkIds)->count());
            } elseif (in_array($journalAction, ['csv', 'xlsx'], true)) {
                $this->assertSame($first === 'maker' ? 0 : 1, DB::table('journal_headers')->where('journal_no', 'RACE-RECEIPT')->where('journal_type', 'IMPORT')->count());
                if ($first === 'maker') $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->getKey(), 'account_code' => '111003', 'position' => 'DEBET', 'amount' => '200.00']);
            } elseif ($openingAction) {
                $this->assertDatabaseHas('journal_details', ['journal_id' => 'JRN-SA-'.$openingDate,
                    'account_code' => $first === 'maker' ? '111003' : ($journalAction === 'opening-fx' ? '111004' : '610001'), 'position' => 'DEBET', 'amount' => '200.00']);
                if ($journalAction === 'opening-fx') {
                    $this->assertSame($first !== 'maker', \App\Modules\Currency\Models\ExchangeRate::query()->whereNotNull('first_used_at')->exists());
                    if ($first !== 'maker') $this->assertDatabaseHas('journal_details', ['journal_id' => 'JRN-SA-'.$openingDate,
                        'account_code' => '111004', 'currency_code' => 'USD', 'amount_txn' => '4.00', 'amount' => '200.00']);
                }
            } elseif ($journalAction === 'edit') {
                $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->getKey(),
                    'account_code' => $first === 'maker' ? '111003' : '610001', 'position' => 'DEBET', 'amount' => '200.00']);
            } elseif ($receipt) {
                $this->assertSame($first === 'maker', DB::table('journal_headers')->where('journal_id', $receipt->getKey())->exists());
                $this->assertSame($first === 'maker' ? 2 : 0, DB::table('journal_details')->where('journal_id', $receipt->getKey())->count());
            } else {
                $this->assertSame($first === 'maker' ? 0 : 1, DB::table('journal_headers')->where('source_doc_no', 'RACE-MANUAL')->count());
            }
            $this->assertDatabaseCount('ppl_reservations', $first === 'maker' ? 1 : 0);
            $this->assertDatabaseCount('ppl_ap_allocations', $first === 'maker' ? 1 : 0);
            $this->assertDatabaseCount('ppl_payment_events', 0);
            $this->assertSame($eventsBefore + ($first === 'maker' ? 1 : 0), DB::table('ppl_events')->count());
            $row = DB::table('ppl_requests')->where('id', $id)->first();
            if ($first !== 'maker') $this->assertEquals($makerBefore, $row);
            else {
                $this->assertSame('bank_prepared', $row->status);
                $this->assertSame($version + 1, $row->version);
                $this->assertDatabaseHas('ppl_ap_allocations', ['request_id' => $id, 'amount' => '400.00', 'status' => 'active']);
                $this->assertDatabaseHas('ppl_reservations', ['request_id' => $id, 'amount' => '400.00', 'status' => 'active']);
            }
            if ($path = getenv('MANUAL_MAKER_RACE_EVIDENCE')) file_put_contents($path, json_encode([
                'first' => $first, 'journal_action' => $journalAction, 'holder' => (int) $holder[1], 'waiter' => (int) $waiter[1], 'observer' => $observerId, 'final_reader' => $finalId,
                'wait' => $wait, 'results' => $results, 'balance' => $balance, 'journals' => DB::table('journal_headers')->get(),
                'reservations' => DB::table('ppl_reservations')->get(), 'allocations' => DB::table('ppl_ap_allocations')->get(),
                'maker_status' => $row->status, 'maker_version' => $row->version], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
        } finally {
            touch($release);
            foreach ($workers as $worker) if ($worker->isRunning()) $worker->stop();
            @unlink($release);
            DB::setDefaultConnection('writer_test');
        }
    }

    private function waitForMarker(Process $worker, string $marker): void
    {
        $deadline = microtime(true) + 20;
        while ($worker->isRunning() && !str_contains($worker->getOutput(), $marker) && microtime(true) < $deadline) usleep(20000);
        $this->assertStringContainsString($marker, $worker->getOutput(), $worker->getErrorOutput());
    }

    public function refreshDatabase(): void
    {
        if (getenv('AP_MYSQL_TESTS') !== '1') $this->markTestSkipped('Opt-in isolated MariaDB test.');
        $this->isolatedDatabase = 'audit_manual_writer_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => 'root', 'password' => '', 'database' => 'information_schema', 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.writer_admin' => $config]);
        DB::connection('writer_admin')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'`');
        $config['database'] = $this->isolatedDatabase;
        config(['database.connections.writer_test' => $config, 'database.default' => 'writer_test']);
        DB::setDefaultConnection('writer_test');
        $this->assertSame(0, Artisan::call('migrate', ['--database' => 'writer_test', '--force' => true]), Artisan::output());
    }

    protected function tearDown(): void
    {
        try {
            if ($this->isolatedDatabase && preg_match('/\Aaudit_manual_writer_[a-f0-9]{16}\z/', $this->isolatedDatabase)) {
                while (DB::connection('writer_test')->transactionLevel() > 0) DB::connection('writer_test')->rollBack();
                DB::purge('writer_test');
                DB::purge('writer_final');
                DB::connection('writer_admin')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
            }
        } finally { parent::tearDown(); }
    }
}
