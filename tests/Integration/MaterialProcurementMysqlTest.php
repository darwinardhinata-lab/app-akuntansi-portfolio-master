<?php

namespace Tests\Integration;

use App\Models\User;
use App\Modules\Manufacturing\Models\Supplier;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class MaterialProcurementMysqlTest extends TestCase
{
    private ?string $fixture = null;

    public function test_http_identical_unkeyed_expense_submissions_with_stock_available(): void
    {
        [, $material] = $this->auxiliaryReceiptFixture();
        $line = \App\Modules\Manufacturing\Models\ProductionLine::create(['line_code' => 'O04-REPEAT', 'line_name' => 'O04 fixture', 'is_active' => true]);
        $workOrder = \App\Modules\Manufacturing\Models\WorkOrder::create(['spk_number' => 'O04-REPEAT', 'order_date' => '2026-11-24', 'line_id' => $line->id, 'garment_name' => 'Fixture', 'planned_qty' => 5]);
        $payload = ['issue_date' => '2026-11-24', 'auxiliary_material_id' => $material->id, 'work_order_id' => $workOrder->id, 'line_id' => $line->id, 'usage_type' => 'EXPENSE', 'qty' => '2.00'];
        config(['database.connections.o04_observer' => config('database.connections.m2_fixture')]);
        $observer = DB::connection('o04_observer');
        try {
            $writerId = DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $observerId = $observer->selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($writerId, $observerId);
            $this->assertSame(0, DB::transactionLevel());
            $this->assertSame('5.00', $observer->table('mfg_auxiliary_materials')->find($material->id)->stock_quantity);
            $runs = [];
            foreach ([1 => ['3.00', '300.00'], 2 => ['1.00', '100.00']] as $count => [$remaining, $value]) {
                $this->flushSession();
                $response = $this->post(route('mfg.auxiliary-material-issues.store'), $payload);
                $response->assertRedirect(route('mfg.work-orders.show', $workOrder->id))->assertSessionHas('success')->assertSessionMissing('error');
                $issues = $observer->table('mfg_auxiliary_material_issues')->orderBy('id')->get();
                $this->assertCount($count, $issues);
                $this->assertCount($count, $issues->pluck('issue_number')->unique());
                $this->assertCount($count, $issues->pluck('journal_id')->unique());
                $this->assertSame($count, $observer->table('mfg_material_ledgers')->where('type', 'OUT')->count());
                $this->assertSame($count + 1, $observer->table('journal_headers')->count());
                $issue = $issues->last();
                $out = $observer->table('mfg_material_ledgers')->where('evidence_number', $issue->issue_number)->sole();
                $this->assertSame(['OUT', '2.00', '200.00', $remaining, $value], [$out->type, $out->qty, $out->total_cost, $out->running_qty, $out->running_value]);
                $this->assertSame($remaining, $observer->table('mfg_auxiliary_materials')->find($material->id)->stock_quantity);
                $journal = $observer->table('journal_headers')->where('journal_id', $issue->journal_id)->sole();
                $this->assertCount($count, $observer->table('journal_headers')->whereIn('journal_id', $issues->pluck('journal_id'))->pluck('evidence_number')->unique());
                $postings = $observer->table('journal_details')->where('journal_id', $issue->journal_id)->orderBy('account_code')->get()->map(fn ($row) => [$row->account_code, $row->position, $row->amount])->all();
                $this->assertSame([['114004', 'KREDIT', '200.00'], ['510010', 'DEBET', '200.00']], $postings);
                if ($count === 2) {
                    $this->assertEquals($runs[0]['issue'], $issues->first());
                }
                $runs[] = ['status' => $response->getStatusCode(), 'redirect' => $response->headers->get('Location'), 'success' => session('success'), 'issueCount' => $count] + compact('issue', 'out', 'journal', 'postings');
            }
            if ($directory = getenv('O02_EVIDENCE')) {
                file_put_contents($directory.'/auxiliary-issue-repeat.json', json_encode(compact('writerId', 'observerId', 'payload', 'runs'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
        } finally {
            DB::purge('o04_observer');
        }
    }

    private function auxiliaryReceiptFixture(): array
    {
        $operator = User::factory()->create(['role' => 'FINANCE']);
        config([
            'platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false,
            'customs.enabled' => false, 'platform.mrn_void_user_ids' => [$operator->id],
        ]);
        $company = \App\Modules\Platform\Models\Company::create(['code' => 'MGI', 'name' => 'MGI', 'active' => true]);
        $operator->companies()->attach($company->id, ['is_default' => true]);
        foreach ([['114004', 'ASSET', 'DEBET', 'auxiliary_material_inventory'], ['117008', 'ASSET', 'DEBET', 'input_vat'], ['211001', 'LIABILITY', 'KREDIT', 'accounts_payable'], ['510010', 'EXPENSE', 'DEBET', 'auxiliary_material_expense']] as [$code, $type, $side, $key]) {
            \App\Models\Account::create(['account_code' => $code, 'account_name' => $key, 'coa_type' => $type, 'normal_balance' => $side, 'report_pos' => $type === 'EXPENSE' ? 'LABA RUGI' : 'NERACA']);
            \App\Modules\Platform\Models\CompanyCoaMapping::create(['company_id' => $company->id, 'semantic_key' => $key, 'account_code' => $code, 'active' => true]);
        }
        $material = \App\Modules\Manufacturing\Models\AuxiliaryMaterial::create(['material_code' => 'AUX-O04', 'material_name' => 'Canonical label', 'unit' => 'PCS', 'is_active' => true]);
        $supplier = Supplier::create(['supplier_code' => 'SUP-O04', 'supplier_name' => 'Canonical supplier', 'supplier_type' => 'RAW_MATERIAL']);
        $this->actingAs($operator)->post(route('mfg.material-receipts.store'), [
            'receipt_date' => '2026-11-24', 'supplier_id' => $supplier->id, 'supplier_doc_no' => 'O04-AUX-RECEIPT',
            'items' => [['item_type' => 'AUXILIARY', 'auxiliary_material_id' => $material->id, 'item_name' => $material->material_name, 'qty' => 5, 'unit' => 'PCS', 'rate' => 100]],
        ])->assertSessionHas('success');
        return [$operator, $material, $supplier];
    }

    #[TestWith([5, 120, '110.00', '220.00', ['1100.00', '880.00', '1100.00'], ['1100.00', '880.00', '1100.00'], 'exact average'], 'exact average')]
    #[TestWith([1, 101, '100.17', '200.34', ['601.00', '400.68', '601.02'], ['601.00', '400.66', '601.00'], 'rounding gap'], 'rounding gap')]
    public function test_http_auxiliary_mixed_cost_issue_and_void_valuation(
        int $secondQty, int $secondRate, string $average, string $issueValue, array $cardValues, array $glValues, string $scenario,
    ): void {
        [, $material, $supplier] = $this->auxiliaryReceiptFixture();
        config(['database.connections.o04_observer' => config('database.connections.m2_fixture')]);
        $observer = DB::connection('o04_observer');
        try {
            $this->assertNotSame(DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $observer->selectOne('SELECT CONNECTION_ID() AS id')->id);
            $this->assertSame(0, DB::transactionLevel());
            $card = fn () => $observer->table('mfg_material_ledgers')->where('item_type', 'AUXILIARY')->where('item_id', $material->id)->orderBy('id')->get();
            $netGl = fn () => $observer->table('journal_details')->where('account_code', '114004')
                ->selectRaw("SUM(CASE WHEN position = 'DEBET' THEN amount ELSE -amount END) AS net")->sole()->net;
            $first = $card()->sole();
            $this->assertSame(['IN', '5.00', '500.00', '5.00', '500.00'], [$first->type, $first->qty, $first->total_cost, $first->running_qty, $first->running_value]);
            $this->assertSame('500.00', $netGl());
            $this->flushSession();
            $this->post(route('mfg.material-receipts.store'), [
                'receipt_date' => '2026-11-24', 'supplier_id' => $supplier->id, 'supplier_doc_no' => 'O04-MIXED-RECEIPT',
                'items' => [['item_type' => 'AUXILIARY', 'auxiliary_material_id' => $material->id, 'item_name' => $material->material_name, 'qty' => $secondQty, 'unit' => 'PCS', 'rate' => $secondRate]],
            ])->assertSessionHas('success')->assertSessionMissing('error');

            $steps = [];
            $check = function (int $stage, string $type, string $movementQty, string $cost, string $runningQty) use ($observer, $material, $card, $netGl, $cardValues, $glValues, $average, &$steps): void {
                $rows = $card();
                $this->assertCount($stage + 2, $rows);
                $last = $rows->last();
                $stock = $observer->table('mfg_auxiliary_materials')->find($material->id);
                $net = $netGl();
                $masterValue = bcmul($stock->stock_quantity, $stock->average_cost, 2);
                $this->assertSame([$type, $movementQty, $cost, $runningQty, $cardValues[$stage]], [$last->type, $last->qty, $last->total_cost, $last->running_qty, $last->running_value]);
                $this->assertSame([$runningQty, $average], [$stock->stock_quantity, $stock->average_cost]);
                $this->assertSame($glValues[$stage], $net);
                $steps[] = compact('last', 'stock', 'net', 'masterValue');
            };
            $received = bcadd('5', (string) $secondQty, 2);
            $check(0, 'IN', bcadd('0', (string) $secondQty, 2), bcmul((string) $secondQty, (string) $secondRate, 2), $received);
            $this->flushSession();
            $this->post(route('mfg.auxiliary-material-issues.store'), [
                'issue_date' => '2026-11-24', 'auxiliary_material_id' => $material->id, 'usage_type' => 'EXPENSE', 'qty' => '2.00',
            ])->assertSessionHas('success')->assertSessionMissing('error');
            $issue = $observer->table('mfg_auxiliary_material_issues')->sole();
            $this->assertSame([$average, $issueValue], [$issue->unit_cost, $issue->total_cost]);
            $check(1, 'OUT', '2.00', $issueValue, bcsub($received, '2', 2));

            $this->flushSession();
            $this->post(route('mfg.auxiliary-material-issues.void', $issue->id))->assertSessionHas('success')->assertSessionMissing('error');
            $voided = $observer->table('mfg_auxiliary_material_issues')->sole();
            $this->assertNotNull($voided->voided_at);
            $check(2, 'IN', '2.00', $issueValue, $received);
            foreach ([$issue->journal_id => ['KREDIT', 'DEBET'], $voided->reversal_journal_id => ['DEBET', 'KREDIT']] as $journalId => [$inventorySide, $expenseSide]) {
                $postings = $observer->table('journal_details')->where('journal_id', $journalId)->orderBy('account_code')->get()
                    ->map(fn ($row) => [$row->account_code, $row->position, $row->amount])->all();
                $this->assertSame([['114004', $inventorySide, $issueValue], ['510010', $expenseSide, $issueValue]], $postings);
            }
            $this->assertSame(4, $observer->table('journal_headers')->count());
            if ($directory = getenv('O02_EVIDENCE')) {
                $filename = 'auxiliary-mixed-'.str_replace(' ', '-', $scenario).'.json';
                file_put_contents($directory.'/'.$filename, json_encode(compact('first', 'steps', 'issue', 'voided'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
        } finally {
            DB::purge('o04_observer');
        }
    }

    public function test_http_auxiliary_expense_void_reconciles_stock_card_to_gl(): void
    {
        [, $material] = $this->auxiliaryReceiptFixture();
        $this->flushSession();
        $this->post(route('mfg.auxiliary-material-issues.store'), [
            'issue_date' => '2026-11-24', 'auxiliary_material_id' => $material->id,
            'usage_type' => 'EXPENSE', 'qty' => '2.00',
        ])->assertSessionHas('success')->assertSessionMissing('error');

        config(['database.connections.o04_observer' => config('database.connections.m2_fixture')]);
        $observer = DB::connection('o04_observer');
        try {
            $this->assertNotSame(DB::selectOne('SELECT CONNECTION_ID() AS id')->id, $observer->selectOne('SELECT CONNECTION_ID() AS id')->id);
            $this->assertSame(0, DB::transactionLevel());
            $receipt = $observer->table('mfg_material_receipts')->sole();
            $issue = $observer->table('mfg_auxiliary_material_issues')->sole();
            $this->flushSession();
            $this->post(route('mfg.auxiliary-material-issues.void', $issue->id))
                ->assertSessionHas('success')->assertSessionMissing('error');
            $voided = $observer->table('mfg_auxiliary_material_issues')->sole();
            $this->assertSame($issue->id, $voided->id);
            $this->assertNotNull($voided->voided_at);
            $this->assertNotSame($issue->journal_id, $voided->reversal_journal_id);

            $card = $observer->table('mfg_material_ledgers')->where('item_type', 'AUXILIARY')->where('item_id', $material->id)->orderBy('id')->get();
            $this->assertCount(3, $card);
            $qty = $value = '0.00';
            $stages = [];
            foreach ([
                [$receipt->receipt_number, $receipt->journal_id, 'IN', '5.00', '500.00', '211001'],
                [$issue->issue_number, $issue->journal_id, 'OUT', '2.00', '200.00', '510010'],
                [$issue->issue_number.'-VOID', $voided->reversal_journal_id, 'IN', '2.00', '200.00', '510010'],
            ] as $index => [$evidence, $journalId, $movement, $units, $amount, $counterparty]) {
                $row = $card[$index];
                $this->assertSame([$evidence, $movement, $units, $amount], [$row->evidence_number, $row->type, $row->qty, $row->total_cost]);
                $qty = $movement === 'IN' ? bcadd($qty, $units, 2) : bcsub($qty, $units, 2);
                $value = $movement === 'IN' ? bcadd($value, $amount, 2) : bcsub($value, $amount, 2);
                $this->assertSame([$qty, $value], [$row->running_qty, $row->running_value]);
                $postings = $observer->table('journal_details')->where('journal_id', $journalId)->orderBy('account_code')->get()
                    ->map(fn ($line) => [$line->account_code, $line->position, $line->amount])->all();
                $this->assertSame([
                    ['114004', $movement === 'IN' ? 'DEBET' : 'KREDIT', $amount],
                    [$counterparty, $movement === 'IN' ? 'KREDIT' : 'DEBET', $amount],
                ], $postings);
                $stages[] = compact('row', 'journalId', 'postings');
            }
            $stock = $observer->table('mfg_auxiliary_materials')->find($material->id);
            $netInventory = $observer->table('journal_details')->where('account_code', '114004')
                ->selectRaw("SUM(CASE WHEN position = 'DEBET' THEN amount ELSE -amount END) AS net")->sole()->net;
            $this->assertSame($qty, $stock->stock_quantity);
            $this->assertSame($value, bcmul($stock->stock_quantity, $stock->average_cost, 2));
            $this->assertSame($value, $netInventory);
            $this->assertSame(3, $observer->table('journal_headers')->count());
            $this->assertSame(0, $observer->table('inventory_ledgers')->count());

            $this->flushSession();
            $this->post(route('mfg.auxiliary-material-issues.void', $issue->id))
                ->assertSessionHas('error', fn ($error) => str_contains($error, 'sudah dibatalkan'));
            $this->assertSame(3, $observer->table('mfg_material_ledgers')->count());
            $this->assertSame(3, $observer->table('journal_headers')->count());
            $this->assertSame(6, $observer->table('journal_details')->count());
            $this->assertEquals($stock, $observer->table('mfg_auxiliary_materials')->find($material->id));
            if ($directory = getenv('O02_EVIDENCE')) {
                file_put_contents($directory.'/auxiliary-issue-conservation.json', json_encode(compact('stages', 'stock', 'netInventory'), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
        } finally {
            DB::purge('o04_observer');
        }
    }

    public function test_http_auxiliary_expense_issue_conserves_stock_and_blocks_void_of_consumed_receipt(): void
    {
        [, $material] = $this->auxiliaryReceiptFixture();
        $issue = fn (int $qty) => $this->post(route('mfg.auxiliary-material-issues.store'), [
            'issue_date' => '2026-11-24', 'auxiliary_material_id' => $material->id, 'usage_type' => 'EXPENSE', 'qty' => $qty, 'work_order_id' => null,
        ]);
        $response = $issue(2);
        $this->assertNull(session('error'), (string) session('error'));
        $response->assertSessionHas('success');
        $posted = DB::table('mfg_auxiliary_material_issues')->sole();
        $this->assertSame('3.00', $material->fresh()->stock_quantity);
        $this->assertSame('100.00', $material->fresh()->average_cost);
        $this->assertSame('200.00', $posted->total_cost);
        $ledger = DB::table('mfg_material_ledgers')->where('evidence_number', $posted->issue_number)->sole();
        $this->assertSame(['OUT', '2.00', '200.00', '3.00', '300.00'], [$ledger->type, $ledger->qty, $ledger->total_cost, $ledger->running_qty, $ledger->running_value]);
        $postings = DB::table('journal_details')->where('journal_id', $posted->journal_id)->orderBy('account_code')->get();
        $this->assertSame([['114004', 'KREDIT', '200.00'], ['510010', 'DEBET', '200.00']], $postings->map(fn ($row) => [$row->account_code, $row->position, $row->amount])->all());
        $this->assertDatabaseCount('mfg_material_ledgers', 2);
        $receipt = DB::table('mfg_material_receipts')->sole();
        $snapshot = fn () => collect(['mfg_auxiliary_materials', 'mfg_auxiliary_material_issues', 'mfg_material_receipts', 'mfg_material_receipt_details', 'mfg_material_ledgers', 'inventory_ledgers', 'journal_headers', 'journal_details'])->map(fn ($table) => DB::table($table)->get()->map(fn ($row) => json_encode($row))->sort()->values()->all())->all();
        $before = $snapshot();
        $issue(4)->assertSessionHas('error', fn ($error) => str_contains($error, 'tidak mencukupi'));
        $this->assertSame($before, $snapshot());
        $this->post(route('mfg.material-receipts.void', $receipt->id), ['reason' => 'Reject void after issue'])->assertSessionHas('error', fn ($error) => str_contains($error, 'mutasi lanjutan'));
        $this->assertSame($before, $snapshot());
    }

    public function test_http_parallel_auxiliary_issues_cannot_overdraw_committed_stock(): void
    {
        [$operator, $material] = $this->auxiliaryReceiptFixture();
        $line = \App\Modules\Manufacturing\Models\ProductionLine::create(['line_code' => 'O04-RACE', 'line_name' => 'O04 fixture', 'is_active' => true]);
        $workOrder = \App\Modules\Manufacturing\Models\WorkOrder::create(['spk_number' => 'O04-RACE', 'order_date' => '2026-11-24', 'line_id' => $line->id, 'garment_name' => 'Fixture', 'planned_qty' => 5]);
        // Same fields sent by the real work-order expense form.
        $payload = ['issue_date' => '2026-11-24', 'auxiliary_material_id' => $material->id, 'line_id' => $line->id, 'work_order_id' => $workOrder->id, 'usage_type' => 'EXPENSE', 'qty' => '3.00'];
        $connection = config('database.connections.m2_fixture');
        $worker = new Process([PHP_BINARY, base_path('tests/Support/auxiliary-issue-race-worker.php')], base_path(), null,
            json_encode([$connection, $operator->id, $payload], JSON_THROW_ON_ERROR), 30);
        $snapshot = fn ($db) => collect(['mfg_auxiliary_materials', 'mfg_auxiliary_material_issues', 'mfg_material_ledgers', 'inventory_ledgers', 'journal_headers', 'journal_details'])->mapWithKeys(fn ($table) => [$table => $db->table($table)->get()->map(fn ($row) => (array) $row)->sortBy(fn ($row) => json_encode($row))->values()->all()])->all();
        DB::beginTransaction();
        try {
            $holder = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->post(route('mfg.auxiliary-material-issues.store'), $payload)
                ->assertRedirect(route('mfg.work-orders.show', $workOrder->id))->assertSessionHas('success');
            $worker->start();
            $wait = null;
            for ($i = 0; $i < 100 && $worker->isRunning(); $i++) {
                if (preg_match('/connection=(\d+)/', $worker->getOutput(), $match)) {
                    $wait = DB::connection('m2_admin')->selectOne('SELECT l.lock_table, l.lock_index, l.lock_data
                        FROM information_schema.innodb_lock_waits w
                        JOIN information_schema.innodb_trx a ON a.trx_id = w.requesting_trx_id
                        JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
                        JOIN information_schema.innodb_locks l ON l.lock_id = w.requested_lock_id
                        WHERE a.trx_mysql_thread_id = ? AND b.trx_mysql_thread_id = ?', [(int) $match[1], $holder]);
                    if ($wait) break;
                }
                usleep(100000);
            }
            $this->assertNotNull($wait, $worker->getOutput().$worker->getErrorOutput());
            $this->assertStringEndsWith('`mfg_auxiliary_materials`', $wait->lock_table);
            $this->assertSame('PRIMARY', $wait->lock_index);
            $winningSnapshot = $snapshot(DB::connection());
            DB::commit();
            $this->assertSame(3, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
            config(['database.connections.o04_observer' => $connection]);
            $db = DB::connection('o04_observer');
            $observer = (int) $db->selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotContains($observer, [$holder, (int) $match[1]]);
            $committed = $snapshot($db);
            $this->assertSame($winningSnapshot, $committed);
            $issue = $db->table('mfg_auxiliary_material_issues')->sole();
            $this->assertSame([$workOrder->id, '3.00', '300.00'], [$issue->work_order_id, $issue->qty, $issue->total_cost]);
            $this->assertSame('2.00', $db->table('mfg_auxiliary_materials')->find($material->id)->stock_quantity);
            $out = $db->table('mfg_material_ledgers')->where('type', 'OUT')->sole();
            $this->assertSame([$issue->issue_number, '3.00', '300.00', '2.00', '200.00'], [$out->evidence_number, $out->qty, $out->total_cost, $out->running_qty, $out->running_value]);
            $this->assertCount(2, $committed['journal_headers']);
            $postings = $db->table('journal_details')->where('journal_id', $issue->journal_id)->orderBy('account_code')->get();
            $this->assertSame([['114004', 'KREDIT', '300.00'], ['510010', 'DEBET', '300.00']], $postings->map(fn ($row) => [$row->account_code, $row->position, $row->amount])->all());
            if ($directory = getenv('O02_EVIDENCE')) {
                file_put_contents($directory.'/auxiliary-issue-race.json', json_encode(compact('holder', 'observer', 'wait', 'committed') + ['worker' => (int) $match[1], 'workerExit' => $worker->getExitCode(), 'workerResponse' => trim($worker->getOutput())], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
            }
        } finally {
            if (DB::transactionLevel() > 0) DB::rollBack();
            $worker->stop();
            DB::purge('o04_observer');
        }
    }

    public function test_http_auxiliary_expense_issue_without_work_order_reports_committed_success(): void
    {
        [, $material] = $this->auxiliaryReceiptFixture();
        $payload = ['issue_date' => '2026-11-24', 'auxiliary_material_id' => $material->id, 'qty' => '2.00'];
        $endpoint = route('mfg.auxiliary-material-issues.store');
        $this->post($endpoint, $payload + ['usage_type' => 'WIP'])
            ->assertSessionHas('error', fn ($error) => str_contains($error, 'wajib memilih SPK'));
        $this->assertDatabaseCount('mfg_auxiliary_material_issues', 0);
        $this->assertSame('5.00', $material->fresh()->stock_quantity);
        $this->flushSession();
        $response = $this->post($endpoint, $payload + ['usage_type' => 'EXPENSE']);
        $this->assertDatabaseCount('mfg_auxiliary_material_issues', 1);
        $issue = DB::table('mfg_auxiliary_material_issues')->sole();
        $this->assertNull($issue->work_order_id);
        $this->assertSame('3.00', $material->fresh()->stock_quantity);
        $this->assertDatabaseCount('mfg_material_ledgers', 2);
        $this->assertDatabaseCount('journal_headers', 2);
        $response->assertSessionMissing('error')->assertSessionHas('success')
            ->assertRedirect(route('mfg.auxiliary-materials.index'));
    }

    #[TestWith(['create', 'create'], 'create blocks create')]
    #[TestWith(['create', 'edit'], 'create blocks edit')]
    #[TestWith(['edit', 'create'], 'edit blocks create')]
    public function test_http_po_decimal_residual_is_exact_across_shared_source_lines(string $holderAction, string $workerAction): void
    {
        $service = app(MaterialProcurementService::class);
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        config([
            'platform.pr_create_user_ids' => [(string) $creator->id],
            'platform.pr_approve_user_ids' => [(string) $approver->id],
            'platform.po_create_user_ids' => [(string) $creator->id],
            'platform.po_approve_user_ids' => [(string) $approver->id],
        ]);
        $yarn = Yarn::create(['yarn_code' => 'O02-EXACT', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $supplier = Supplier::create(['supplier_code' => 'O02-EXACT', 'supplier_name' => 'Canonical supplier', 'supplier_type' => 'RAW_MATERIAL']);
        $item = ['item_type' => 'YARN', 'yarn_id' => $yarn->id, 'item_name' => 'Cotton', 'qty' => '0.30', 'unit' => 'KGS', 'rate' => '10.00'];
        $pr = $service->createRequest(['request_date' => '2026-11-23', 'created_by' => $creator->id], [$item]);
        $service->submitRequest($pr->id, $creator->id);
        $service->approveRequest($pr->id, $approver->id);
        $source = $pr->details()->sole();
        $line = fn (string $qty) => array_replace($item, ['qty' => $qty, 'source_request_detail_id' => $source->id]);
        $header = ['request_id' => $pr->id, 'supplier_id' => $supplier->id, 'po_date' => '2026-11-23'];
        $create = fn (...$lines) => $this->post(route('mfg.material-orders.store'), $header + ['items' => $lines]);
        $snapshot = fn () => collect(['mfg_material_purchase_orders', 'mfg_material_purchase_order_details', 'mfg_material_purchase_order_histories', 'mfg_material_purchase_request_details', 'mfg_yarns', 'journal_headers', 'journal_details', 'inventory_ledgers', 'mfg_material_ledgers'])->mapWithKeys(fn ($table) => [$table => DB::table($table)->get()->map(fn ($row) => json_encode($row))->sort()->values()->all()])->all();

        $this->actingAs($creator);
        $create($line('0.10'))->assertSessionHas('success');
        $first = DB::table('mfg_material_purchase_orders')->sole();
        $service->submitOrder($first->id, $creator->id);
        $service->approveOrder($first->id, $approver->id);
        $this->get(route('mfg.material-orders.create', ['request_id' => $pr->id]))->assertOk()
            ->assertSee('name="items[0][qty]" value="0.20"', false);
        $before = $snapshot();
        foreach ([[$line('0.21')], [$line('0.10'), $line('0.11')], [$line('0.10'), $line('0.101')]] as $lines) {
            $create(...$lines)->assertSessionHas('error');
            $this->assertSame($before, $snapshot());
        }
        $create($line('0.10'), $line('0.10'))->assertSessionHas('success');
        $second = DB::table('mfg_material_purchase_orders')->where('id', '!=', $first->id)->sole();
        $this->assertSame('0.30', $source->fresh()->qty_ordered);
        $edit = fn (...$lines) => $this->put(route('mfg.material-orders.update', $second->id), $header + ['items' => $lines]);
        foreach (['0.10', '0.15', '0.15', '0.20'] as $qty) {
            $edit($line($qty))->assertSessionHas('success');
            $this->assertSame(bcadd('0.10', $qty, 2), $source->fresh()->qty_ordered);
            $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $second->id, 'source_request_detail_id' => $source->id, 'qty' => $qty, 'unit' => 'KGS']);
        }
        $filled = $snapshot();
        $edit($line('0.10'), $line('0.11'))->assertSessionHas('error');
        $this->assertSame($filled, $snapshot());
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $first->id, 'qty' => '0.10']);
        foreach (['mfg_yarns', 'journal_headers', 'journal_details', 'inventory_ledgers', 'mfg_material_ledgers'] as $table) {
            $this->assertSame($before[$table], $filled[$table]);
        }

        // Free 0.10. Each contender independently requests the entire remainder.
        $edit($line('0.10'))->assertSessionHas('success');
        $connection = config('database.connections.m2_fixture');
        $worker = new Process([PHP_BINARY, base_path('tests/Support/material-po-race-worker.php')], base_path(), null,
            json_encode([$connection, $pr->id, $supplier->id, $line($workerAction === 'edit' ? '0.20' : '0.10'), ['po_date' => '2026-11-23', 'created_by' => $creator->id], $workerAction === 'edit' ? $second->id : null], JSON_THROW_ON_ERROR), 30);
        DB::beginTransaction();
        try {
            $holder = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            if ($holderAction === 'edit') {
                $edit($line('0.20'))->assertSessionHas('success');
                $winner = $second->id;
            } else {
                $create($line('0.10'))->assertSessionHas('success');
                $winner = DB::table('mfg_material_purchase_orders')->max('id');
            }
            $worker->start();
            $wait = null;
            for ($i = 0; $i < 100 && $worker->isRunning(); $i++) {
                if (preg_match('/connection=(\d+)/', $worker->getOutput(), $match)) {
                    $wait = DB::connection('m2_admin')->selectOne('SELECT l.lock_table, l.lock_index
                        FROM information_schema.innodb_lock_waits w
                        JOIN information_schema.innodb_trx a ON a.trx_id = w.requesting_trx_id
                        JOIN information_schema.innodb_trx b ON b.trx_id = w.blocking_trx_id
                        JOIN information_schema.innodb_locks l ON l.lock_id = w.requested_lock_id
                        WHERE a.trx_mysql_thread_id = ? AND b.trx_mysql_thread_id = ?', [(int) $match[1], $holder]);
                    if ($wait) {
                        break;
                    }
                }
                usleep(100000);
            }
            $this->assertNotNull($wait, $worker->getOutput().$worker->getErrorOutput());
            $this->assertMatchesRegularExpression($workerAction === 'edit'
                ? '/`mfg_material_purchase_(?:orders|requests)`$/'
                : '/`mfg_material_purchase_requests`$/', $wait->lock_table);
            $this->assertNotEmpty($wait->lock_index);
            $winningSnapshot = $snapshot();
            DB::commit();
            $this->assertSame(3, $worker->wait(), $worker->getErrorOutput());

            config(['database.connections.o02_observer' => $connection]);
            DB::setDefaultConnection('o02_observer');
            try {
                $observer = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
                $this->assertNotContains($observer, [$holder, (int) $match[1]]);
                $this->assertSame($winningSnapshot, $snapshot());
                $committed = DB::table('mfg_material_purchase_order_details')->where('source_request_detail_id', $source->id)->sum('qty');
                $this->assertSame('0.30', $committed);
                $this->assertSame('0.30', $source->fresh()->qty_ordered);
                if ($directory = getenv('O02_EVIDENCE')) {
                    file_put_contents($directory.'/race-'.$holderAction.'-'.$workerAction.'.json', json_encode(['holderAction' => $holderAction, 'workerAction' => $workerAction, 'holder' => $holder, 'worker' => (int) $match[1], 'observer' => $observer, 'workerExit' => $worker->getExitCode(), 'wait' => $wait, 'winner' => $winner, 'source' => $source->id, 'committed' => $committed], JSON_THROW_ON_ERROR));
                }
            } finally {
                DB::purge('o02_observer');
                DB::setDefaultConnection('m2_fixture');
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            $worker->stop();
        }
    }

    private bool $created = false;

    protected function setUp(): void
    {
        parent::setUp();
        if (getenv('MGI_M2_MYSQL_TESTS') !== '1') {
            $this->markTestSkipped('Run the isolated M2 MySQL verifier.');
        }
        $config = ['driver' => 'mysql', 'host' => getenv('MGI_TEST_HOST') ?: '127.0.0.1', 'port' => getenv('MGI_TEST_PORT') ?: '3306', 'username' => getenv('MGI_TEST_USER') ?: 'root', 'password' => getenv('MGI_TEST_PASSWORD') ?: '', 'database' => 'information_schema', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'prefix' => '', 'strict' => true];
        config(['database.connections.m2_admin' => $config]);
        $this->fixture = 'mgi_fresh_m2test_'.bin2hex(random_bytes(8));
        DB::connection('m2_admin')->statement('CREATE DATABASE `'.$this->fixture.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->created = true;
        $config['database'] = $this->fixture;
        config(['database.connections.m2_fixture' => $config, 'database.default' => 'm2_fixture']);
        DB::setDefaultConnection('m2_fixture');
        try {
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'm2_fixture', '--force' => true]), Artisan::output());
        } catch (\Throwable $e) {
            $this->cleanupFixture();
            throw $e;
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->cleanupFixture();
        } finally {
            parent::tearDown();
        }
    }

    public function test_full_mysql_migration_chain_supports_pr_to_approved_po_without_posting(): void
    {
        $yarn = Yarn::create(['yarn_code' => 'YARN-M2-MYSQL', 'yarn_type' => 'Cotton', 'unit' => 'KGS']);
        $supplier = Supplier::create(['supplier_code' => 'SUP-M2-MYSQL', 'supplier_name' => 'Supplier Raw Material', 'supplier_type' => 'RAW_MATERIAL']);
        $item = ['item_type' => 'YARN', 'yarn_id' => $yarn->id, 'item_name' => 'Cotton Yarn', 'qty' => 20, 'unit' => 'KGS', 'rate' => 12500];
        $service = app(MaterialProcurementService::class);
        // FIX: izin fixture eksplisit dengan pembuat dan approver terpisah.
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        config([
            'platform.pr_create_user_ids' => [(string) $creator->id],
            'platform.pr_approve_user_ids' => [(string) $approver->id],
            'platform.po_create_user_ids' => [(string) $creator->id],
            'platform.po_approve_user_ids' => [(string) $approver->id],
        ]);
        $pr = $service->createRequest(['request_date' => '2026-09-28', 'created_by' => $creator->id], [$item]);
        $service->submitRequest($pr->id, $creator->id);
        $service->approveRequest($pr->id, $approver->id);
        // FIX: verifier terisolasi juga memeriksa histori transisi PR pada revisi awal.
        $this->assertSame(0, $pr->fresh()->revision_no);
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $pr->histories()->pluck('action')->all());
        $this->assertDatabaseHas('mfg_material_purchase_request_histories', [
            'request_id' => $pr->id, 'action' => 'APPROVED', 'from_status' => 'SUBMITTED',
            'to_status' => 'APPROVED', 'actor_id' => $approver->id, 'revision_no' => 0,
        ]);
        $detail = $pr->fresh('details')->details->sole();
        $po = $service->createOrderFromRequest($pr->id, $supplier->id, [$item + ['source_request_detail_id' => $detail->id]], ['po_date' => '2026-09-28', 'created_by' => $creator->id]);
        $service->submitOrder($po->id, $creator->id);
        $service->approveOrder($po->id, $approver->id);
        // FIX: histori PO dan identitas approver diperiksa tanpa melonggarkan SoD fixture.
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $po->histories()->pluck('action')->all());
        $this->assertDatabaseHas('mfg_material_purchase_order_histories', [
            'order_id' => $po->id, 'action' => 'APPROVED', 'from_status' => 'SUBMITTED',
            'to_status' => 'APPROVED', 'actor_id' => $approver->id, 'revision_no' => 0,
        ]);

        $this->assertDatabaseHas('mfg_material_purchase_orders', ['id' => $po->id, 'approval_status' => 'APPROVED', 'fulfillment_status' => 'OPEN']);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id, 'source_request_detail_id' => $detail->id, 'qty' => 20]);
        $this->assertSame(0, DB::table('journal_headers')->count());
        $this->assertSame(0, DB::table('inventory_ledgers')->count());
        $this->assertSame(0, DB::table('mfg_material_ledgers')->count());
    }

    public function test_supplier_identity_authorization_and_failed_second_line_preserve_mysql_state(): void
    {
        $service = app(MaterialProcurementService::class);
        $creator = User::factory()->create();
        $approver = User::factory()->create();
        config([
            'platform.pr_create_user_ids' => [(string) $creator->id],
            'platform.pr_approve_user_ids' => [(string) $approver->id],
            'platform.po_create_user_ids' => [(string) $creator->id],
        ]);
        $material = \App\Modules\Manufacturing\Models\AuxiliaryMaterial::create([
            'material_code' => '000017', 'material_name' => 'Label', 'unit' => 'PCS',
            'meters_per_roll' => '12.75', 'stock_quantity' => '5.25', 'average_cost' => '1.50',
        ]);
        $supplier = Supplier::create(['supplier_code' => '000017', 'supplier_name' => 'Same name', 'supplier_type' => 'RAW_MATERIAL', 'is_active' => true]);
        $wrong = Supplier::create(['supplier_code' => 'OTHER', 'supplier_name' => 'Same name', 'supplier_type' => 'KNITTER', 'is_active' => true]);
        $item = ['item_type' => 'AUXILIARY', 'auxiliary_material_id' => $material->id,
            'item_name' => 'Label', 'qty' => '2.00', 'unit' => 'PCS', 'rate' => '12.75'];
        $pr = $service->createRequest(['request_date' => '2026-11-17', 'created_by' => $creator->id], [$item]);
        $service->submitRequest($pr->id, $creator->id);
        $service->approveRequest($pr->id, $approver->id);
        $detail = $pr->fresh('details')->details->sole();
        $line = $item + ['source_request_detail_id' => $detail->id];
        $header = ['po_date' => '2026-11-17', 'created_by' => $creator->id];
        $snapshot = function (): array {
            $state = [];
            foreach (DB::select('SHOW TABLES') as $table) {
                $name = array_values((array) $table)[0];
                $rows = DB::table($name)->get()->map(fn ($row) => json_encode($row))->all();
                sort($rows);
                $state[$name] = $rows;
            }
            ksort($state);
            return $state;
        };
        foreach (['wrong_type', 'inactive', 'revoked_permission', 'second_line', 'wrong_unit'] as $case) {
            $supplier->update(['is_active' => $case !== 'inactive']);
            config(['platform.po_create_user_ids' => $case === 'revoked_permission' ? [] : [(string) $creator->id]]);
            $before = $snapshot();
            try {
                $lines = match ($case) {
                    'second_line' => [$line, $line],
                    'wrong_unit' => [array_replace($line, ['qty' => '1.00']), array_replace($line, ['qty' => '1.00', 'unit' => 'ROLL'])],
                    default => [$line],
                };
                $service->createOrderFromRequest($pr->id, $case === 'wrong_type' ? $wrong->id : $supplier->id, $lines, $header);
                $this->fail('Invalid supplier, permission, residual or source unit must reject the entire PO.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertSame('revoked_permission', $case);
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString(match ($case) {
                    'second_line' => 'sisa detail PR',
                    'wrong_unit' => 'unit detail PR sumber',
                    default => 'Supplier Material PO',
                }, $e->getMessage());
            }
            $this->assertSame($before, $snapshot(), $case.' must not mutate table rows');
        }
        $po = $service->createOrderFromRequest($pr->id, $supplier->id, [$line], $header);
        $this->assertSame($supplier->id, $po->supplier_id);
        $this->assertDatabaseHas('mfg_material_purchase_order_details', ['po_id' => $po->id,
            'auxiliary_material_id' => $material->id, 'source_request_detail_id' => $detail->id, 'unit' => 'PCS', 'amount' => '25.50']);
        $this->assertSame('2.00', $detail->fresh()->qty_ordered);
        $before = $snapshot();
        try {
            $service->createOrderFromRequest($pr->id, $supplier->id, [$line], $header);
            $this->fail('Retry must not over-order an exhausted source line.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('sisa detail PR', $e->getMessage());
        }
        $this->assertSame($before, $snapshot());
        $service->submitOrder($po->id, $creator->id);
        foreach (['revoked_approver', 'self_approval'] as $case) {
            // Even an explicitly listed creator must not approve their own submitted PO.
            config(['platform.po_approve_user_ids' => $case === 'self_approval' ? [(string) $creator->id] : []]);
            $before = $snapshot();
            try {
                $service->approveOrder($po->id, $case === 'self_approval' ? $creator->id : $approver->id);
                $this->fail('Revocation and separation of duties must reject PO approval.');
            } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
                $this->assertStringContainsString('Material PO', $e->getMessage());
            }
            $this->assertSame($before, $snapshot(), $case.' must not mutate table rows');
        }
        config(['platform.po_approve_user_ids' => [(string) $approver->id]]);
        // Inject a real MariaDB persistence failure after the header update, not a mocked transaction.
        DB::unprepared("CREATE TRIGGER m3_fail_approval_history BEFORE INSERT ON mfg_material_purchase_order_histories
            FOR EACH ROW BEGIN IF NEW.action = 'APPROVED' THEN
            SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'M3 synthetic approval history failure'; END IF; END");
        $before = $snapshot();
        try {
            try {
                $service->approveOrder($po->id, $approver->id);
                $this->fail('Failed history persistence must roll back the approval header.');
            } catch (\Illuminate\Database\QueryException $e) {
                $this->assertStringContainsString('M3 synthetic approval history failure', $e->getMessage());
            }
            $this->assertSame($before, $snapshot(), 'History failure must restore every table row');
            $this->assertSame('SUBMITTED', $po->fresh()->approval_status);
            $this->assertNull($po->fresh()->approved_by);
            $this->assertNull($po->fresh()->approved_at);
        } finally {
            DB::unprepared('DROP TRIGGER m3_fail_approval_history');
        }
        $approved = $service->approveOrder($po->id, $approver->id);
        $this->assertSame('APPROVED', $approved->approval_status);
        $this->assertSame('APPROVED', $approved->status);
        $this->assertSame($approver->id, $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        $this->assertSame(['CREATED', 'SUBMITTED', 'APPROVED'], $approved->histories()->pluck('action')->all());
        $this->assertSame('2.00', $detail->fresh()->qty_ordered);
        $before = $snapshot();
        try {
            $service->approveOrder($po->id, $approver->id);
            $this->fail('Approval retry must not append another history or mutate the approved PO.');
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            $this->assertStringContainsString('Material PO', $e->getMessage());
        }
        $this->assertSame($before, $snapshot());
        $this->assertSame('000017', $material->fresh()->material_code);
        $this->assertSame('12.75', $material->fresh()->meters_per_roll);
        $this->assertSame('5.25', $material->fresh()->stock_quantity);
        $this->assertSame('1.50', $material->fresh()->average_cost);
        foreach (['journal_headers', 'journal_details', 'inventory_ledgers', 'mfg_material_ledgers'] as $table) {
            $this->assertSame(0, DB::table($table)->count(), $table);
        }
    }

    private function cleanupFixture(): void
    {
        if ($this->created && preg_match('/\Amgi_fresh_m2test_[a-f0-9]{16}\z/', (string) $this->fixture)) {
            DB::purge('m2_fixture');
            DB::connection('m2_admin')->statement('DROP DATABASE `'.$this->fixture.'`');
            $this->created = false;
        }
    }
}
