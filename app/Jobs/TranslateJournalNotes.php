<?php

namespace App\Jobs;

use App\Services\LibreTranslateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class TranslateJournalNotes implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;
    public int $timeout = 150;
    public int $backoff = 60;

    public function __construct(public string $journalId)
    {
        $this->onConnection(config('libretranslate.connection'));
        $this->onQueue('translations');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        return [(new WithoutOverlapping('journal-translation:'.$this->journalId))->releaseAfter(180)->expireAfter(180)];
    }

    public function handle(LibreTranslateService $service): void
    {
        $service->translateJournal($this->journalId);
    }
}