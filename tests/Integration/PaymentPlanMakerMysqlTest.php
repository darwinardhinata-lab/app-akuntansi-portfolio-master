<?php

namespace Tests\Integration;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use App\Modules\PaymentPlan\Services\WorkflowStore;
use Symfony\Component\Process\Process;

/** Disposable schema only. Real workers, financial fence and connection-loss rollback. */
class PaymentPlanMakerMysqlTest extends \Tests\Feature\PaymentPlanBankFlowTest
{
    private ?string $isolatedDatabase = null;

    public static function accounts(): array { return ['same bank' => ['idr3'], 'cross bank' => ['idr4']]; }

    #[\PHPUnit\Framework\Attributes\DataProvider('accounts')]
    public function test_ap_makers_and_killed_transaction_recover(string $secondAccount): void
    {
        if ($secondAccount === 'idr4') $this->markTestSkipped('Cross-bank IDR race blocked: PaymentBalances::source permits idr3 only for IDR. A second approved IDR mandate/domain contract is required; test-only config cannot authorize it.');
        parent::test_bank_decisions_reserve_consume_and_post_exactly_once();
        $company = DB::table('companies')->value('id');
        config(['payment_plan_v33.ap_allocations' => true]);
        if ($secondAccount === 'idr4') {
            config(['payment_plan_v33.payment_accounts' => ['idr3' => 'IDR', 'idr4' => 'IDR']]);
            $context = DB::table('ppl_financial_contexts')->where('company_id', $company)->first();
            $settings = json_decode($context->settings, true);
            $bank = collect($settings['accounts'])->firstWhere('id', 'idr3');
            $bank['id'] = 'idr4'; $bank['number'] = 'TEST-SECOND'; $bank['name'] = 'Synthetic second bank';
            $settings['accounts'][] = $bank;
            DB::table('ppl_financial_contexts')->where('company_id', $company)->update(['settings' => json_encode($settings)]);
            DB::table('accounts')->insert(['account_code'=>'111004','account_name'=>'Synthetic second bank','coa_type'=>'Other','normal_balance'=>'DEBET','report_pos'=>'NERACA']);
            DB::table('company_coa_mappings')->insert(['company_id'=>$company,'semantic_key'=>'ppl.bank.idr4','account_code'=>'111004','active'=>true]);
        }
        DB::table('accounts')->insert(['account_code'=>'211001','account_name'=>'Synthetic AP','coa_type'=>'Other','normal_balance'=>'KREDIT','report_pos'=>'NERACA']);
        $journal = \App\Support\PostingService::post(['transaction_date'=>'2026-10-27','source_doc_no'=>'RACE-INVOICE'], [
            ['account_code'=>'610001','position'=>'DEBET','amount'=>'100.00'], ['account_code'=>'211001','position'=>'KREDIT','amount'=>'100.00'],
        ]);
        $bill = DB::table('purchase_bills')->insertGetId(['bill_number'=>'RACE-INVOICE','bill_date'=>'2026-10-27','vendor_name'=>'Synthetic race supplier','credit_account'=>'211001','grand_total'=>'100.00','journal_id'=>$journal->getKey()]);
        $actors = [];
        foreach (['req'=>'requester','dir'=>'director','fs'=>'finance_staff','mk'=>'maker'] as $key=>$role) {
            $user = \App\Models\User::factory()->create();
            $actors[$key] = ['id'=>(string)$user->id,'name'=>$user->name,'email'=>$user->email,'company_id'=>$company,'department'=>'purchasing','roles'=>[$role]];
        }
        $store = new WorkflowStore(fn()=>new \DateTimeImmutable('2026-10-27T12:00:00Z'));
        $requests = [];
        foreach (['idr3', $secondAccount] as $account) {
            $id = (string) \Illuminate\Support\Str::uuid();
            (new \App\Modules\PaymentPlan\Services\BankRequestStore())->create(['id'=>$id,'payee'=>'Synthetic','subject'=>['Uji','Test','测试'],'purpose'=>['Uji','Test','测试'],'amount'=>'60.00','recipientBank'=>'TEST','recipientAccount'=>'TEST','due'=>'2026-10-29','purchaseBillId'=>$bill],$actors['req']);
            $version = 1;
            foreach ([['submit','req',[]],['approve_request','dir',['checked'=>true]]] as [$action,$actor,$input]) {
                $store->action($id,$version++,$id.$action,$action,$input,$actors[$actor]);
            }
            $row = DB::table('ppl_requests')->where('id',$id)->first();
            $payload = json_decode($row->payload,true);
            // Synthetic domain fixture attachment; not storage acceptance.
            $payload['attachments'][]=['id'=>'synthetic-settlement','kind'=>'settlement'];
            DB::table('ppl_requests')->where('id',$id)->update(['payload'=>json_encode($payload)]);
            $store->action($id,$version++,$id.'prepare','prepare_settlement',['checked'=>true,'base'=>'60.00','coa'=>'211001','sourceId'=>$account],$actors['fs']);
            $store->action($id,$version++,$id.'approve','approve_settlement',['checked'=>true],$actors['dir']);
            $requests[] = [$id,$version];
        }
        [$first,$version] = $requests[0]; [$second,$secondVersion] = $requests[1];
        // Terminate a real worker after all maker writes, but before outer commit.
        $crash=$this->worker($second,$secondVersion,$actors['mk'],true);
        try { $crash->start();$this->waitFor($crash,'UNCOMMITTED');$crash->stop(0); }
        finally { if($crash->isRunning())$crash->stop(0); }
        $this->assertDatabaseMissing('ppl_reservations',['request_id'=>$second]);
        $this->assertDatabaseMissing('ppl_ap_allocations',['request_id'=>$second]);
        $this->assertSame('settlement_approved',DB::table('ppl_requests')->where('id',$second)->value('status'));
        $this->assertDatabaseMissing('ppl_events',['request_id'=>$second,'mutation_id'=>'worker-maker']);
        $worker = $this->worker($second,$secondVersion,$actors['mk'], false);
        DB::beginTransaction();
        try {
            (new \App\Modules\PaymentPlan\Services\BankPaymentControl())->lockContext($company);
            $worker->start();
            $this->waitFor($worker,'READY');
            usleep(200000); $this->assertTrue($worker->isRunning());
            $store->action($first,$version,'race-first','mark_maker',['reference'=>'RACE-FIRST'],$actors['mk']);
            DB::commit(); $worker->wait();
            $this->assertSame(3,$worker->getExitCode(),$worker->getOutput().$worker->getErrorOutput());
            $this->assertStringContainsString('overpayment',$worker->getOutput());
            $this->assertDatabaseHas('ppl_ap_allocations',['request_id'=>$first,'status'=>'active']);
            $this->assertDatabaseMissing('ppl_reservations',['request_id'=>$second]);
        } finally {
            if($worker->isRunning())$worker->stop();
            while(DB::transactionLevel())DB::rollBack();
        }
        $result=$store->action($first,$version,'race-first','mark_maker',['reference'=>'RACE-FIRST'],$actors['mk']);
        $retry=$store->action($first,$version,'race-first','mark_maker',['reference'=>'RACE-FIRST'],$actors['mk']);
        $this->assertSame($result,$retry);
        $this->assertSame(1,DB::table('ppl_reservations')->where('request_id',$first)->count());
    }

