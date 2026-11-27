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
    use \Tests\Concerns\CashPurchaseBillCapacityScenario;

    private ?string $isolatedDatabase = null;

    #[\PHPUnit\Framework\Attributes\DataProvider('outboundRollbackCases')]
    public function test_outbound_rollback_keeps_manual_funding(bool $collision): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 12:00:00', 'Asia/Jakarta'));
        $this->overlap('maker', $collision ? 'outbound-rollback' : 'outbound-clean', true);
    }

    public static function outboundRollbackCases(): array
    {
        return ['shared reference fails closed' => [true], 'unambiguous rollback succeeds' => [false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('residualWriterCases')]
    public function test_residual_writer_committed(string $action, string $first, string $amount): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 12:00:00', 'Asia/Jakarta'));
        try {
            $this->overlap($first, $action, true, $amount);
        } finally {
            $this->travelBack();
        }
    }

    public static function residualWriterCases(): iterable
    {
        foreach (['bill-delete', 'ar-reverse'] as $action) {
            yield "$action maker first" => [$action, 'maker', '100.01'];
            yield "$action writer first" => [$action, $action, '100.01'];
            yield "$action exact capacity" => [$action, 'maker', '100.00'];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('futureWriterCases')]
    public function test_future_writer(string $action, bool $makerFirst): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 00:30:00', 'Asia/Jakarta'));
        try {
            $this->overlap($makerFirst ? 'maker' : $action, $action, true);
        } finally {
            $this->travelBack();
        }
    }

    public static function futureWriterCases(): iterable
    {
        foreach (['manual', 'edit', 'fx', 'csv', 'xlsx', 'cash_bill', 'opening-csv', 'legacy'] as $action) {
            foreach ([true, false] as $makerFirst) {
                yield $action.($makerFirst ? '-maker-first' : '-writer-first') => [$action, $makerFirst];
            }
        }
    }

    public function test_cash_bill_race_maker_first(): void
    {
        $this->overlap('maker', 'cash_bill');
    }

    public function test_cash_bill_race_bill_first(): void
    {
        $this->overlap('cash_bill', 'cash_bill');
    }

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

    public function test_future_inbound_maker_first(): void
    {
        $this->runFutureInbound('maker');
    }

    public function test_future_inbound_receipt_first(): void
    {
        $this->runFutureInbound('inbound');
    }

    private function runFutureInbound(string $first): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 00:30:00', 'Asia/Jakarta'));
        try {
            $this->overlap($first, 'inbound', true);
        } finally {
            $this->travelBack();
        }
    }

    private function overlap(string $first, string $journalAction = 'manual', bool $future = false, ?string $residualAmount = null): void
    {
        $legacy = $journalAction === 'legacy';
        $cashBill = $journalAction === 'cash_bill';
        $inbound = $journalAction === 'inbound';
        $flags = ['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
            'payment_plan_v33.erp_bank_balance' => true, 'payment_plan_v33.ap_allocations' => true,
            'payment_plan_v33.reservations' => true, 'platform.posting_intents_enabled' => true,
            'platform.legacy_sync_enabled' => false, 'customs.enabled' => false];
        if ($inbound || $future) $flags += ['coa.persediaan' => '11200', 'payment_plan_v33.primary_navigation' => true,
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
        $bankCoa = $legacy || $cashBill || $journalAction === 'ar-reverse' ? '111101' : '111003';
        foreach ([$bankCoa => 'DEBET', '310001' => 'KREDIT', '610001' => 'DEBET', '211001' => 'KREDIT'] as $code => $side) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic', 'normal_balance' => $side,
                'coa_type' => ($legacy || $journalAction === 'ar-reverse') && (string) $code === $bankCoa ? 'Cash & Bank' : 'Other', 'report_pos' => 'NERACA']);
        }
        if ($journalAction === 'fx') {
            $this->seedFx($users['fs']->id);
            if ($future) app(\App\Modules\Currency\Services\ExchangeRateService::class)->save([
                'currency_code' => 'USD', 'rate' => '100.01', 'rate_date' => '2026-11-27', 'type' => 'SPOT', 'source' => 'BI',
            ], null, $users['fs']->id);
            $flags['platform.multicurrency_enabled'] = true;
        }
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3', 'account_code' => $bankCoa, 'active' => true]);
        $settings = ['version' => 1, 'erp_cutoff' => '2026-01-01', 'accounts' => [['id' => 'idr3', 'name' => 'Synthetic',
            'number' => 'TEST', 'currency' => 'IDR', 'payment' => true, 'opening' => '500.00', 'asOf' => '2026-01-01']], 'receipts' => []];
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings)]);
        $opening = $future || in_array($journalAction, ['manual', 'fx', 'legacy', 'cash_bill', 'inbound'], true) ? '500.00' : '300.00';
        if ($residualAmount !== null) $opening = bcsub('500.00', $residualAmount, 2);
        if (in_array($journalAction, ['outbound-rollback', 'outbound-clean'], true)) $opening = '399.99';
        \App\Support\PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => $bankCoa, 'position' => 'DEBET', 'amount' => $opening], ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => $opening]]);
        $openingAction = !$future && str_starts_with($journalAction, 'opening');
        $receipt = !$future && ! $openingAction && !in_array($journalAction, ['manual', 'bulk', 'fx', 'legacy', 'cash_bill', 'inbound'], true) ? \App\Support\PostingService::post([
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
        if ($cashBill) {
            $product = \App\Models\Product::create(['sku' => 'F03-CASH-RACE', 'name' => 'Synthetic', 'stock_quantity' => 0, 'average_cost' => 0]);
        }
        if ($legacy) {
            $division = DB::table('master_divisi')->insertGetId(['kode_divisi' => 'M1', 'nama_divisi' => 'Synthetic', 'status_aktif' => 1]);
            $disk = \Illuminate\Support\Facades\Storage::fake('public');
            $disk->put('proof/f03.pdf', 'Synthetic proof');
            $proofRoot = $disk->path('');
            $this->actingAs($users['req']);
            $payment = \App\Models\PaymentPlan::create(['no_transaksi' => 'F03-LEGACY-RACE', 'id_divisi' => $division,
                'tgl_pengajuan' => '2026-01-02', 'tgl_transaksi' => $future ? '2026-11-28' : '2026-01-02', 'jenis_transaksi' => $bankCoa,
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
        if (in_array($journalAction, ['outbound-rollback', 'outbound-clean'], true)) {
            $this->observeOutboundRollback($company, $settings, $users, $id, $version, $bankCoa, $journalAction === 'outbound-rollback');
            return;
        }
        if ($future && $residualAmount === null) {
            if ($inbound) {
                $this->observeFutureInbound($first, $company, $settings, $users, $id, $version, $product, $inboundRef, $bankCoa);
            } else {
                $this->observeFutureWriter($first, $journalAction, $company, $settings, $users, $id, $version, $bankCoa, $payment);
            }

            return;
        }
        if ($residualAmount !== null) {
            $residual = $this->seedResidualWriter($journalAction, $users['fs'], $bankCoa, $residualAmount);
            $flags += ['platform.payment_reverse_user_ids' => [$users['fs']->id], 'coa.piutang_usaha' => '12001'];
            $sourceBefore = $this->residualSource($residual);
            $this->assertSame('500.00', app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings)['available']);
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
            if ($action === 'cash_bill') $payload = ['bill_date' => '2026-01-03', 'vendor_name' => 'F03 cash race',
                'credit_account' => $bankCoa, 'details' => [['account_code' => '610001', 'amount' => '100.01']],
                'items' => [['sku' => $product->sku, 'qty' => 1, 'unit_cost' => '100.01']]];
            if ($action === 'inbound') $payload = ['transaction_date' => '2026-01-03', 'evidence_number' => $inboundRef,
                'description' => 'Synthetic inbound race', 'offset_account' => $bankCoa,
                'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => '100.01']]];
            if ($action === 'edit') $payload = ['journal_id' => $receipt->getKey(), 'transaction_date' => '2026-01-03',
                'description' => 'Reclassify receipt', 'details' => [
                    ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '200.00'],
                    ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '200.00'],
                ]];
            if ($action === 'bulk') $payload = ['ids' => $bulkIds];
            if ($residualAmount !== null && $action !== 'maker') $payload = $residual;
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
                'payload' => $payload, 'hold' => $index === 0, 'release' => $release, 'proof_root' => $proofRoot,
                'clock' => $residualAmount !== null ? now()->toIso8601String() : null], JSON_THROW_ON_ERROR)], null, 110);
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
            if ($residualAmount !== null) {
                $this->assertResidualOutcome($first, $journalAction, $residualAmount, $residual, $sourceBefore,
                    $results, $wait, [(int) $holder[1], (int) $waiter[1], $observerId], $company, $settings, $id, $makerBefore, $bankCoa);
                return;
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
            $afterOutflow = $legacy || $cashBill || $inbound ? '399.99' : '300.00';
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
            if ($cashBill) {
                $count = $first === 'maker' ? 0 : 1;
                $this->assertDatabaseCount('purchase_bills', 1 + $count);
                $this->assertDatabaseCount('purchase_bill_details', $count);
                $this->assertDatabaseCount('inventory_ledgers', $count);
                $this->assertDatabaseCount('purchase_receipts', 0);
                $this->assertDatabaseCount('purchase_receipt_details', 0);
                $this->assertSame($count, (int) $product->fresh()->stock_quantity);
                if ($count) {
                    $committedBill = DB::table('purchase_bills')->where('vendor_name', 'F03 cash race')->sole();
                    $this->assertSame('PAID', $committedBill->payment_status);
                    $this->assertDatabaseHas('journal_details', ['journal_id' => $committedBill->journal_id,
                        'account_code' => $bankCoa, 'position' => 'KREDIT', 'amount' => '100.01']);
                }
            } elseif ($legacy) {
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

    private function observeFutureInbound(string $first, Company $company, array $settings, array $users, string $id,
        int $version, \App\Models\Product $product, string $inboundRef, string $bankCoa): void
    {
        $day = now('Asia/Jakarta')->toDateString();
        $next = now('Asia/Jakarta')->addDay()->toDateString();
        $this->assertSame('2026-11-27', $day);
        $this->assertSame('2026-11-28', $next);
        $writer = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $snapshots = [];
        $results = [];
        $snapshot = function (string $key) use (&$snapshots, $company, $settings, $writer, $id, $product, $inboundRef): void {
            $this->assertSame(0, DB::connection('writer_test')->transactionLevel());
            config(['database.connections.writer_final' => config('database.connections.writer_test')]);
            DB::purge('writer_final');
            DB::setDefaultConnection('writer_final');
            try {
                $reader = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                $this->assertNotContains($reader, [$writer, ...array_column($snapshots, 'reader')]);
                $journal = DB::table('journal_headers')->where('source_doc_no', $inboundRef)->first(['journal_id', 'transaction_date']);
                $snapshots[$key] = [
                    'day' => now('Asia/Jakarta')->toDateString(), 'reader' => $reader,
                    'balance' => app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings),
                    'hold' => DB::table('ppl_reservations')->where('request_id', $id)->get(['account_key', 'amount', 'status'])->all(),
                    'allocation' => DB::table('ppl_ap_allocations')->where('request_id', $id)->get(['amount', 'status'])->all(),
                    'maker_status' => DB::table('ppl_requests')->where('id', $id)->value('status'),
                    'journal' => $journal,
                    'lines' => $journal ? DB::table('journal_details')->where('journal_id', $journal->journal_id)
                        ->orderBy('position')->get(['account_code', 'position', 'amount'])->all() : [],
                    'stock' => DB::table('products')->where('id', $product->id)->first(['stock_quantity', 'average_cost']),
                    'ledger' => DB::table('inventory_ledgers')->where('evidence_number', $inboundRef)->get(['transaction_date', 'type', 'qty', 'total_cost'])->all(),
                ];
            } finally {
                DB::setDefaultConnection('writer_test');
            }
        };
        $snapshot('before');
        foreach ([$first, $first === 'maker' ? 'inbound' : 'maker'] as $action) {
            if ($action === 'maker') {
                $response = $this->actingAs($users['mk'])->postJson(route('payment-plan-v33.action'), [
                    'id' => $id, 'version' => $version, 'mutationId' => 'future-credit-maker',
                    'action' => 'mark_maker', 'input' => ['reference' => 'SYNTHETIC-FUTURE'],
                ]);
                $results[$action] = ['http' => $response->status(), 'error' => $response->json('error')];
            } else {
                session()->forget(['success', 'error', 'errors']);
                DB::flushQueryLog();
                DB::enableQueryLog();
                $response = $this->actingAs($users['fs'])->from(route('warehouse.inbound.create', ['tab' => 'transfer_masuk']))
                    ->post(route('warehouse.inbound.store'), [
                        'transaction_date' => $next, 'evidence_number' => $inboundRef,
                        'description' => 'Synthetic future receipt', 'offset_account' => $bankCoa,
                        'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => '100.01']],
                    ]);
                $writes = array_values(array_filter(array_column(DB::getQueryLog(), 'query'),
                    static fn ($sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql)));
                DB::disableQueryLog();
                $results[$action] = ['http' => $response->status(), 'location' => $response->headers->get('Location'),
                    'success' => session('success'), 'error' => session('error'), 'validation' => session('errors')?->getBag('default')->toArray(),
                    'writes' => $writes];
            }
            $snapshot('after_'.$action);
        }
        $this->travelTo(\Illuminate\Support\Carbon::parse($next.' 10:00:00', 'Asia/Jakarta'));
        $snapshot('next_day');
        if ($path = getenv('WAREHOUSE_INBOUND_EVIDENCE')) {
            $this->assertGreaterThan(0, file_put_contents($path, json_encode(compact('first', 'writer', 'day', 'next',
                'inboundRef', 'results', 'snapshots'), JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND));
        }

        $this->assertSame('500.00', $snapshots['before']['balance']['available']);
        $this->assertSame(200, $results['maker']['http']);
        $this->assertSame('future_protected_bank_credit', $results['inbound']['error']);
        $this->assertSame([], $results['inbound']['writes']);
        $lastOnDay = $snapshots[$first === 'maker' ? 'after_inbound' : 'after_maker'];
        foreach ([$lastOnDay, $snapshots['next_day']] as $state) {
            $this->assertSame('100.00', $state['balance']['available']);
            $this->assertSame('500.00', $state['balance']['actual']);
            $this->assertSame([['idr3', '400.00', 'active']], array_map(fn ($row) => array_values((array) $row), $state['hold']));
            $this->assertSame('bank_prepared', $state['maker_status']);
            $this->assertNull($state['journal']);
            $this->assertSame([], $state['lines']);
            $this->assertSame([], $state['ledger']);
            $this->assertEquals(0, $state['stock']->stock_quantity);
            $this->assertSame('0.00', $state['stock']->average_cost);
        }
    }

    private function observeFutureWriter(string $first, string $action, Company $company, array $settings, array $users,
        string $id, int $version, string $bankCoa, ?\App\Models\PaymentPlan $payment): void
    {
        $day = now('Asia/Jakarta')->toDateString();
        $next = now('Asia/Jakarta')->addDay()->toDateString();
        $this->assertSame('2026-11-27', $day);
        $this->assertSame('2026-11-28', $next);
        $writer = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $snapshots = $results = [];
        $snapshot = function (string $key) use (&$snapshots, $company, $settings, $writer, $id, $bankCoa, $next, $payment): void {
            $this->assertSame(0, DB::connection('writer_test')->transactionLevel());
            config(['database.connections.writer_final' => config('database.connections.writer_test')]);
            DB::purge('writer_final');
            DB::setDefaultConnection('writer_final');
            try {
                $reader = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                $this->assertNotContains($reader, [$writer, ...array_column($snapshots, 'reader')]);
                $snapshots[$key] = [
                    'day' => now('Asia/Jakarta')->toDateString(), 'reader' => $reader,
                    'balance' => app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings),
                    'hold' => DB::table('ppl_reservations')->where('request_id', $id)->get(['account_key', 'amount', 'status'])->all(),
                    'allocation' => DB::table('ppl_ap_allocations')->where('request_id', $id)->get(['amount', 'status'])->all(),
                    'maker_status' => DB::table('ppl_requests')->where('id', $id)->value('status'),
                    'future_credits' => DB::table('journal_details as l')->join('journal_headers as h', 'h.journal_id', '=', 'l.journal_id')
                        ->whereDate('h.transaction_date', $next)->where('l.account_code', $bankCoa)->where('l.position', 'KREDIT')
                        ->get(['h.journal_id', 'h.transaction_date', 'h.source_doc_no', 'l.amount'])->all(),
                    'legacy_status' => $payment?->fresh()?->status_payment,
                ];
            } finally {
                DB::setDefaultConnection('writer_test');
            }
        };
        $snapshot('before');
        $sourceState = static function (): array {
            $state = [];
            foreach (['journal_headers' => 'journal_id', 'journal_details' => 'id', 'purchase_bills' => 'id',
                'purchase_bill_details' => 'id', 'transaksi_payment_plan' => 'id_payment', 'assets' => 'id',
                'inventory_ledgers' => 'id', 'accounting_posting_intents' => 'idempotency_key'] as $table => $key) {
                $state[$table] = DB::table($table)->orderBy($key)->get()->toJson();
            }
            return $state;
        };
        if ($action === 'edit' || in_array($action, ['csv', 'xlsx'], true)) {
            $neutral = \App\Support\PostingService::post(['transaction_date' => $day, 'journal_type' => 'MANUAL'], [
                ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '100.01'],
                ['account_code' => '310001', 'position' => 'KREDIT', 'amount' => '100.01'],
            ]);
            if ($action !== 'edit') $neutral->forceFill(['journal_no' => 'FUTURE-WRITER', 'journal_type' => 'IMPORT'])->save();
        }
        $unchanged = false;
        foreach ([$first, $first === 'maker' ? $action : 'maker'] as $step) {
            session()->forget(['success', 'error', 'errors']);
            if ($step !== 'maker') $beforeSource = $sourceState();
            $response = $step === 'maker'
                ? $this->actingAs($users['mk'])->postJson(route('payment-plan-v33.action'), [
                    'id' => $id, 'version' => $version, 'mutationId' => 'future-writer-maker', 'action' => 'mark_maker',
                    'input' => ['reference' => 'SYNTHETIC-FUTURE-WRITER'],
                ])
                : $this->actingAs($users['fs'])->submitFutureWriter($action, $next, $bankCoa, $payment, $neutral?->getKey() ?? null);
            $results[$step] = ['http' => $response->status(), 'success' => session('success'),
                'error' => $step === 'maker' ? $response->json('error') : session('error'),
                'validation' => session('errors')?->getBag('default')->toArray()];
            if ($step !== 'maker') $unchanged = $beforeSource === $sourceState();
            $snapshot('after_'.$step);
        }
        $this->travelTo(\Illuminate\Support\Carbon::parse($next.' 10:00:00', 'Asia/Jakarta'));
        $snapshot('next_day');
        $path = getenv('F03_WRITER_DATE_EVIDENCE');
        $this->assertNotEmpty($path);
        $this->assertGreaterThan(0, file_put_contents($path, json_encode(compact('first', 'action', 'writer', 'day', 'next',
            'results', 'snapshots', 'unchanged'), JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX));

        $this->assertSame(200, $results['maker']['http']);
        $failure = json_encode($results[$action], JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('future_protected_bank_credit', $failure);
        if (in_array($action, ['csv', 'xlsx', 'opening-csv'], true)) $this->assertStringContainsString('baris ', $failure);
        $this->assertTrue($unchanged, 'Rejected writer modified committed source tables.');
        $afterBoth = $snapshots['after_'.($first === 'maker' ? $action : 'maker')];
        $this->assertSame('500.00', $afterBoth['balance']['actual']);
        $this->assertSame('100.00', $afterBoth['balance']['available']);
        foreach ([$afterBoth, $snapshots['next_day']] as $state) {
            $this->assertSame([['idr3', '400.00', 'active']], array_map(fn ($row) => array_values((array) $row), $state['hold']));
            $this->assertSame('bank_prepared', $state['maker_status']);
            $this->assertSame([], $state['future_credits']);
        }
        $this->assertSame('500.00', $snapshots['next_day']['balance']['actual']);
        $this->assertSame('100.00', $snapshots['next_day']['balance']['available']);
    }

    private function submitFutureWriter(string $action, string $next, string $bankCoa,
        ?\App\Models\PaymentPlan $payment, ?string $neutral): \Illuminate\Testing\TestResponse
    {
        $payload = ['transaction_date' => $next, 'source_doc_no' => 'SYNTHETIC-F03', 'description' => 'Synthetic D+1 credit',
            'details' => [
                ['account_code' => '610001', 'position' => 'DEBET', 'amount' => '100.01'],
                ['account_code' => $bankCoa, 'position' => 'KREDIT', 'amount' => '100.01'],
            ]];
        if ($action === 'edit') {
            return $this->put(route('jurnal.update', [$neutral]), $payload);
        }
        if ($action === 'fx') {
            $payload['currency_code'] = 'USD';
            foreach ($payload['details'] as &$line) $line['amount_txn'] = '1.00';
            unset($line);
        }
        if (in_array($action, ['csv', 'xlsx'], true)) {
            $rows = [
                ['Tanggal', 'No Jurnal', 'No Bukti', 'Deskripsi', 'Col5', 'Col6', 'Nilai Debet', 'Nilai Kredit', 'Akun'],
                ['2026-11-27', 'F03-VALID-FIRST', 'VALID', 'Synthetic', '', '', '10.00', '0.00', '610001'],
                ['2026-11-27', 'F03-VALID-FIRST', 'VALID', 'Synthetic', '', '', '0.00', '10.00', '310001'],
                [$next, 'FUTURE-WRITER', 'TEST', 'Synthetic', '', '', '100.01', '0.00', '610001'],
                [$next, 'FUTURE-WRITER', 'TEST', 'Synthetic', '', '', '0.00', '100.01', $bankCoa],
            ];
            if ($action === 'xlsx') {
                $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
                $book->getActiveSheet()->fromArray($rows);
                ob_start();
                (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
                $content = ob_get_clean();
            } else {
                $content = implode("\n", array_map(fn ($row) => implode(',', $row), $rows))."\n";
            }
            return $this->post(route('jurnal.import'), ['file_excel' => \Illuminate\Http\UploadedFile::fake()
                ->createWithContent('synthetic.'.$action, $content)]);
        }
        return match ($action) {
            'manual', 'fx' => $this->post(route('jurnal.store'), $payload),
            'legacy' => $this->post(route('payment.post_journal'), ['selected_ids' => $payment->no_transaksi]),
            'cash_bill' => $this->post(route('purchase-bills.store'), ['bill_date' => $next, 'vendor_name' => 'Synthetic D+1',
                'credit_account' => $bankCoa, 'details' => [['account_code' => '610001', 'amount' => '100.01']]]),
            'opening-csv' => $this->post(route('account.import_opening_balance'), ['tanggal_saldo' => $next,
                'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('synthetic.csv',
                    "Account,Name,Debit,Credit\n610001,Other,100.01,0\n{$bankCoa},Bank,0,100.01\n")]),
        };
    }

    private function observeOutboundRollback(Company $company, array $settings, array $users, string $id, int $version, string $bankCoa, bool $collision): void
    {
        config(['coa.piutang_usaha' => '12001', 'coa.hpp' => '610001']);
        foreach (['12001', '11200'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic',
                'normal_balance' => 'DEBET', 'report_pos' => 'NERACA', 'coa_type' => 'Other']);
        }
        DB::table('accounts')->insert(['account_code' => '411001', 'account_name' => 'Synthetic sales',
            'normal_balance' => 'KREDIT', 'report_pos' => 'LABA RUGI', 'coa_type' => 'Sales']);
        $day = ErpBankBalance::businessDate();
        $sales = \App\Models\Product::create(['sku' => 'F03-SALES', 'name' => 'Synthetic', 'stock_quantity' => 1, 'average_cost' => '20.00']);
        $manual = \App\Models\Product::create(['sku' => 'F03-OUT', 'name' => 'Synthetic', 'stock_quantity' => 1, 'average_cost' => '100.01']);
        $so = \App\Models\SalesOrder::create(['so_number' => 'SO-F03-OUT', 'transaction_date' => $day, 'contact_name' => 'Synthetic', 'status' => 'OPEN']);
        $this->actingAs($users['fs'])->post(route('so.ship', $so->id), ['ship_date' => $day, 'sales_semantic' => 'LOCAL',
            'items' => [['item_code' => $sales->sku, 'qty' => 1, 'price' => 50]], 'sub_total' => 50, 'grand_total' => 50])->assertSessionHas('success');
        $number = \App\Models\SalesInvoice::where('sales_order_id', $so->id)->sole()->invoice_number;
        $manualNumber = $collision ? $number : 'OUT-F03-SEPARATE';
        $this->post(route('warehouse.outbound.store'), ['evidence_number' => $manualNumber, 'transaction_date' => $day,
            'offset_account' => $bankCoa, 'items' => [['product_id' => $manual->id, 'qty' => 1]]])->assertSessionHas('success');
        $this->actingAs($users['mk'])->postJson(route('payment-plan-v33.action'), ['id' => $id, 'version' => $version,
            'mutationId' => 'outbound-maker', 'action' => 'mark_maker', 'input' => ['reference' => 'SYNTHETIC']])->assertOk();
        $balance = fn () => app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings);
        $snapshot = fn () => [
            'balance' => $balance(),
            'gl' => DB::table('journal_headers as h')->join('journal_details as d', 'd.journal_id', '=', 'h.journal_id')
                ->whereIn('h.source_doc_no', [$number, $manualNumber])->orderBy('d.id')->get(['h.source_doc_no', 'h.transaction_type', 'd.*'])->all(),
            'stock' => DB::table('products')->whereIn('id', [$sales->id, $manual->id])->orderBy('id')->get()->all(),
            'ledger' => DB::table('inventory_ledgers')->whereIn('evidence_number', [$number, $manualNumber])->orderBy('id')->get()->all(),
            'invoice' => DB::table('sales_invoices')->where('invoice_number', $number)->first(),
            'so' => DB::table('sales_orders')->where('id', $so->id)->first(),
            'hold' => DB::table('ppl_reservations')->where('request_id', $id)->first(),
        ];
        $before = $snapshot();
        $this->assertSame('100.00', $before['balance']['available']);
        $this->assertSame('400.00', $before['balance']['reserved']);
        $this->assertCount(2, $before['ledger']);
        $response = $this->actingAs($users['fs'])->post(route('so.rollback', $so->id))->assertRedirect();
        config(['database.connections.writer_final' => config('database.connections.writer_test')]);
        $writer = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        DB::setDefaultConnection('writer_final');
        try {
            $reader = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($writer, $reader);
            $after = $snapshot();
            file_put_contents(getenv('F03_RESIDUAL_EVIDENCE'), json_encode(compact('collision', 'before', 'after', 'reader', 'writer'), JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND);
            $this->assertSame('100.00', $after['balance']['available']);
            $this->assertEquals($before['hold'], $after['hold']);
            if ($collision) {
                $response->assertSessionHas('error');
                $this->assertEquals($before, $after);
            } else {
                $response->assertSessionHas('success');
                $this->assertNull($after['invoice']);
                $this->assertSame('APPROVED', $after['so']->status);
                $this->assertEquals([1, 0], array_column($after['stock'], 'stock_quantity'));
                $this->assertEquals(array_values(array_filter($before['gl'], fn ($line) => $line->source_doc_no === $manualNumber)), $after['gl']);
                $this->assertEquals(array_values(array_filter($before['ledger'], fn ($line) => $line->evidence_number === $manualNumber)), $after['ledger']);
            }
        } finally {
            DB::setDefaultConnection('writer_test');
        }
    }

    private function seedResidualWriter(string $action, User $actor, string $bankCoa, string $amount): array
    {
        $day = ErpBankBalance::businessDate();
        $this->actingAs($actor);
        switch ($action) {
            case 'bill-delete':
                $this->post(route('purchase-bills.store'), ['bill_date' => $day, 'vendor_name' => 'Synthetic debit bill',
                    'credit_account' => '211001', 'details' => [['account_code' => $bankCoa, 'amount' => $amount]]])
                    ->assertSessionHas('success');
                $bill = DB::table('purchase_bills')->where('vendor_name', 'Synthetic debit bill')->sole();
                return ['id' => $bill->id, 'journal_id' => $bill->journal_id, 'kind' => $action];
            case 'ar-reverse':
                config(['platform.payment_reverse_user_ids' => [$actor->id], 'platform.payment_post_user_ids' => [$actor->id],
                    'coa.piutang_usaha' => '12001']);
                DB::table('accounts')->insert(['account_code' => '12001', 'account_name' => 'Synthetic AR',
                    'normal_balance' => 'DEBET', 'coa_type' => 'Other', 'report_pos' => 'NERACA']);
                $source = \App\Models\JournalHeader::create(['transaction_date' => $day, 'transaction_type' => 'Sales Invoice']);
                $source->details()->create(['account_code' => '12001', 'position' => 'DEBET', 'amount' => $amount]);
                $invoice = \App\Models\SalesInvoice::create(['invoice_number' => 'SYNTH-F03-AR', 'transaction_date' => $day,
                    'contact_name' => 'Synthetic', 'grand_total' => $amount, 'payment_status' => 'UNPAID', 'journal_id' => $source->getKey()]);
                $receipt = \App\Models\JournalHeader::create(['transaction_date' => $day, 'journal_type' => 'MANUAL', 'source_doc_no' => 'SYNTH-F03-AR']);
                $receipt->details()->createMany([
                    ['account_code' => $bankCoa, 'position' => 'DEBET', 'amount' => $amount],
                    ['account_code' => '12001', 'position' => 'KREDIT', 'amount' => $amount],
                ]);
                $this->postJson(route('invoice-payment-allocations.store'), ['sales_invoice_id' => $invoice->id,
                    'journal_id' => $receipt->getKey()])->assertOk();
                $allocation = DB::table('invoice_payment_allocations')->sole();
                return ['id' => $allocation->id, 'journal_id' => $receipt->getKey(), 'invoice_id' => $invoice->id,
                    'invoice_journal_id' => $source->getKey(), 'kind' => $action];
        }
        throw new \LogicException('Unknown residual writer');
    }

    private function residualSource(array $target): array
    {
        $tables = $target['kind'] === 'bill-delete'
            ? ['purchase_bills' => ['id', $target['id']], 'purchase_bill_details' => ['purchase_bill_id', $target['id']]]
            : ['invoice_payment_allocations' => ['id', $target['id']], 'sales_invoices' => ['id', $target['invoice_id']]];
        $state = [];
        foreach ($tables as $table => [$column, $id]) $state[$table] = DB::table($table)->where($column, $id)->orderBy('id')->get()->all();
        $ids = array_filter([$target['journal_id'], $target['invoice_journal_id'] ?? null]);
        $state['headers'] = DB::table('journal_headers')->whereIn('journal_id', $ids)->orderBy('journal_id')->get()->all();
        $state['lines'] = DB::table('journal_details')->whereIn('journal_id', $ids)->orderBy('id')->get()->all();
        $state['reversals'] = DB::table('journal_headers')->where('source_doc_no', 'SYNTH-F03-AR')->where('journal_type', 'REVERSAL')->get()->all();
        return $state;
    }

    private function assertResidualOutcome(string $first, string $action, string $amount, array $target, array $sourceBefore,
        array $results, object $wait, array $threads, Company $company, array $settings, string $id, object $makerBefore, string $bankCoa): void
    {
        $makerFirst = $first === 'maker';
        $rejected = $makerFirst && $amount === '100.01';
        [$maker, $writer] = $makerFirst ? $results : array_reverse($results);
        config(['database.connections.writer_final' => config('database.connections.writer_test')]);
        DB::purge('writer_final');
        DB::setDefaultConnection('writer_final');
        $reader = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
        $this->assertNotContains($reader, [...$threads, (int) DB::connection('writer_test')->selectOne('SELECT CONNECTION_ID() AS id')->id]);
        $balance = app(ErpBankBalance::class)->project($company->id, ['id' => 'idr3', 'currency' => 'IDR'], $settings);
        $hold = DB::table('ppl_reservations')->where('request_id', $id)->get(['amount', 'status'])->all();
        $ap = DB::table('ppl_ap_allocations')->where('request_id', $id)->get(['amount', 'status'])->all();
        $request = DB::table('ppl_requests')->where('id', $id)->first();
        $source = $this->residualSource($target);
        if ($path = getenv('F03_RESIDUAL_EVIDENCE')) {
            file_put_contents($path, json_encode(compact('first', 'action', 'amount', 'wait', 'threads', 'reader', 'results',
                'balance', 'hold', 'ap', 'request', 'sourceBefore', 'source'), JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        $this->assertSame($makerFirst ? 200 : 422, $maker['status']);
        if (!$makerFirst) {
            $this->assertSame('funds_insufficient', $maker['body']['error']);
            $this->assertEquals($makerBefore, $request);
        } else {
            $this->assertSame('bank_prepared', $request->status);
            $this->assertSame($makerBefore->version + 1, $request->version);
        }
        $expectedHold = $makerFirst ? [['400.00', 'active']] : [];
        foreach ([$hold, $ap] as $rows) $this->assertSame($expectedHold, array_map(fn ($row) => array_values((array) $row), $rows));
        if ($rejected) {
            if ($action === 'bill-delete') $this->assertStringContainsString('funds_insufficient', $writer['error'] ?? '');
            else {
                $this->assertSame(422, $writer['status']);
                $this->assertSame('funds_insufficient', $writer['body']['message']);
            }
            $this->assertEquals($sourceBefore, $source);
        } elseif ($action === 'bill-delete') {
            $this->assertNotEmpty($writer['success']);
            foreach ($source as $rows) $this->assertSame([], $rows);
        } else {
            $this->assertSame(200, $writer['status']);
            $this->assertEquals($sourceBefore['headers'], $source['headers']);
            $this->assertEquals($sourceBefore['lines'], $source['lines']);
            $this->assertSame('UNPAID', $source['sales_invoices'][0]->payment_status);
            $this->assertCount(1, $source['reversals']);
            $reversal = $source['invoice_payment_allocations'][0]->reversal_journal_id;
            $this->assertSame($source['reversals'][0]->journal_id, $reversal);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $reversal, 'account_code' => $bankCoa, 'position' => 'KREDIT', 'amount' => $amount]);
            $this->assertDatabaseHas('journal_details', ['journal_id' => $reversal, 'account_code' => '12001', 'position' => 'DEBET', 'amount' => $amount]);
        }
        $this->assertSame($rejected ? '500.00' : bcsub('500.00', $amount, 2), $balance['actual']);
        $this->assertSame($makerFirst ? '400.00' : '0.00', $balance['reserved']);
        $this->assertSame($rejected ? '100.00' : ($makerFirst ? '0.00' : '399.99'), $balance['available']);
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
        if (preg_match('/legacy_payment_post|cash_bill/', $this->name()) && getenv('MGI_TEST_PORT') !== '33391') {
            $this->markTestSkipped('F03 tests require the dedicated disposable port.');
        }
        $inbound = str_contains($this->name(), 'warehouse_inbound_race');
        $future = str_contains($this->name(), 'future_inbound');
        $futureWriter = str_contains($this->name(), 'future_writer');
        $lifecycle = str_contains($this->name(), 'outbound_rollback');
        $residual = $lifecycle || str_contains($this->name(), 'residual_writer_committed');
        if ($residual && (getenv('MGI_TEST_PORT') !== '33397' || !getenv('F03_RESIDUAL_EVIDENCE'))) {
            $this->markTestSkipped('Residual capacity tests require the dedicated disposable runner.');
        }
        if ($futureWriter && getenv('MGI_TEST_PORT') !== '33396') {
            $this->markTestSkipped('Writer-date probe requires its dedicated disposable port.');
        }
        if ($future && getenv('MGI_TEST_PORT') !== '33395') {
            $this->markTestSkipped('Future-date probe requires its dedicated disposable port.');
        }
        if ($inbound && getenv('MGI_TEST_PORT') !== '33394') {
            $this->markTestSkipped('Inbound overlap requires the dedicated disposable port.');
        }
        $this->isolatedDatabase = 'audit_manual_writer_'.bin2hex(random_bytes(8));
        $config = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: 3306,
            'username' => 'root', 'password' => '', 'database' => 'information_schema', 'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.writer_admin' => $config]);
        if ($residual) {
            $identity = DB::connection('writer_admin')->selectOne('SELECT @@datadir AS datadir, VERSION() AS version');
            $this->assertSame($lifecycle ? 'd:/erp-recovery/m1-f03-lifecycle-20261127-db/data' : 'd:/erp-recovery/m1-f03-residual-guard-20261127-db/data',
                strtolower(rtrim(preg_replace('~[\\\\/]+~', '/', $identity->datadir), '/')));
            $this->assertStringContainsString('MariaDB', $identity->version);
        }
        if ($future || $futureWriter) {
            $identity = DB::connection('writer_admin')->selectOne('SELECT @@datadir AS datadir, VERSION() AS version');
            $this->assertSame($futureWriter ? 'd:/erp-recovery/m1-f03-writer-date-20261127-db/data' : 'd:/erp-recovery/m1-f03-future-20261127-db/data',
                strtolower(rtrim(preg_replace('~[\\\\/]+~', '/', $identity->datadir), '/')));
            $this->assertStringContainsString('MariaDB', $identity->version);
        }
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
