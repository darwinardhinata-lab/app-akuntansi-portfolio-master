<?php

namespace Tests\Integration;

use App\Modules\PaymentPlan\Services\ErpBankBalance;
use App\Modules\Platform\Models\Company;
use App\Support\PostingService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;

require_once __DIR__.'/ManualJournalBankCapacityMysqlTest.php';

class LegacyJournalCliBankCapacityMysqlTest extends ManualJournalBankCapacityMysqlTest
{
    public static function commands(): array
    {
        return [['jurnal:fast'], ['jurnal:sync']];
    }

    #[DataProvider('commands')]
    public function test_legacy_journal_cli_is_only_available_outside_bank_workflow(string $command): void
    {
        config(['payment_plan_v33.bank_workflow' => true, 'payment_plan_v33.erp_bank_balance' => true,
            'platform.legacy_sync_enabled' => false]);
        $company = Company::create(['code' => 'MGI', 'name' => 'Synthetic', 'active' => true, 'base_currency' => 'IDR']);
        foreach (['111101', '610001'] as $code) {
            DB::table('accounts')->insert(['account_code' => $code, 'account_name' => 'Synthetic',
                'coa_type' => 'Other', 'normal_balance' => 'DEBET', 'report_pos' => 'NERACA']);
        }
        $bank = ['id' => 'idr3', 'currency' => 'IDR'];
        $settings = ['erp_cutoff' => '2026-01-01', 'accounts' => [$bank]];
        DB::table('company_coa_mappings')->insert(['company_id' => $company->id, 'semantic_key' => 'ppl.bank.idr3',
            'account_code' => '111101', 'active' => true]);
        DB::table('ppl_financial_contexts')->insert(['company_id' => $company->id, 'settings' => json_encode($settings, JSON_THROW_ON_ERROR)]);
        PostingService::post(['transaction_date' => '2026-01-01'], [
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => '300.00'],
            ['account_code' => '610001', 'position' => 'KREDIT', 'amount' => '300.00'],
        ]);
        $receipt = PostingService::post(['transaction_date' => '2026-01-03', 'evidence_number' => 'F03-RECEIPT', 'journal_type' => 'IMPORT'], [
            ['account_code' => '111101', 'position' => 'DEBET', 'amount' => '200.00'],
            ['account_code' => '610001', 'position' => 'KREDIT', 'amount' => '200.00'],
        ]);
        $this->assertSame('F03-RECEIPT', $receipt->refresh()->evidence_number);
        $id = (string) \Illuminate\Support\Str::uuid();
        $owner = \App\Models\User::factory()->create();
        DB::table('ppl_requests')->insert(['id' => $id, 'doc' => 'F03-CLI-HOLD', 'company_id' => $company->id,
            'owner_user_id' => $owner->id, 'status' => 'bank_prepared', 'version' => 1, 'payload' => '{}']);
        DB::table('ppl_reservations')->insert(['request_id' => $id, 'company_id' => $company->id, 'account_key' => 'idr3',
            'currency' => 'IDR', 'amount' => '400.00', 'status' => 'active']);
        $balance = fn () => app(ErpBankBalance::class)->project($company->id, $bank, $settings);
        $this->assertSame('100.00', $balance()['available']);
        $path = tempnam(sys_get_temp_dir(), 'f03_cli_');
        try {
            file_put_contents($path, "Date;Journal;Evidence;Description;X;Y;Debet;Kredit;Account\n"
                ."2026-01-03;F03-RECEIPT;F03-RECEIPT;Synthetic;0;0;200;0;111101\n"
                ."2026-01-03;F03-RECEIPT;F03-RECEIPT;Synthetic;0;0;0;200;610001\n");
            $output = new BufferedOutput();
            $exit = Artisan::call($command, ['file' => $path], $output);
            $message = $output->fetch();
            config(['database.connections.writer_final' => config('database.connections.writer_test')]);
            DB::setDefaultConnection('writer_final');
            $after = $balance();
            $this->assertSame('500.00', $after['actual'], $message);
            $this->assertSame('400.00', $after['reserved']);
            $this->assertSame('100.00', $after['available']);
            $this->assertDatabaseCount('journal_headers', 2);
            $this->assertDatabaseCount('journal_details', 4);
            $this->assertDatabaseHas('journal_headers', ['journal_id' => $receipt->getKey()]);
            $this->assertDatabaseHas('ppl_reservations', ['request_id' => $id, 'amount' => '400.00', 'status' => 'active']);
            $this->assertSame(1, $exit);
            $this->assertStringContainsString('Bank workflow v33 aktif', $message);
        } finally {
            unlink($path);
            DB::setDefaultConnection('writer_test');
        }
    }

    public function refreshDatabase(): void
    {
        if (getenv('MGI_TEST_PORT') !== '33391') {
            $this->markTestSkipped('Dedicated disposable port required.');
        }
        parent::refreshDatabase();
    }
}