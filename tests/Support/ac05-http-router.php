<?php

// Router for the loopback PHP test server only. No production registration.
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);
require $root.'/vendor/autoload.php';
$app = require $root.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();
$request = Request::capture();
if (!$app->environment('testing') || $request->server('REMOTE_ADDR') !== '127.0.0.1') {
    http_response_code(403);
    return;
}
$mode = getenv('AC05_KILL_MODE');
if ($request->is('payment-plan/api/action') && $request->isMethod('POST') && $request->input('mutationId') === 'ac05-http-post') {
    $park = static function (): never {
        file_put_contents(getenv('AC05_MARKER'), json_encode([
            'pid'=>getmypid(), 'level'=>DB::transactionLevel(), 'connection'=>DB::selectOne('SELECT CONNECTION_ID() AS id')->id,
        ], JSON_THROW_ON_ERROR), LOCK_EX);
        sleep(45);
        throw new RuntimeException('AC05 parent did not terminate the parked worker');
    };
    if ($mode === 'before') {
        DB::listen(static function ($event) use ($park): void {
            if (str_starts_with(strtolower($event->sql), 'insert into `ppl_events`')) $park();
        });
    } elseif ($mode === 'after') {
        $ackInserted = false;
        DB::listen(static function ($event) use (&$ackInserted): void {
            if (str_starts_with(strtolower($event->sql), 'insert into `ppl_events`')) $ackInserted = true;
        });
        $app['events']->listen(TransactionCommitted::class, static function ($event) use ($park, &$ackInserted): void {
            if ($ackInserted && $event->connection->transactionLevel() === 0) $park();
        });
    }
}
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);