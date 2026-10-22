<?php

namespace App\Observers;

use App\Jobs\TranslateJournalNotes;
use App\Models\JournalHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class JournalTranslationObserver
{
    public function saved(JournalHeader $journal): void
    {
        if (!config('libretranslate.enabled') || !$journal->wasChanged('notes') && !$journal->wasRecentlyCreated
            || trim((string) $journal->notes) === '') {
            return;
        }
        $id = (string) $journal->getKey();
        DB::afterCommit(function () use ($id) {
            try {
                // Never execute translation inline on the accounting request.
                if (config('libretranslate.connection') !== 'translations') {
                    throw new \RuntimeException('An asynchronous translation queue is required.');
                }
                TranslateJournalNotes::dispatch($id);
            } catch (Throwable $exception) {
                Log::warning('Journal translation dispatch failed.', ['journal_id' => $id, 'exception_type' => get_class($exception)]);
            }
        });
    }
}