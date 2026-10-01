<?php

namespace Tests\Unit;

use App\Support\RecoveryDumpInspector;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class RecoveryDumpInspectorTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    public function test_dump_without_journal_rows_is_rejected_for_journal_recovery(): void
    {
        $report = RecoveryDumpInspector::inspect($this->dump(<<<'SQL'
CREATE TABLE `accounts` (`id` int);
INSERT INTO `accounts` VALUES (1);
CREATE TABLE `journal_headers` (`journal_id` varchar(50));
CREATE TABLE `journal_details` (`id` bigint);
SQL));

        $this->assertSame([
            'Dump tidak memiliki data `journal_headers` dan DILARANG digunakan untuk pemulihan jurnal.',
            'Dump tidak memiliki data `journal_details` dan DILARANG digunakan untuk pemulihan jurnal.',
        ], RecoveryDumpInspector::validationErrors($report, true));
    }

    public function test_complete_accounting_dump_passes_journal_recovery_preflight(): void
    {
        $report = RecoveryDumpInspector::inspect($this->dump(<<<'SQL'
CREATE TABLE `accounts` (`id` int);
INSERT INTO `accounts` VALUES (1);
CREATE TABLE `journal_headers` (`journal_id` varchar(50));
INSERT INTO `journal_headers` VALUES ('JU-001');
CREATE TABLE `journal_details` (`id` bigint);
INSERT INTO `journal_details` VALUES (1);
SQL));

        $this->assertSame([], RecoveryDumpInspector::validationErrors($report, true));
    }

    public function test_dump_without_coa_rows_is_rejected(): void
    {
        $report = RecoveryDumpInspector::inspect($this->dump(<<<'SQL'
CREATE TABLE `accounts` (`id` int);
CREATE TABLE `journal_headers` (`journal_id` varchar(50));
INSERT INTO `journal_headers` VALUES ('JU-001');
CREATE TABLE `journal_details` (`id` bigint);
INSERT INTO `journal_details` VALUES (1);
SQL));

        $this->assertContains('Dump tidak memiliki data `accounts`; COA tidak dapat diverifikasi.', RecoveryDumpInspector::validationErrors($report));
    }

    public function test_missing_or_unreadable_dump_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        RecoveryDumpInspector::inspect(__DIR__.'/does-not-exist.sql');
    }

    private function dump(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'erp-recovery-dump-');
        file_put_contents($path, $contents);
        $this->files[] = $path;

        return $path;
    }
}
