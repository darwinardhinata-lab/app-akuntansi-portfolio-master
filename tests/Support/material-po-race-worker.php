<?php

use App\Modules\Manufacturing\Services\MaterialProcurementService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
[$connection, $request, $supplier, $line, $header, $orderToEdit] = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (getenv('MGI_M2_MYSQL_TESTS') !== '1' || $connection['host'] !== '127.0.0.1' || (int) $connection['port'] === 3306
    || ! preg_match('/\Amgi_fresh_m2test_[a-f0-9]{16}\z/', $connection['database'])) {
    exit(2);
}
config(['database.connections.race' => $connection, 'platform.po_create_user_ids' => [(string) $header['created_by']]]);
DB::setDefaultConnection('race');
echo 'connection=' . DB::selectOne('SELECT CONNECTION_ID() AS id')->id . PHP_EOL;
try {
    $service = $app->make(MaterialProcurementService::class);
    if ($orderToEdit === null) {
        $service->createOrderFromRequest($request, $supplier, [$line], $header);
    } else {
        $service->updateOrder($orderToEdit, ['po_date' => $header['po_date']], [$line], $header['created_by']);
    }
    exit(0);
} catch (RuntimeException $e) {
    fwrite(STDERR, $e->getMessage());
    exit(str_contains($e->getMessage(), 'sisa detail PR') ? 3 : 1);
}