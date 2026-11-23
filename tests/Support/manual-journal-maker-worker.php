<?php

require getcwd().'/vendor/autoload.php';

/** Normal HTTP test client, including route middleware, real auth and controller dispatch. */
final class ManualJournalMakerWorker extends \Tests\TestCase
{
    public function execute(array $f): void
    {
        $this->setUp();
        try {
            if (!preg_match('/\Aaudit_manual_writer_[a-f0-9]{16}\z/', $f['connection']['database'])) throw new RuntimeException('Disposable schema required');
            config($f['flags'] + ['database.connections.race_worker' => $f['connection'], 'database.default' => 'race_worker']);
            \Illuminate\Support\Facades\DB::setDefaultConnection('race_worker');
            $db = \Illuminate\Support\Facades\DB::connection();
            $this->actingAs(\App\Models\User::findOrFail($f['actor']));
            echo 'READY:'.$db->selectOne('SELECT CONNECTION_ID() AS id')->id.PHP_EOL;
            flush();
            if ($f['hold']) $db->beginTransaction();
            $response = match ($f['action']) {
                'maker' => $this->postJson(route('payment-plan-v33.action'), $f['payload']),
                'manual' => $this->post(route('jurnal.store'), $f['payload']),
                'fx' => $this->post(route('jurnal.store'), $f['payload']),
                'delete' => $this->delete(route('jurnal.destroy', [$f['payload']['journal_id']])),
                'edit' => $this->put(route('jurnal.update', [$f['payload']['journal_id']]), $f['payload']),
                'bulk' => $this->delete(route('jurnal.massDestroy'), $f['payload']),
                'opening' => $this->post(route('account.store_opening_balance'), $f['payload']),
                'opening-fx' => $this->post(route('account.store_opening_balance'), $f['payload']),
                'opening-csv' => $this->post(route('account.import_opening_balance'), [
                    'tanggal_saldo' => $f['payload']['transaction_date'],
                    'file' => \Illuminate\Http\UploadedFile::fake()->createWithContent('opening.csv',
                        "Account,Name,Debit,Credit\n610001,Other,200.00,0\n310001,Equity,0,200.00\n"),
                ]),
                'csv', 'xlsx' => $this->importReplacement($f['action']),
            };
            $result = ['status' => $response->status(), 'body' => $f['action'] === 'maker' ? $response->json() : null,
                'error' => session('error') ?? session('errors')?->first('currency_code'), 'success' => session('success')];
            echo 'RESULT:'.json_encode($result, JSON_THROW_ON_ERROR).PHP_EOL;
            flush();
            if ($f['hold']) {
                echo "UNCOMMITTED\n";
                flush();
                $deadline = microtime(true) + 90;
                while (!file_exists($f['release']) && microtime(true) < $deadline) usleep(20000);
                if (!file_exists($f['release'])) throw new RuntimeException('Observer did not release holder');
                $db->commit();
                echo "COMMITTED\n";
            }
        } finally {
            while (\Illuminate\Support\Facades\DB::transactionLevel() > 0) \Illuminate\Support\Facades\DB::rollBack();
            // This standalone subprocess exits immediately; PHPUnit's runner registry is absent.
            // Closing its PDO connections releases resources without invoking runner-only teardown.
            \Illuminate\Support\Facades\DB::disconnect();
        }
    }

    private function importReplacement(string $format): \Illuminate\Testing\TestResponse
    {
        $rows = [
            ['Tanggal', 'No Jurnal', 'No Bukti', 'Deskripsi', 'Col5', 'Col6', 'Nilai Debet', 'Nilai Kredit', 'Akun'],
            ['2026-01-03', 'RACE-RECEIPT', 'TEST', 'Reclassify', '', '', '200.00', '0.00', '610001'],
            ['2026-01-03', 'RACE-RECEIPT', 'TEST', 'Reclassify', '', '', '0.00', '200.00', '310001'],
        ];
        if ($format === 'xlsx') {
            $book = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $book->getActiveSheet()->fromArray($rows);
            ob_start();
            (new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($book))->save('php://output');
            $content = ob_get_clean();
        } else {
            $content = implode("\n", array_map(fn ($row) => implode(',', $row), $rows))."\n";
        }
        return $this->post(route('jurnal.import'), ['file_excel' => \Illuminate\Http\UploadedFile::fake()->createWithContent('race.'.$format, $content)]);
    }
}

try {
    (new ManualJournalMakerWorker('execute'))->execute(json_decode(getenv('MANUAL_MAKER_FIXTURE'), true, 512, JSON_THROW_ON_ERROR));
} catch (Throwable $e) {
    fwrite(STDERR, (string) $e);
    exit(2);
}
