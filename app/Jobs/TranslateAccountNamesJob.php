<?php

namespace App\Jobs;

use App\Services\AccountTranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TranslateAccountNamesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 30;

    /**
     * @param  string[]  $accountCodes  akun yang baru diimport/dibuat, untuk translate account_name
     * @param  string[]  $coaTypes      nilai coa_type (locale id) yang perlu dicek kamusnya
     */
    public function __construct(
        protected array $accountCodes,
        protected array $coaTypes = [],
    ) {}

    public function handle(AccountTranslationService $service): void
    {
        if (!empty($this->accountCodes)) {
            $service->translateAccountNames($this->accountCodes);
        }

        if (!empty($this->coaTypes)) {
            $service->translateCoaTypes($this->coaTypes);
        }
    }
}
