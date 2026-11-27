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

    public function test_maker_first_rejects_waiting_legacy_payment_post(): void
    {
        $this->overlap('maker', 'legacy');
    }

    public function test_legacy_payment_post_first_rejects_waiting_maker(): void
    {
        $this->overlap('legacy', 'legacy');
    }

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

    public function test_maker_first_rejects_waiting_fx(): void
    {
        $this->overlap('maker', 'fx');
    }

    public function test_fx_first_rejects_waiting_maker(): void
    {
        $this->overlap('fx', 'fx');
    }

    public function test_warehouse_inbound_race_maker_first(): void
    {
        $this->overlap('maker', 'inbound');
    }

    public function test_warehouse_inbound_race_receipt_first(): void
    {
        $this->overlap('inbound', 'inbound');
    }

    private function overlap(string $first, string $journalAction = 'manual'): void
    {
        $legacy = $journalAction === 'legacy';
        $inbound = $journalAction === 'inbound';
        $flags = ['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.erp_bank_balance' => true, 'payment_plan_v33.ap_allocations' => true,
            'payment_plan_v33.reservations' => true, 'platform.posting_intents_enabled' => true,
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false];
        if ($inbound) $flags += ['coa.persediaan' => '11200', 'payment_plan_v33.primary_navigation' => true,
            'payment_plan_v33.posting' => false];
        config($flags);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        $users = [];
        foreach (['req' => 'REQUESTER', 'dir' => 'DIRECTOR', 'fs' => 'FINANCE_STAFF', 'mk' => 'MAKER'] as $key => $role) {
            $users[$key] = User::factory()->create(['role' => 'FINANCE']);
            $users[$key]->companies()->attach($company->id, ['is_default' => true]);
            $r = Role::create(['code' => 'PPL_'.$role, 'name' => $role]);
            $users[$key]->platformRoles()->attach($r->id, ['company_id' => $company->id]);
        }
        if ($legacy) {
            $flags['platform.payment_post_user_ids'] = [$users['fs']->id];
            config($flags);
        }
        $bankCoa = $legacy ? '111101' : '111003';
        foreach ([$bankCoa => 'DEBET', '310001' => 'KREDIT', '610001' => 'DEBET', '211001' => 'KREDIT'] as $code => $side) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic', 'normal_balance' => $side,
                'coa_type' => $legacy && (string) $code === $bankCoa ? 'Cash & Bank' : 'Other', 'report_pos' => 'NERACA']);
        }
        if ($journalAction === 'fx') {
            $this->seedFx($users['fs']->id);
            $flags['platform.multicurrency_enabled'] = true;
        }
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => $bankCoa, 'active' => true]);
        $settings = ['version' => 1, 'erp_cutoff' => '2026-01-01', 'accounts' => [['id' => 'idr3', 'name' => 'Synthetic',
            'number' => 'TEST', 'currency' => 'IDR', 'payment' => true, 'opening' => '500.00', 'asOf' => '2026-01-01']], 'receipts' => []];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings)]);
        $opening = in_array($journalAction, ['manual', 'fx', 'legacy', 'inbound'], true) ? '500.00' : '300.00';
        \App\Support\PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => $bankCoa, 'position' => 'DEBET', 'amount' => $opening], ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => $opening]]);
        $openingAction = str_starts_with($journalAction, 'opening');
        $receipt = ! $openingAction && !in_array($journalAction, ['manual', 'bulk', 'fx', 'legacy', 'inbound'], true) ? \App\Support\PostingService::post([
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
        $payment = $proofRoot = null;
        if ($legacy) {
            $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'M1', 'nama_divisi' => 'Synthetic', 'status_aktif' => 1]);
            $disk = \Illuminate\Support\Facades\Storage::fake('public');
            $disk->put('proof/f03.pdf', 'Synthetic proof');
            $proofRoot = $disk->path('');
            $this->actingAs($users['req']);
            $payment = \App\Models\PaymentPlan::create(['no_transaksi' => 'F03-LEGACY-RACE', 'id_divisi' => $division,
                'tgl_pengajuan' => '2026-01-02', 'tgl_transaksi' => '2026-01-02', 'jenis_transaksi' => $bankCoa,
                'kategori_payment' => 'OPERASIONAL', 'vendor_toko' => 'Synthetic', 'penerima_pj' => 'Synthetic',
                'keterangan' => 'Synthetic direct service', 'id_akun' => '610001', 'nominal' => '100.01',
                'nominal_aktual' => '100.01', 'status_payment' => 'PENGAJUAN']);
            $payment->details()->create(['keterangan' => 'Synthetic', 'qty' => 1, 'nominal' => '100.01',
                'nominal_aktual' => '100.01', 'bukti_file' => 'proof/f03.pdf']);
            $this->actingAs($users['fs']);
            \App\Support\PaymentMakerChecker::approve($payment->refresh());
            $payment->update(['status_payment' => 'PAID']);
            \App\Support\PaymentPlanWorkflow::realized($payment->refresh());
            $paymentBefore = $payment->refresh()->getAttributes();
            $this->assertSame('500.00', app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings)['available']);
            $this->assertDatabaseCount('ppl_reservations', 0);
            $this->assertDatabaseCount('ppl_ap_allocations', 0);
        }
        if ($inbound) {
            DB::table('accounts')->insert(['account_code' => '11200', 'account_name' => 'Synthetic inventory',
                'normal_balance' => 'DEBET', 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
            $product = \App\Models\Product::create(['sku' => 'F03-INBOUND-RACE', 'name' => 'Synthetic',
                'stock_quantity' => 0, 'average_cost' => 0]);
            $form = $this->actingAs($users['fs'])->get(route('warehouse.inbound.create', ['tab' => 'transfer_masuk']))
                ->assertOk()->assertSee('value="'.$bankCoa.'"', false);
            $this->assertSame(1, preg_match('/name="evidence_number"[^>]+value="(IN-[^"]+)"/', $form->getContent(), $match));
            $inboundRef = $match[1];
        }
        $release = tempnam(sys_get_temp_dir(), 'manual_maker_');
        unlink($release);
        $workers = [];
        foreach ([$first, $first === 'maker' ? $journalAction : 'maker'] as $index => $action) {
            $payload = $action === 'maker' ? ['id' => $id, 'version' => $version, 'mutationId' => 'race-maker', 'action' => 'mark_maker', 'input' => ['reference' => 'RACE']]
                : ['transaction_date' => '2026-01-03', 'source_doc_no' => 'RACE-MANUAL', 'description' => 'Manual race outflow', 'details' => [
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'], ['account_code' => '111003', 'position' => 'KREDIT', 'amount' => '200.00']]];
            if ($action === 'delete') $payload = ['journal_id' => $receipt->getKey()];
            if ($action === 'legacy') $payload = ['selected_ids' => $payment->no_transaksi];
            if ($action === 'inbound') $payload = ['transaction_date' => '2026-01-03', 'evidence_number' => $inboundRef,
                'description' => 'Synthetic inbound race', 'offset_account' => $bankCoa,
                'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => '100.01']]];
            if ($action === 'edit') $payload = ['journal_id' => $receipt->getKey(), 'transaction_date' => '2026-01-03',
                'description' => 'Reclassify receipt', 'details' => [
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'],
                    ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '200.00'],
                ]];
            if ($action === 'bulk') $payload = ['ids' => $bulkIds];
            if ($action === 'opening') $payload = ['transaction_date' => $openingDate, 'balances' => ['610001' => '200.00', '310001' => '200.00']];
            if ($action === 'opening-csv') $payload = ['transaction_date' => $openingDate];
            if ($action === 'opening-fx') $payload = ['transaction_date' => $openingDate, 'balances' => ['111004' => '4.00', '310001' => '200.00']];
            if ($action === 'fx') {
                $payload['currency_code'] = 'USD';
                $payload['details'] = [
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount_txn' => '2.00'],
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount_txn' => '2.00'],
                    ['account_code' => '111003', 'position' => 'KREDIT', 'amount_txn' => '4.00'],
                ];
            }
            $workers[] = new Process([PHP_BINARY, base_path('tests/Support/manual-journal-maker-worker.php')], base_path(), ['MANUAL_MAKER_FIXTURE' => json_encode([
                'connection' => config('database.connections.writer_test'), 'flags' => $flags, 'action' => $action, 'actor' => $users[$action === 'maker' ? 'mk' : 'fs']->id,
                'payload' => $payload, 'hold' => $index === 0, 'release' => $release, 'proof_root' => $proofRoot], JSON_THROW_ON_ERROR)], null, 110);
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
            $this->assertStringContainsString("\nCOMMITTED", $workers[0]->getOutput());
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
            $this->assertNotContains($finalId, [(int) $holder[1], (int) $waiter[1], $observerId,
                (int) DB::connection('writer_test')->selectOne('SELECT CONNECTION_ID() AS id')->id]);
            $balance = app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings);
            $afterOutflow = $legacy || $inbound ? '399.99' : '300.00';
            $this->assertSame($first === 'maker' ? '500.00' : $afterOutflow, $balance['actual']);
            $this->assertSame($first === 'maker' ? '400.00' : '0.00', $balance['reserved']);
            $this->assertSame($first === 'maker' ? '100.00' : $afterOutflow, $balance['available']);
            $journalCount = match ($journalAction) {
                'delete' => $first === 'maker' ? 3 : 2,
                'edit', 'csv', 'xlsx', 'opening', 'opening-csv', 'opening-fx' => 3,
                'bulk' => $first === 'maker' ? 4 : 2,
                default => $first === 'maker' ? 2 : 3,
            };
            $this->assertDatabaseCount('journal_headers', $journalCount);
            $this->assertDatabaseCount('journal_details', $journalCount * 2 + ($first === 'fx' ? 1 : 0));
            if ($first === 'fx') $this->assertDatabaseHas('journal_details', ['account_code' => '111003', 'position' => 'KREDIT', 'amount' => '200.00']);
            $this->assertDatabaseCount('accounting_posting_intents', $intentsBefore);
            if ($legacy) {
                $journalId = \App\Models\JournalHeader::idForPaymentPlan($payment->no_transaksi);
                $committedPayment = $payment->fresh();
                $this->assertSame($first === 'maker' ? 'PAID' : 'POSTED', $committedPayment->status_payment);
                $this->assertSame($first === 'maker' ? 0 : 1, DB::table('journal_headers')->where('journal_id', $journalId)->count());
                if ($first === 'maker') {
                    $this->assertSame($paymentBefore, $committedPayment->getAttributes());
                    $this->assertSame(0, DB::table('journal_details')->where('journal_id', $journalId)->count());
                } else {
                    $this->assertSame(2, DB::table('journal_details')->where('journal_id', $journalId)->count());
                    $this->assertDatabaseHas('journal_details', ['journal_id' => $journalId, 'account_code' => $bankCoa, 'position' => 'KREDIT', 'amount' => '100.01']);
                    $this->assertDatabaseHas('journal_details', ['journal_id' => $journalId, 'account_code' => '610001', 'position' => 'DEBET', 'amount' => '100.01']);
                }
            } elseif ($bulkIds) {
                $this->assertSame($first === 'maker' ? 2 : 0, DB::table('journal_headers')->whereIn('journal_id', $bulkIds)->count());
                $this->assertSame($first === 'maker' ? 4 : 0, DB::table('journal_details')->whereIn('journal_id', $bulkIds)->count());
            } elseif (in_array($journalAction, ['csv', 'xlsx'], true)) {
                $this->assertSame($first === 'maker' ? 0 : 1, DB::table('journal_headers')->where('journal_no', 'RACE-RECEIPT')->where('journal_type', 'IMPORT')->count());
                if ($first === 'maker') $this->assertDatabaseHas('journal_details', ['journal_id' => $receipt->getKey(), 'account_code' => '111003', 'position' => 'DEBET', 'amount' => '200.00']);
            } elseif ($inbound) {
                $committed = $first !== 'maker';
                $stock = DB::table('products')->where('id', $product->id)->first(['stock_quantity', 'average_cost']);
                $ledger = DB::table('inventory_ledgers')->where('evidence_number', $inboundRef)->get([
                    'type', 'qty', 'unit_cost', 'total_cost', 'running_qty', 'running_value', 'moving_average_cost',
                ]);
                $inboundHeaders = DB::table('journal_headers')->where('source_doc_no', $inboundRef)->get();
                $inboundLines = DB::table('journal_details')->whereIn('journal_id', $inboundHeaders->pluck('journal_id'))
                    ->orderBy('position')->get(['account_code', 'position', 'amount']);
                $this->assertEquals($committed ? 1 : 0, $stock->stock_quantity);
                $this->assertEquals($committed ? '100.01' : '0.00', $stock->average_cost);
                $this->assertCount($committed ? 1 : 0, $ledger);
                $this->assertCount($committed ? 1 : 0, $inboundHeaders);
                $this->assertEquals($committed ? [['11200', 'DEBET', '100.01'], [$bankCoa, 'KREDIT', '100.01']] : [],
                    $inboundLines->map(fn ($line) => array_values((array) $line))->all());
                if ($committed) $this->assertEquals(['IN', 1, '100.01', '100.01', 1, '100.01', '100.01'], array_values((array) $ledger[0]));
            } elseif ($journalAction === 'bulk') {
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
                'maker_status' => $row->status, 'maker_version' => $row->version,
                'legacy_status' => $committedPayment->status_payment ?? null,
                'legacy_lines' => $legacy ? DB::table('journal_details')->where('journal_id', $journalId)->get() : null,
                'inbound' => $inbound ? compact('inboundRef', 'stock', 'ledger', 'inboundHeaders', 'inboundLines') : null], JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
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
        if (str_contains($this->name(), 'legacy_payment_post') && getenv('MGI_TEST_PORT') !== '33391') {
            $this->markTestSkipped('Legacy overlap requires the dedicated disposable port.');
        }
        $inbound = str_contains($this->name(), 'warehouse_inbound_race');
        if ($inbound && getenv('MGI_TEST_PORT') !== '33394') {
            $this->markTestSkipped('Inbound overlap requires the dedicated disposable port.');
        }
        $this->isolatedDatabase = 'audit_manual_writer_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => 'root', 'password' => '', 'database' => 'information_schema', 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.writer_admin' => $config]);
        if ($inbound) {
            $identity = DB::connection('writer_admin')->selectOne('SELECT @@datadir AS datadir, VERSION() AS version');
            $this->assertSame('d:/erp-recovery/m1-f03-inbound-patch-20261127-db/data',
                strtolower(rtrim(preg_replace('~[\\\\/]+~', '/', $identity->datadir), '/')));
            $this->assertStringContainsString('MariaDB', $identity->version);
        }
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
