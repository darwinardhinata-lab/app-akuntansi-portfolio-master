<?php

use App\Models\User;
use App\Modules\PaymentPlan\Services\WorkflowStore;
use App\Modules\Platform\Models\Company;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$script, $mode, $directory] = $argv;
$database = realpath($directory.'/ac05.sqlite');
if (!$database || !$app->environment('testing') || config('database.default') !== 'sqlite'
    || config('database.connections.sqlite.url') || $database !== realpath(config('database.connections.sqlite.database'))) {
    throw new RuntimeException('Expected the dedicated AC05 SQLite database');
}
config(['payment_plan_v33.enabled' => true, 'payment_plan_v33.bank_workflow' => true,
    'payment_plan_v33.reservations' => true, 'payment_plan_v33.posting' => true, 'platform.posting_intents_enabled' => true]);
if ($mode === 'migrate') {
    $kernel = $app->make(Kernel::class);
    $status = $kernel->call('migrate', ['--force' => true]);
    echo $kernel->output();
    exit($status);
}
$store = new WorkflowStore(fn () => new DateTimeImmutable('2026-10-27T12:00:00Z'));
$id = '45e52737-dd50-4996-af16-b704e54f6375';
$actorPath = $directory.'/actor.json';
if ($mode === 'setup') {
    $company = Company::create(['code'=>'MGI', 'name'=>'AC05 disposable', 'base_currency'=>'IDR', 'active'=>true]);
    $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/integration/v33-approval-parity.json'), true, 512, JSON_THROW_ON_ERROR)['cases'][0];
    $actors = [];
    foreach (['requester','director','finance_staff','maker','general_manager','accountant'] as $role) {
        $user = User::factory()->create();
        $actors[$role] = ['id'=>(string) $user->id, 'name'=>$user->name, 'email'=>$user->email,
            'department'=>'purchasing', 'company_id'=>$company->id, 'roles'=>[$role]];
    }
    $payload = array_replace($fixture['given'], ['id'=>$id, 'createdBy'=>$actors['requester']['id'],
        'attachments'=>[['id'=>'settle', 'kind'=>'settlement'], ['id'=>'payment', 'kind'=>'payment']]]);
    DB::table('ppl_financial_contexts')->insert(['company_id'=>$company->id, 'settings'=>json_encode($fixture['settings'], JSON_THROW_ON_ERROR)]);
    DB::table('ppl_requests')->insert(['id'=>$id, 'company_id'=>$company->id, 'owner_user_id'=>$payload['createdBy'],
        'doc'=>$payload['doc'], 'status'=>'draft', 'version'=>1, 'payload'=>json_encode($payload, JSON_THROW_ON_ERROR)]);
    foreach (['expense'=>'610001', 'bank.idr3'=>'111003'] as $semantic=>$code) {
        DB::table('accounts')->insert(['account_code'=>$code, 'account_name'=>'Synthetic '.$semantic, 'coa_type'=>'Other', 'normal_balance'=>'DEBET', 'report_pos'=>'NERACA']);
        DB::table('company_coa_mappings')->insert(['company_id'=>$company->id, 'semantic_key'=>'ppl.'.$semantic, 'account_code'=>$code, 'active'=>true]);
    }
    $steps = [
        ['submit','requester',[]], ['approve_request','director',['checked'=>true]],
        ['prepare_settlement','finance_staff',['checked'=>true,'base'=>'1000000.00','coa'=>'610001','sourceId'=>'idr3']],
        ['approve_settlement','director',['checked'=>true]], ['mark_maker','maker',['reference'=>'BANK-ONE']],
        ['release_gm','general_manager',['checked'=>true,'reference'=>'RELEASE-ONE']],
        ['confirm_paid','finance_staff',['checked'=>true,'reference'=>'DEBIT-ONE','paidDate'=>'2026-10-27']],
    ];
    foreach ($steps as $index=>[$action,$role,$input]) {
        $store->action($id, $index + 1, 'ac05-step-'.$index, $action, $input, $actors[$role]);
    }
    file_put_contents($actorPath, json_encode($actors['accountant'], JSON_THROW_ON_ERROR));
    return;
}
if ($mode === 'inspect') {
    echo json_encode([
        'request'=>DB::table('ppl_requests')->where('id', $id)->first(['status','version']),
        'headers'=>DB::table('journal_headers')->count(), 'details'=>DB::table('journal_details')->count(),
        'paidEvents'=>DB::table('ppl_payment_events')->where('request_id',$id)->count(),
        'journal'=>DB::table('ppl_payment_events')->where('request_id',$id)->value('journal_id'),
        'acks'=>DB::table('ppl_events')->where('mutation_id','ac05-post')->count(),
    ], JSON_THROW_ON_ERROR), PHP_EOL;
    return;
}
$pause = static function () use ($directory, $mode): never {
    file_put_contents($directory.'/ready', json_encode(['pid'=>getmypid(), 'mode'=>$mode, 'level'=>DB::transactionLevel()], JSON_THROW_ON_ERROR), LOCK_EX);
    sleep(30);
    throw new RuntimeException('Parent did not kill the parked worker');
};
if ($mode === 'before') {
    DB::listen(static function ($query) use ($pause): void {
        if (str_starts_with(strtolower($query->sql), 'insert into "ppl_events"')) {
            $pause();
        }
    });
}
$result = $store->action($id, 8, 'ac05-post', 'post_external', ['checked'=>true,'reference'=>'ERP-ACK'],
    json_decode(file_get_contents($actorPath), true, 512, JSON_THROW_ON_ERROR));
if ($mode === 'after') {
    $pause();
}
echo json_encode(['journal'=>$result['request']['journal']['journalId'],'status'=>$result['request']['status'],'version'=>$result['version']], JSON_THROW_ON_ERROR), PHP_EOL;