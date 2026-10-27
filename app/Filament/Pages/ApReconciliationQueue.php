<?php

namespace App\Filament\Pages;

use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\ErpPanelAccess;
use Illuminate\Support\Facades\DB;

final class ApReconciliationQueue extends \Filament\Pages\Page
{
    protected string $view = 'filament.pages.ap-reconciliation-queue';
    protected static ?string $slug = 'ap-reconciliation';
    public array $proposal = ['purchase_bill_id' => '', 'journal_id' => '', 'amount' => '', 'reason' => '', 'evidence' => ''];
    public string $decisionReason = '';

    public static function canAccess(): bool
    {
        return config('payment_plan_v33.bank_workflow') && app(ErpPanelAccess::class)->allows(request(), auth()->user(), 'ap.reconcile.view');
    }

    public function propose(): void
    {
        abort_unless(static::canAccess(), 403);
        app(ApReconciliation::class)->propose($this->proposal);
        $this->reset('proposal');
    }

    public function decide(int $id, int $version, string $action): void
    {
        abort_unless(static::canAccess(), 403);
        app(ApReconciliation::class)->decide($id, $version, $action, $this->decisionReason);
    }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        $company = (int) app(CompanyContext::class)->requireCompany(request())->id;
        $queue = DB::transaction(function () use ($company) {
            \App\Modules\PaymentPlan\Services\LegacyFinancialFence::lock();
            $rows = [];
            foreach (\App\Models\PurchaseBill::whereIn('payment_status', ['UNPAID', 'PARTIAL'])->orderBy('id')->limit(200)->get() as $bill) {
                try { $rows[] = (new PurchaseBillAllocation())->source($bill->id, $company); }
                catch (\App\Modules\PaymentPlan\Domain\RuleViolation $e) { $rows[] = ['id' => $bill->id, 'number' => $bill->bill_number, 'blocked' => $e->rule]; }
            }
            return $rows;
        });
        return ['queue' => $queue, 'resolutions' => DB::table('ppl_ap_reconciliations')->where('company_id', $company)->orderByDesc('id')->limit(200)->get()];
    }
}