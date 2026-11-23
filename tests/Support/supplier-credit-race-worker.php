<?php

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$fixture = json_decode(getenv('CREDIT_RACE_FIXTURE'), true, 512, JSON_THROW_ON_ERROR);
$connection = $fixture['connection'];
if (!preg_match('/\Aaudit_credit_void_[a-f0-9]{16}\z/', $connection['database'])) exit(9);
config(['database.connections.credit_worker' => $connection, 'database.default' => 'credit_worker',
    'platform.filament_enabled' => true, 'platform.order_company_scope_enabled' => true,
    'platform.posting_intents_enabled' => true, 'platform.legacy_sync_enabled' => false,
    'customs.enabled' => false, 'payment_plan_v33.bank_workflow' => true]);
Illuminate\Support\Facades\DB::setDefaultConnection('credit_worker');
$session = app('session')->driver();
$session->start();
$session->put(App\Modules\Platform\Support\CompanyContext::SESSION_KEY, $fixture['company']);
request()->setLaravelSession($session);
Illuminate\Support\Facades\Auth::loginUsingId($fixture['actor']);
request()->setUserResolver(fn () => Illuminate\Support\Facades\Auth::user());
echo 'READY:'.Illuminate\Support\Facades\DB::selectOne('SELECT CONNECTION_ID() AS id')->id.PHP_EOL;
flush();
try {
    if ($fixture['action'] === 'approve') {
        app(App\Modules\PaymentPlan\Services\ApReconciliation::class)->decide($fixture['proposal'], 1, 'approve', 'Concurrent independent approval');
    } else {
        request()->merge($fixture['payload']);
        app(App\Modules\PaymentPlan\Services\SupplierCreditReversal::class)->reverse(request(), $fixture['bill'], $fixture['binding']);
    }
    echo 'ACCEPTED';
} catch (Illuminate\Validation\ValidationException $e) {
    echo json_encode($e->errors());
    exit(3);
} catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
    echo $e->getStatusCode().':'.$e->getMessage();
    exit(4);
}