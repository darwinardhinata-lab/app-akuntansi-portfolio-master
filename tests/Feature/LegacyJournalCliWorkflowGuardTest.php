<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class LegacyJournalCliWorkflowGuardTest extends TestCase
{
    public static function commands(): array
    {
        return [['jurnal:fast'], ['jurnal:sync']];
    }

    #[DataProvider('commands')]
    public function test_bank_workflow_refuses_before_file_or_database_access(string $command): void
    {
        config(['payment_plan_v33.bank_workflow' => true, 'payment_plan_v33.erp_bank_balance' => false,
            'payment_plan_v33.posting' => false, 'platform.legacy_sync_enabled' => false]);
        DB::shouldReceive('connection', 'table', 'disableQueryLog', 'statement')->never();

        $scheme = 'f03guard'.bin2hex(random_bytes(4));
        $this->assertTrue(stream_wrapper_register($scheme, JournalCliUnreachableFile::class));
        try {
            $output = new BufferedOutput();
            $this->assertSame(1, Artisan::call($command, ['file' => $scheme.'://fixture.csv'], $output));
            $this->assertStringContainsString('Bank workflow v33 aktif', $output->fetch());
        } finally {
            stream_wrapper_unregister($scheme);
        }
    }

    #[DataProvider('commands')]
    public function test_bank_workflow_off_preserves_legacy_missing_file_handling(string $command): void
    {
        config(['payment_plan_v33.bank_workflow' => false, 'payment_plan_v33.erp_bank_balance' => true,
            'platform.legacy_sync_enabled' => false]);
        DB::shouldReceive('connection', 'table', 'statement')->never();
        DB::shouldReceive('disableQueryLog')->times($command === 'jurnal:sync' ? 1 : 0);

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'f03-missing-'.bin2hex(random_bytes(8)).'.csv';
        $output = new BufferedOutput();
        $this->assertSame(0, Artisan::call($command, ['file' => $path], $output));
        $this->assertStringContainsString('File tidak ditemukan', $output->fetch());
    }
}

final class JournalCliUnreachableFile
{
    public $context;

    public function url_stat(string $path, int $flags): never
    {
        throw new \LogicException('The guarded command must not stat the input file.');
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): never
    {
        throw new \LogicException('The guarded command must not open the input file.');
    }
}