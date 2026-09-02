<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Asset;

class CleanupOrphanAssets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'assets:cleanup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up orphan assets that do not have any associated journal detail records';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting clean up of orphan assets...');

        // FIX: Pindahkan operasi delete dari GET request ke Command/Cron Job
        $deletedCount = Asset::doesntHave('journalDetail')->delete();

        $this->info("Successfully deleted {$deletedCount} orphan asset(s).");
        
        return Command::SUCCESS;
    }
}
