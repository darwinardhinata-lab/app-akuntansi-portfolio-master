<?php

namespace App\Console\Commands;

use App\Jobs\TranslateJournalNotes;
use App\Models\JournalHeader;
use App\Models\JournalNoteTranslation;
use App\Services\LibreTranslateService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class TranslateJournalNote extends Command
{
    protected $signature = 'translation:journal 
        {journal_id? : Specific journal ID to translate}
        {--check : Check local health and language routes without sending notes}
        {--missing : Select journals that have notes but no translation for current targets}
        {--account= : Filter journals belonging to a specific account code}
        {--start-date= : Filter journals from start date (YYYY-MM-DD)}
        {--end-date= : Filter journals to end date (YYYY-MM-DD)}
        {--limit= : Limit maximum journals to process}
        {--now : Execute translation synchronously now instead of queuing}
        {--status : Show translation statistics for journal notes}';

    protected $description = 'Check local LibreTranslate, queue or process journal notes for local translation.';

    public function handle(LibreTranslateService $service): int
    {
        if ($this->option('check')) {
            return $this->handleCheck($service);
        }

        if ($this->option('status')) {
            return $this->handleStatus($service);
        }

        if (!config('libretranslate.enabled')) {
            $this->error('Enable the pilot first (LIBRETRANSLATE_ENABLED=true).');
            return self::FAILURE;
        }

        $syncNow = (bool) $this->option('now');

        if (!$syncNow && config('libretranslate.connection') !== 'translations') {
            $this->error('Enable the pilot and use the translations queue connection first, or use --now to translate immediately.');
            return self::FAILURE;
        }

        $journalId = $this->argument('journal_id');

        // Single specific journal ID without bulk flags
        if ($journalId && !$this->option('missing') && !$this->option('account')) {
            $journal = JournalHeader::find($journalId);
            if (!$journal || trim((string) $journal->notes) === '' || mb_strlen((string) $journal->notes) > config('libretranslate.max_characters')) {
                $this->error('Provide an existing journal with a non-empty note of at most 5000 characters.');
                return self::FAILURE;
            }

            if ($syncNow) {
                try {
                    $service->translateJournal((string) $journal->getKey());
                    $this->info("Successfully translated journal '{$journalId}' immediately.");
                    return self::SUCCESS;
                } catch (\Throwable $e) {
                    $this->error("Translation failed: " . $e->getMessage());
                    return self::FAILURE;
                }
            }

            TranslateJournalNotes::dispatch((string) $journal->getKey());
            $this->info('Queued one journal note for local translation.');
            return self::SUCCESS;
        }

        // Multiple / filtered / missing journals
        $query = JournalHeader::query()
            ->whereNotNull('notes')
            ->where('notes', '!=', '')
            ->whereRaw('LENGTH(notes) <= ?', [config('libretranslate.max_characters', 5000)]);

        if ($journalId) {
            $query->where('journal_id', $journalId);
        }

        if ($account = $this->option('account')) {
            $query->whereHas('details', fn ($q) => $q->where('account_code', $account));
        }

        if ($startDate = $this->option('start-date')) {
            $query->where('transaction_date', '>=', $startDate);
        }

        if ($endDate = $this->option('end-date')) {
            $query->where('transaction_date', '<=', $endDate);
        }

        $journals = $query->orderBy('transaction_date', 'asc')->get();

        if ($this->option('missing')) {
            $targets = array_keys(config('libretranslate.targets', []));
            $journals = $journals->filter(function (JournalHeader $j) use ($targets) {
                $hash = LibreTranslateService::fingerprint((string) $j->notes);
                $existingCount = JournalNoteTranslation::where('journal_id', $j->journal_id)
                    ->whereIn('locale', $targets)
                    ->where('source_hash', $hash)
                    ->count();
                return $existingCount < count($targets);
            });
        }

        if ($limit = $this->option('limit')) {
            $journals = $journals->take((int) $limit);
        }

        $total = $journals->count();
        if ($total === 0) {
            $this->info('No matching journals found to translate.');
            return self::SUCCESS;
        }

        $this->info("Found {$total} journal(s) to process.");

        if ($syncNow) {
            $this->output->progressStart($total);
            $success = 0;
            $failed = 0;
            foreach ($journals as $j) {
                try {
                    $service->translateJournal((string) $j->getKey());
                    $success++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->newLine();
                    $this->warn("Failed translating {$j->journal_id}: " . $e->getMessage());
                }
                $this->output->progressAdvance();
            }
            $this->output->progressFinish();
            $this->info("Translation complete. Success: {$success}, Failed: {$failed}.");
            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        }

        foreach ($journals as $j) {
            TranslateJournalNotes::dispatch((string) $j->getKey());
        }
        $this->info("Queued {$total} journal(s) for local translation on 'translations' queue.");
        $this->comment("Run 'php artisan queue:work translations --queue=translations' to process the queue.");
        return self::SUCCESS;
    }

    private function handleCheck(LibreTranslateService $service): int
    {
        try {
            $url = $service->endpoint();
            $client = Http::connectTimeout(3)->timeout(15)->withoutRedirecting();
            if (!$client->get($url.'/health')->successful()) {
                throw new \RuntimeException('Local service is not healthy.');
            }
            $response = $client->get($url.'/languages');
            $languages = $response->json();
            if (!$response->successful() || !is_array($languages)) {
                throw new \RuntimeException('Cannot read local language routes.');
            }
            $source = collect($languages)->firstWhere('code', config('libretranslate.source'));
            foreach (config('libretranslate.targets') as $target) {
                if (!in_array($target, $source['targets'] ?? [], true)) {
                    throw new \RuntimeException('Required language route is not loaded: '.$target);
                }
            }
            $this->info('Local service is healthy; required translation routes are available.');
            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $this->error('Local service check failed. Check container health, URL and loaded languages.');
            return self::FAILURE;
        }
    }

    private function handleStatus(LibreTranslateService $service): int
    {
        $totalWithNotes = JournalHeader::whereNotNull('notes')->where('notes', '!=', '')->count();
        $targets = array_keys(config('libretranslate.targets', []));
        $translatedCount = JournalNoteTranslation::distinct('journal_id')->count('journal_id');
        $pendingJobs = DB::table('jobs')->where('queue', 'translations')->count();

        $this->table(
            ['Metric', 'Value'],
            [
                ['LibreTranslate Enabled', config('libretranslate.enabled') ? 'Yes' : 'No'],
                ['Endpoint', config('libretranslate.url')],
                ['Source Language', config('libretranslate.source')],
                ['Target Languages', implode(', ', $targets)],
                ['Total Journals with Notes', $totalWithNotes],
                ['Journals with Translations', $translatedCount],
                ['Pending Translation Queue Jobs', $pendingJobs],
            ]
        );

        return self::SUCCESS;
    }
}