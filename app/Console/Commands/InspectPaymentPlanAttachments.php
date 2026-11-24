<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class InspectPaymentPlanAttachments extends Command
{
    protected $signature = 'ppl:attachments-inspect';

    protected $description = 'Read-only counts for Payment Plan attachment rows and private local files (quiesce writers first)';

    public function handle(): int
    {
        if (config('filesystems.disks.local.driver') !== 'local') {
            $this->error('Only the private local disk is supported.');

            return self::FAILURE;
        }

        $counts = array_fill_keys(['valid', 'missing_file', 'hash_mismatch', 'invalid_key', 'unreadable_file', 'file_without_row'], 0);
        $referenced = [];

        // Not an atomic DB/filesystem snapshot; run only on an approved, quiesced target.
        // Include archived rows: archiving does not remove their private evidence.
        try {
            $disk = Storage::disk('local');
            $root = realpath($disk->path(''));
            foreach (DB::table('ppl_attachments')->select('id', 'company_id', 'request_id', 'reconciliation_id', 'storage_key', 'sha256')->cursor() as $row) {
                $key = $this->expectedKey($row);
                if ($key === null || $row->storage_key !== $key) {
                    $counts['invalid_key']++;

                    continue;
                }

                $referenced[$key] = true;
                $counts[$this->fileStatus($disk, $root, $key, $row->sha256)]++;
            }

            foreach ($disk->allFiles('payment-plan') as $key) {
                if (! isset($referenced[$key])) {
                    $counts['file_without_row']++;
                }
            }
        } catch (\Exception) {
            $this->error('Attachment inventory could not complete. No result was produced.');

            return self::FAILURE;
        }

        $this->line(json_encode(['counts' => $counts, 'read_only' => true], JSON_THROW_ON_ERROR));

        return array_sum($counts) === $counts['valid'] ? self::SUCCESS : self::FAILURE;
    }

    private function expectedKey(object $row): ?string
    {
        if (! ctype_digit((string) $row->company_id) || ! Str::isUuid($row->id)) {
            return null;
        }

        if ($row->reconciliation_id === null && is_string($row->request_id) && Str::isUuid($row->request_id)) {
            return "payment-plan/{$row->company_id}/{$row->request_id}/{$row->id}";
        }

        if ($row->request_id === null && ctype_digit((string) $row->reconciliation_id)) {
            return "payment-plan/{$row->company_id}/reconciliation/{$row->reconciliation_id}/{$row->id}";
        }

        return null;
    }

    private function fileStatus(FilesystemAdapter $disk, string|false $root, string $key, string $expectedHash): string
    {
        if (! $disk->fileExists($key)) {
            return 'missing_file';
        }

        $path = realpath($disk->path($key));
        if ($root === false || $path === false || ! str_starts_with($path, rtrim($root, '/\\').DIRECTORY_SEPARATOR) || ! is_file($path)) {
            return 'unreadable_file';
        }

        $hash = @hash_file('sha256', $path);

        return $hash === false ? 'unreadable_file' : (hash_equals($expectedHash, $hash) ? 'valid' : 'hash_mismatch');
    }
}