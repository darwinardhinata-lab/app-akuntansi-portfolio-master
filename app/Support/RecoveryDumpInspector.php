<?php

namespace App\Support;

use InvalidArgumentException;

class RecoveryDumpInspector
{
    /** @var list<string> */
    public const REQUIRED_ACCOUNTING_TABLES = ['accounts', 'journal_headers', 'journal_details'];

    /**
     * Read a SQL dump without executing any of its content.
     *
     * @return array{path: string, size: int, tables: array<string, array{structure: bool, rows: bool}>}
     */
    public static function inspect(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new InvalidArgumentException("Dump SQL tidak dapat dibaca: {$path}");
        }

        $tables = [];
        foreach (self::REQUIRED_ACCOUNTING_TABLES as $table) {
            $tables[$table] = ['structure' => false, 'rows' => false];
        }

        $file = new \SplFileObject($path, 'r');
        while (! $file->eof()) {
            $line = (string) $file->fgets();

            foreach (self::REQUIRED_ACCOUNTING_TABLES as $table) {
                $quotedTable = preg_quote($table, '/');

                if (preg_match("/\\bCREATE\\s+TABLE(?:\\s+IF\\s+NOT\\s+EXISTS)?\\s+`?{$quotedTable}`?/i", $line)) {
                    $tables[$table]['structure'] = true;
                }

                if (preg_match("/\\bINSERT\\s+INTO\\s+`?{$quotedTable}`?\\b/i", $line)) {
                    $tables[$table]['rows'] = true;
                }
            }
        }

        return [
            'path' => realpath($path) ?: $path,
            'size' => filesize($path) ?: 0,
            'tables' => $tables,
        ];
    }

    /**
     * @param  array{tables: array<string, array{structure: bool, rows: bool}>}  $report
     * @return list<string>
     */
    public static function validationErrors(array $report, bool $requireJournals = false): array
    {
        $errors = [];

        foreach (self::REQUIRED_ACCOUNTING_TABLES as $table) {
            if (! ($report['tables'][$table]['structure'] ?? false)) {
                $errors[] = "Struktur tabel wajib `{$table}` tidak ditemukan.";
            }
        }

        if (! ($report['tables']['accounts']['rows'] ?? false)) {
            $errors[] = 'Dump tidak memiliki data `accounts`; COA tidak dapat diverifikasi.';
        }

        if ($requireJournals) {
            foreach (['journal_headers', 'journal_details'] as $table) {
                if (! ($report['tables'][$table]['rows'] ?? false)) {
                    $errors[] = "Dump tidak memiliki data `{$table}` dan DILARANG digunakan untuk pemulihan jurnal.";
                }
            }
        }

        return $errors;
    }
}