    private function worker(string $id,int $version,array $actor,bool $crash): Process
    {
        $code=<<<'PHP'
require getcwd().'/vendor/autoload.php';
$app=require getcwd().'/bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$cfg=json_decode(getenv('MAKER_DB'),true);if(!preg_match('/\Aaudit_maker_test_[a-f0-9]{16}\z/',$cfg['database']))exit(9);
config(['database.connections.maker_worker'=>$cfg,'database.default'=>'maker_worker','payment_plan_v33.enabled'=>true,'payment_plan_v33.bank_workflow'=>true,'payment_plan_v33.reservations'=>true,'payment_plan_v33.ap_allocations'=>true,'payment_plan_v33.payment_accounts'=>['idr3'=>'IDR','idr4'=>'IDR']]);
Illuminate\Support\Facades\DB::setDefaultConnection('maker_worker');
echo "READY\n";flush();
try {
if(getenv('MAKER_CRASH')==='1')Illuminate\Support\Facades\DB::beginTransaction();
(new App\Modules\PaymentPlan\Services\WorkflowStore(fn()=>new DateTimeImmutable('2026-10-27T12:00:00Z')))->action(getenv('MAKER_ID'),(int)getenv('MAKER_VERSION'),'worker-maker','mark_maker',['reference'=>'WORKER'],json_decode(getenv('MAKER_ACTOR'),true));
if(getenv('MAKER_CRASH')==='1'){echo "UNCOMMITTED\n";flush();sleep(60);}
exit(0);
}catch(Throwable $e){echo $e->getMessage();exit(3);}
PHP;
        return new Process([PHP_BINARY,'-r',$code],base_path(),['MAKER_DB'=>json_encode(config('database.connections.maker_test')),'MAKER_ID'=>$id,'MAKER_VERSION'=>(string)$version,'MAKER_ACTOR'=>json_encode($actor),'MAKER_CRASH'=>$crash?'1':'0'],null,30);
    }

    private function waitFor(Process $worker,string $marker): void
    {
        $deadline=microtime(true)+15;
        while($worker->isRunning() && !str_contains($worker->getOutput(),$marker) && microtime(true)<$deadline)usleep(20000);
        $this->assertStringContainsString($marker,$worker->getOutput(),$worker->getErrorOutput());
    }

    public function refreshDatabase(): void
    {
        if(getenv('AP_MYSQL_TESTS')!=='1')$this->markTestSkipped('Opt-in disposable MySQL maker test.');
        $this->isolatedDatabase='audit_maker_test_'.bin2hex(random_bytes(8));
        $config=['driver'=>'mysql','host'=>getenv('MGI_TEST_HOST')?:'127.0.0.1','port'=>getenv('MGI_TEST_PORT')?:3306,'username'=>getenv('MGI_TEST_USER')?:'root','password'=>getenv('MGI_TEST_PASSWORD')?:'','database'=>'information_schema','charset'=>'utf8mb4','collation'=>'utf8mb4_unicode_ci','prefix'=>'','strict'=>true];
        config(['database.connections.maker_admin'=>$config]);DB::connection('maker_admin')->statement('CREATE DATABASE `'.$this->isolatedDatabase.'`');
        $config['database']=$this->isolatedDatabase;config(['database.connections.maker_test'=>$config,'database.default'=>'maker_test']);DB::setDefaultConnection('maker_test');
        $this->assertSame(0,Artisan::call('migrate',['--database'=>'maker_test','--force'=>true]),Artisan::output());
    }

    protected function tearDown(): void
    {
        try { if($this->isolatedDatabase && preg_match('/\Aaudit_maker_test_[a-f0-9]{16}\z/',$this->isolatedDatabase)) {
            while(DB::connection('maker_test')->transactionLevel())DB::connection('maker_test')->rollBack();
            DB::purge('maker_test');DB::connection('maker_admin')->statement('DROP DATABASE `'.$this->isolatedDatabase.'`');
        }} finally {parent::tearDown();}
    }
}