<?php

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class PaymentPostCrashTest extends TestCase
{
    public function test_killed_posting_worker_rolls_back_or_replays_at_the_commit_boundary(): void
    {
        $root = dirname(__DIR__, 2);
        foreach (['before' => 1, 'after' => 0] as $mode => $level) {
            $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'r1-ac05-'.bin2hex(random_bytes(8));
            self::assertTrue(mkdir($directory));
            $database = $directory.'/ac05.sqlite';
            touch($database);
            $env = ['APP_ENV'=>'testing', 'DB_CONNECTION'=>'sqlite', 'DB_DATABASE'=>$database, 'DB_URL'=>'',
                'CACHE_STORE'=>'array', 'SESSION_DRIVER'=>'array', 'APP_KEY'=>'base64:'.base64_encode(random_bytes(32))];
            $worker = static fn (string $action) => new Process([PHP_BINARY, $root.'/tests/Support/payment-post-crash-worker.php', $action, $directory], $root, $env, null, 60);
            $run = static fn (string $action) => json_decode($worker($action)->mustRun()->getOutput(), true, 512, JSON_THROW_ON_ERROR);
            $child = $worker($mode);
            try {
                $worker('migrate')->setTimeout(90)->mustRun();
                $worker('setup')->mustRun();
                $paid = $run('inspect');
                self::assertSame(['status'=>'paid', 'version'=>8], $paid['request']);
                self::assertSame([0, 0, 1, null, 0], array_values(array_slice($paid, 1)));
                $child->start();
                $deadline = microtime(true) + 20;
                do {
                    $ready = is_file($directory.'/ready') ? json_decode(file_get_contents($directory.'/ready'), true) : null;
                    if ($ready || !$child->isRunning()) break;
                    usleep(25_000);
                } while (microtime(true) < $deadline);
                self::assertIsArray($ready, $child->getErrorOutput());
                self::assertSame($mode, $ready['mode']);
                self::assertSame($level, $ready['level']);
                self::assertTrue($child->isRunning());
                $child->stop(0, 9);
                self::assertFalse($child->isRunning());
                self::assertNotSame(0, $child->getExitCode());
                self::assertSame('', $child->getOutput(), 'Killed worker must not have emitted an ACK');

                $afterKill = $run('inspect');
                if ($mode === 'before') {
                    self::assertSame($paid, $afterKill);
                } else {
                    self::assertSame(['status'=>'posted', 'version'=>9], $afterKill['request']);
                    self::assertNotEmpty($afterKill['journal']);
                }
                $retry = $run('post');
                self::assertSame('posted', $retry['status']);
                self::assertSame(9, $retry['version']);
                if ($mode === 'after') self::assertSame($afterKill['journal'], $retry['journal']);
                self::assertSame($retry, $run('post'));
                $final = $run('inspect');
                self::assertSame([1, 2, 1, $retry['journal'], 1], array_values(array_slice($final, 1)));
                if ($mode === 'after') self::assertSame($afterKill, $final);
            } finally {
                if ($child->isRunning()) $child->stop(0, 9);
                foreach (glob($directory.'/*') as $file) unlink($file);
                rmdir($directory);
            }
        }
    }
}