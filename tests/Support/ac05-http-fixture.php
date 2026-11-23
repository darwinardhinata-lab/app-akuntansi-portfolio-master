<?php

use App\Models\User;
use App\Modules\PaymentPlan\Services\BankPaymentControl;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\PaymentPlan\Services\WorkflowStore;
use App\Modules\Platform\Models\Company;
use App\Modules\Platform\Models\Role;
use App\Support\PostingService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$identity = DB::selectOne('SELECT DATABASE() AS db, @@port AS port, @@datadir AS dir, CONNECTION_ID() AS connection, VERSION() AS version');
if (!$app->environment('testing') || config('database.default') !== 'mysql'
    || config('database.connections.mysql.host') !== '127.0.0.1' || config('database.connections.mysql.url')
    || !in_array($identity->db, ['ac05_before', 'ac05_after'], true) || (string) $identity->port !== getenv('DB_PORT')
    || realpath($identity->dir) !== realpath(getenv('AC05_DATADIR')) || !str_contains($identity->version, 'MariaDB')) {
    throw new RuntimeException('Not the owned disposable AC05 MariaDB');
}
$mode = $argv[1];
$id = '404533da-f658-4f80-afc9-7e438ed4a529';
if ($mode === 'intent-conflict') {
    $event = DB::table('ppl_payment_events')->where('request_id', $id)->first();
    $frozen = json_decode($event->snapshot, true, 512, JSON_THROW_ON_ERROR);
    $lines = $frozen['accountingApproval']['basis']['lines'];
    foreach ($lines as &$line) $line['amount'] = '100.26';
    unset($line);
    try {
        PostingService::post(['transaction_date'=>$frozen['paidDate'], 'journal_type'=>'PAYMENT',
            'source_doc_no'=>$frozen['doc'], 'notes'=>'Payment event '.$event->id], $lines, null, null,
            ['idempotency_key'=>'ppl:paid:'.$event->id, 'source_type'=>'PPL_PAYMENT_EVENT',
                'source_id'=>$event->id, 'actor_id'=>(string) $event->confirmed_by]);
    } catch (RuntimeException $error) {
        if ($error->getMessage() !== 'Idempotency key payload conflict.') throw $error;
        echo "intent_amount_conflict\n";
        exit;
    }
    throw new RuntimeException('Changed posting amount accepted');
}
if ($mode === 'migrate') {
    $exit = $kernel->call('migrate', ['--force'=>true]);
    echo $kernel->output();
    exit($exit);
}
if ($mode === 'snapshot') {
    $event = DB::table('ppl_payment_events')->where('request_id',$id)->first();
    $allocation = DB::table('ppl_ap_allocations')->where('request_id',$id)->first();
    $company = (int) $event->company_id;
    $state = DB::transaction(static function () use ($id,$event,$allocation,$company): array {
        $bank = (new BankPaymentControl())->lockContext($company)['_erp_projection']['idr3'];
        $bill = (new PurchaseBillAllocation())->source((int) $allocation->purchase_bill_id, $company);
        return [
            'bank'=>$bank, 'residual'=>$bill['residual'], 'bill'=>DB::table('purchase_bills')->find($allocation->purchase_bill_id),
            'reservation'=>DB::table('ppl_reservations')->where('request_id',$id)->first(), 'allocation'=>$allocation,
            'event'=>$event, 'request'=>DB::table('ppl_requests')->where('id',$id)->first(),
            'journals'=>DB::table('journal_headers')->orderBy('journal_id')->get(),
            'lines'=>DB::table('journal_details')->orderBy('id')->get(),
            'intent'=>DB::table('accounting_posting_intents')->where('idempotency_key','ppl:paid:'.$event->id)->first(),
            'ack'=>DB::table('ppl_events')->where('mutation_id','ac05-http-post')->first(),
        ];
    });
    $engines = DB::select('SELECT DISTINCT ENGINE AS engine FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (\'ppl_requests\',\'ppl_events\',\'journal_headers\',\'accounting_posting_intents\')');
    echo json_encode(['connection'=>$identity->connection,'version'=>$identity->version,'engines'=>$engines,'state'=>$state], JSON_THROW_ON_ERROR), PHP_EOL;
    exit;
}
if ($mode !== 'setup') throw new InvalidArgumentException('Unknown AC05 fixture mode');
$company = Company::create(['code'=>'MGI','name'=>'AC05 disposable','base_currency'=>'IDR','active'=>true]);
$actors = [];
foreach (['requester','director','finance_staff','maker','general_manager','accountant'] as $role) {
    $user = User::factory()->create(['email'=>$role.'@example.test','password'=>Illuminate\Support\Facades\Hash::make(getenv('AC05_LOGIN_PASSWORD'))]);
    $user->companies()->attach($company->id, ['is_default'=>true]);
    $assigned = Role::create(['code'=>'PPL_'.strtoupper($role),'name'=>$role]);
    $user->platformRoles()->attach($assigned->id,['company_id'=>$company->id]);
    $actors[$role] = ['id'=>(string) $user->id,'name'=>$user->name,'email'=>$user->email,'department'=>'','company_id'=>$company->id,'roles'=>[$role]];
}
foreach (['610001'=>'DEBET','111003'=>'DEBET','211001'=>'KREDIT'] as $code=>$normal) {
    DB::table('accounts')->insert(['account_code'=>$code,'account_name'=>'AC05 '.$code,'coa_type'=>'Other','normal_balance'=>$normal,'report_pos'=>'NERACA']);
}
foreach (['expense'=>'610001','bank.idr3'=>'111003'] as $key=>$code) {
    DB::table('company_coa_mappings')->insert(['company_id'=>$company->id,'semantic_key'=>'ppl.'.$key,'account_code'=>$code,'active'=>true]);
}
$post = static fn ($date,$debit,$amount) => PostingService::post(['transaction_date'=>$date],[
    ['account_code'=>$debit,'position'=>'DEBET','amount'=>$amount],['account_code'=>'211001','position'=>'KREDIT','amount'=>$amount],
]);
$post('2025-12-31','111003','500.00');
$source = $post('2026-01-01','610001','200.50');
$bill = DB::table('purchase_bills')->insertGetId(['bill_number'=>'AC05-HTTP-AP','bill_date'=>'2026-01-01','vendor_name'=>'Disposable supplier','credit_account'=>'211001','grand_total'=>'200.50','journal_id'=>$source->getKey(),'payment_status'=>'UNPAID']);
DB::table('ppl_financial_contexts')->insert(['company_id'=>$company->id,'settings'=>json_encode(['version'=>1,'accounts'=>[['id'=>'idr3','name'=>'Disposable bank','number'=>'TEST','currency'=>'IDR','payment'=>true,'opening'=>'500.00','asOf'=>'2026-01-01']],'erp_cutoff'=>'2026-01-01','receipts'=>[],'cash'=>['opening'=>null,'asOf'=>null],'cashReceipts'=>[]],JSON_THROW_ON_ERROR)]);
$created = app(App\Modules\PaymentPlan\Services\BankRequestStore::class)->create(['id'=>$id,'payee'=>'Fixture','subject'=>['AP','AP','AP'],'purpose'=>['AP','AP','AP'],'amount'=>'100.25','recipientBank'=>'Fixture','recipientAccount'=>'TEST','due'=>'2026-10-27','purchaseBillId'=>$bill],$actors['requester']);
$payload = $created['request'];
// Synthetic attachment prerequisites only; this is not a storage acceptance test.
$payload['attachments'] = [['id'=>'test-settlement','kind'=>'settlement'],['id'=>'test-payment','kind'=>'payment']];
DB::table('ppl_requests')->where('id',$id)->update(['payload'=>json_encode($payload,JSON_THROW_ON_ERROR)]);
$store = new WorkflowStore(fn () => new DateTimeImmutable('2026-10-27T12:00:00Z'));
foreach ([['submit','requester',[]],['approve_request','director',['checked'=>true]],
    ['prepare_settlement','finance_staff',['checked'=>true,'base'=>'100.25','coa'=>'610001','sourceId'=>'idr3']],
    ['approve_settlement','director',['checked'=>true]],['mark_maker','maker',['reference'=>'AC05-MAKER']],
    ['release_gm','general_manager',['checked'=>true,'reference'=>'AC05-GM']],
    ['confirm_paid','finance_staff',['checked'=>true,'reference'=>'AC05-PAID','paidDate'=>'2026-10-27']]] as $i=>[$action,$role,$input]) {
    $store->action($id,$i+1,'ac05-fixture-'.$i,$action,$input,$actors[$role]);
}