<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
[$connection, $actor, $payload] = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (getenv('MGI_M2_MYSQL_TESTS') !== '1' || $connection['host'] !== '127.0.0.1' || (int) $connection['port'] === 3306
    || ! preg_match('/\Amgi_fresh_m2test_[a-f0-9]{16}\z/', $connection['database'])) {
    exit(2);
}
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
config([
    'database.connections.race' => $connection, 'session.driver' => 'array',
    'platform.order_company_scope_enabled' => true, 'platform.legacy_sync_enabled' => false, 'customs.enabled' => false,
]);
DB::setDefaultConnection('race');
echo 'connection='.DB::selectOne('SELECT CONNECTION_ID() AS id')->id.PHP_EOL;
Auth::guard('web')->setUser(User::findOrFail($actor));
$request = Request::create(route('mfg.auxiliary-material-issues.store'), 'POST', $payload);
$kernel = $app->make(HttpKernel::class);
$response = $kernel->handle($request);
$error = $request->hasSession() ? $request->session()->get('error', '') : '';
$kernel->terminate($request, $response);
echo $response->getStatusCode().' '.$error.PHP_EOL;
exit($response->isRedirect() && str_contains($error, 'tidak mencukupi') ? 3 : 1);