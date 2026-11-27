<?php

namespace Tests\Integration;

use App\Models\Product;
use App\Models\User;
use App\Modules\PaymentPlan\Services\ErpBankBalance;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class WarehouseInboundBankCapacityMysqlTest extends TestCase
{
    public static function cases(): array
    {
        return [
            'over capacity' => ['100.01', true, null],
            'credit rounds over capacity' => ['100.005', true, null],
            'exact capacity' => ['100.00', true, '100.00'],
            'non-bank' => ['100.01', false, '100.01'],
            'same Jakarta business day' => ['100.00', true, '100.00', '2026-11-27'],
            'future non-bank' => ['100.01', false, '100.01', '2026-11-28'],
            'future bank with ERP projection off' => ['100.00', true, null, '2026-11-28', false],
            'future bank with workflow off' => ['100.00', true, '100.00', '2026-11-28', true, false],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('cases')]
    public function test_manual_receipt_capacity(string $cost, bool $useBank, ?string $posted,
        string $glDate = '2026-01-03', bool $projection = true, bool $workflow = true): void
    {
        if (getenv('AP_MYSQL_TESTS') !== '1' || getenv('MGI_TEST_PORT') !== '33394') {
            $this->markTestSkipped('Requires the dedicated disposable MariaDB runner.');
        }
        $this->assertSame(realpath(base_path('app/Http/Controllers/WarehouseController.php')),
            realpath((new \ReflectionClass(\App\Http\Controllers\WarehouseController::class))->getFileName()));
        $admin = ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 33394,
            'username' => 'root', 'password' => '', 'database' => 'information_schema',
            'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci', 'strict' => true];
        config(['database.connections.probe_admin' => $admin]);
        $identity = DB::connection('probe_admin')->selectOne('SELECT @@port AS port, @@datadir AS datadir, VERSION() AS version');
        $this->assertSame('d:/erp-recovery/m1-f03-inbound-patch-20261127-db/data',
            strtolower(rtrim(preg_replace('~[\\\\/]+~', '/', $identity->datadir), '/')));
        $this->assertStringContainsString('MariaDB', $identity->version);
        $db = 'audit_inbound_'.bin2hex(random_bytes(8));
        DB::connection('probe_admin')->statement('CREATE DATABASE `'.$db.'`');
        $config = array_replace($admin, ['database' => $db]);
        config(['database.connections.probe_writer' => $config, 'database.connections.probe_reader' => $config]);
        DB::setDefaultConnection('probe_writer');
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-27 00:30:00', 'Asia/Jakarta'));
        try {
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'probe_writer', '--force' => true]), Artisan::output());
            $inventory = '11200';
            $bankCoa = '111003';
            $offset = '610001';
            config(['coa.persediaan' => $inventory, 'payment_plan_v33.bank_workflow' => $workflow,
                'payment_plan_v33.erp_bank_balance' => $projection, 'payment_plan_v33.primary_navigation' => true,
                'payment_plan_v33.posting' => false]);
            $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic MGI', 'active' => true, 'base_currency' => 'IDR']);
            $user = User::factory()->create(['role' => 'FINANCE']);
            $user->companies()->attach($company->id, ['is_default' => true]);
            foreach ([$inventory, $bankCoa, $offset] as $code) {
                DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic '.$code,
                    'normal_balance' => $code === $offset ? 'KREDIT' : 'DEBET',
                    'coa_type' => $code === $bankCoa ? 'Cash & Bank' : 'Other', 'report_pos' => 'NERACA']);
            }
            $bank = ['id' => 'idr3', 'currency' => 'IDR'];
            $settings = ['erp_cutoff' => '2026-01-01', 'accounts' => [$bank]];
            DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
            DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3',
                'account_code' => $bankCoa, 'active' => true]);
            PostingService::post(['transaction_date' => '2026-01-01'], [
                ['account_code' => $bankCoa, 'position' => 'DEBET', 'amount' => '500.00'],
                ['account_code' => $offset, 'position' => 'KREDIT', 'amount' => '500.00'],
            ]);
            $id = (string) Str::uuid();
            DB::table('ppl_requests')->insert(['id' => $id, 'doc' => 'INBOUND-PROBE-HOLD', 'company_id' => $company->id,
                'owner_user_id' => $user->id, 'status' => 'bank_prepared', 'version' => 1, 'payload' => '{}']);
            DB::table('ppl_reservations')->insert(['request_id' => $id, 'company_id' => $company->id, 'account_key' => 'idr3',
                'currency' => 'IDR', 'amount' => '400.00', 'status' => 'active']);
            $product = Product::create(['sku' => 'F03-INBOUND-PROBE', 'name' => 'Synthetic', 'stock_quantity' => 0, 'average_cost' => 0]);
            $before = app(ErpBankBalance::class)->project($company->id, $bank, $settings);
            $this->assertSame('100.00', $before['available']);
            $formUrl = route('warehouse.inbound.create', ['tab' => 'transfer_masuk']);
            $form = $this->actingAs($user)->get($formUrl)->assertOk()->assertSee('value="'.$bankCoa.'"', false);
            $this->assertSame(1, preg_match('/name="evidence_number"[^>]+value="(IN-[^"]+)"/', $form->getContent(), $match));
            $evidence = $match[1];
            $writer = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $credit = $useBank ? $bankCoa : $offset;
            DB::enableQueryLog();
            $response = $this->from($formUrl)->post(route('warehouse.inbound.store'), [
                'evidence_number' => $evidence, 'transaction_date' => $glDate, 'description' => 'Synthetic manual receipt',
                'offset_account' => $credit, 'items' => [['product_id' => $product->id, 'qty' => 1, 'unit_cost' => $cost]],
            ]);
            $writes = array_values(array_filter(array_column(DB::getQueryLog(), 'query'),
                static fn ($sql) => preg_match('/^\s*(insert|update|delete|replace)\b/i', $sql)));
            DB::disableQueryLog();
            $this->assertSame(0, DB::connection('probe_writer')->transactionLevel());
            DB::setDefaultConnection('probe_reader');
            $reader = (int) DB::selectOne('SELECT CONNECTION_ID() AS id')->id;
            $this->assertNotSame($writer, $reader);
            $headers = DB::table('journal_headers')->where('source_doc_no', $evidence)->get([
                'journal_id', 'evidence_number', 'source_doc_no', 'transaction_type',
            ]);
            $lines = DB::table('journal_details')->whereIn('journal_id', $headers->pluck((new \App\Models\JournalHeader)->getKeyName()))
                ->orderBy('position')->get(['account_code', 'position', 'amount']);
            $after = app(ErpBankBalance::class)->project($company->id, $bank, $settings);
            $hold = DB::table('ppl_reservations')->where('request_id', $id)->first(['amount', 'status']);
            $stock = DB::table('products')->where('id', $product->id)->first(['stock_quantity', 'average_cost']);
            $ledger = DB::table('inventory_ledgers')->where('evidence_number', $evidence)->get([
                'type', 'qty', 'unit_cost', 'total_cost', 'running_qty', 'running_value', 'moving_average_cost',
            ]);
            $snapshot = compact('identity', 'writer', 'reader', 'before', 'after', 'evidence', 'headers', 'lines', 'hold', 'stock', 'ledger', 'cost', 'credit', 'writes')
                + ['form_http' => $form->status(), 'form_bank_option' => true, 'post_http' => $response->status(),
                    'location' => $response->headers->get('Location'), 'success' => session('success'),
                    'error' => session('error'), 'validation' => session('errors')?->getBag('default')->toArray()];
            if ($file = getenv('WAREHOUSE_INBOUND_EVIDENCE')) {
                $this->assertGreaterThan(0, file_put_contents($file, json_encode($snapshot, JSON_THROW_ON_ERROR).PHP_EOL, FILE_APPEND));
            }
            $this->assertSame(['amount' => '400.00', 'status' => 'active'], (array) $hold);
            if ($posted === null) {
                $response->assertRedirect($formUrl)->assertSessionHas('error', $projection ? 'funds_insufficient' : 'future_protected_bank_credit');
                $this->assertSame([], $writes);
                $this->assertSame($before, $after);
                $this->assertCount(0, $headers);
                $this->assertCount(0, $lines);
                $this->assertCount(0, $ledger);
                $this->assertEquals(0, $stock->stock_quantity);
                $this->assertEquals(0, $stock->average_cost);

                return;
            }
            $response->assertRedirect(route('warehouse.inbound'))->assertSessionHas('success');
            $this->assertCount(1, $headers);
            $this->assertSame([[$inventory, 'DEBET', $posted], [$credit, 'KREDIT', $posted]],
                $lines->map(fn ($line) => [$line->account_code, $line->position, (string) $line->amount])->all());
            $this->assertSame($useBank && $workflow ? '0.00' : '100.00', $after['available']);
            $this->assertEquals(1, $stock->stock_quantity);
            $this->assertEquals($posted, $stock->average_cost);
            $this->assertCount(1, $ledger);
            $this->assertEquals(['IN', 1, $posted, $posted, 1, $posted, $posted], array_values((array) $ledger[0]));
        } finally {
            $this->travelBack();
            DB::purge('probe_reader');
            DB::purge('probe_writer');
            DB::setDefaultConnection('sqlite');
            DB::connection('probe_admin')->statement('DROP DATABASE `'.$db.'`');
        }
    }
}