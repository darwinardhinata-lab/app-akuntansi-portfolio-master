<?php

namespace App\Filament\Pages;

use App\Support\GeneralLedgerRead;
use Livewire\Attributes\Url;

final class GeneralLedger extends \Filament\Pages\Page
{
    protected string $view = 'filament.pages.general-ledger';
    protected static ?string $slug = 'general-ledger';
    #[Url] public string $start = '';
    #[Url] public string $end = '';
    #[Url] public string $account = '';
    #[Url] public string $journal = '';
    #[Url] public int $page = 1;

    public static function canAccess(): bool { return GeneralLedgerRead::allowed(); }
    public static function getNavigationLabel(): string { return __('gl.title'); }
    public function getTitle(): string { return __('gl.title'); }

    public function mount(): void
    {
        abort_unless(self::canAccess(), 403);
        $this->start = $this->start ?: now()->startOfMonth()->toDateString();
        $this->end = $this->end ?: now()->endOfMonth()->toDateString();
    }

    public function hydrate(): void { abort_unless(self::canAccess(), 403); }

    public function apply(): void
    {
        $this->validate($this->rules());
        $this->journal = '';
        $this->page = 1;
    }

    public function link(array $changes): string
    {
        return static::getUrl(array_merge(['start' => $this->start, 'end' => $this->end,
            'account' => $this->account, 'journal' => $this->journal, 'page' => $this->page], $changes));
    }

    protected function getViewData(): array
    {
        abort_unless(self::canAccess(), 403);
        if (validator($this->all(), $this->rules())->fails()) {
            return ['summary' => [], 'transactions' => null, 'detail' => null, 'source' => null, 'pageOpening' => '0.00',
                'company' => app(\App\Modules\Platform\Support\CompanyContext::class)->requireCompany(request())];
        }
        return app(GeneralLedgerRead::class)->read($this->start, $this->end, $this->account, $this->journal, $this->page);
    }

    private function rules(): array
    {
        return ['start' => 'required|date_format:Y-m-d', 'end' => 'required|date_format:Y-m-d|after_or_equal:start',
            'account' => 'string|max:50', 'journal' => 'string|max:50', 'page' => 'integer|min:1|max:100000'];
    }
}