<?php

namespace App\Filament\Pages;

use App\Modules\PaymentPlan\Services\ApReconciliation;
use App\Modules\PaymentPlan\Services\PurchaseBillAllocation;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\ErpPanelAccess;
use Illuminate\Support\Facades\DB;

final class ApReconciliationQueue extends \Filament\Pages\Page
{
    use \Livewire\WithPagination;

    protected string $view = 'filament.pages.ap-reconciliation-queue';
    protected static ?string $slug = 'ap-reconciliation';
    public array $proposal = ['purchase_bill_id' => '', 'journal_id' => '', 'amount' => '', 'reason' => '', 'evidence' => '', 'purchase_return_detail_id' => null, 'quantity' => null];
    public string $decisionReason = '';
    public string $invoiceSearch = '';
    public string $journalSearch = '';
    public string $returnSearch = '';

    public function updatedInvoiceSearch(): void { $this->resetPage('invoices'); }
    public function updatedJournalSearch(): void { $this->resetPage('journals'); }
    public function updatedReturnSearch(): void { $this->resetPage('returns'); }

    public function updatedProposalPurchaseBillId(): void
    {
        $this->proposal['journal_id'] = '';
        $this->proposal['purchase_return_detail_id'] = null;
        $this->proposal['quantity'] = null;
        $this->resetPage('journals');
        $this->resetPage('returns');
    }

    public function updatedProposalPurchaseReturnDetailId(): void
    {
        $this->proposal['journal_id'] = '';
        $this->resetPage('journals');
    }

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
        return DB::transaction(function () use ($company) {
            \App\Modules\PaymentPlan\Services\LegacyFinancialFence::lock();
            $invoices = \App\Models\PurchaseBill::whereIn('payment_status', ['UNPAID', 'PARTIAL'])
                ->where(fn ($q) => $q->where('bill_number', 'like', '%'.mb_substr($this->invoiceSearch, 0, 100).'%')
                    ->orWhere('vendor_name', 'like', '%'.mb_substr($this->invoiceSearch, 0, 100).'%'))
                ->orderBy('id')->paginate(20, ['*'], 'invoices');
            $queue = [];
            foreach ($invoices as $bill) {
                try { $source = (new PurchaseBillAllocation())->source($bill->id, $company); }
                catch (\App\Modules\PaymentPlan\Domain\RuleViolation $e) { $source = ['blocked' => $e->rule]; }
                $queue[] = $source + ['id' => $bill->id, 'number' => $bill->bill_number, 'payee' => $bill->vendor_name,
                    'date' => (string) $bill->bill_date, 'status' => $bill->payment_status];
            }
            $bill = \App\Models\PurchaseBill::find((int) ($this->proposal['purchase_bill_id'] ?? 0));
            $returns = DB::table('purchase_return_details as d')->join('purchase_returns as r', 'r.id', '=', 'd.purchase_return_id')
                ->where('r.purchase_order_id', $bill?->purchase_order_id ?: -1)
                ->where(fn ($q) => $q->where('r.return_number', 'like', '%'.mb_substr($this->returnSearch, 0, 100).'%')
                    ->orWhere('d.item_code', 'like', '%'.mb_substr($this->returnSearch, 0, 100).'%'))
                ->orderBy('d.id')->select('d.*', 'r.return_number', 'r.return_date', 'r.status')->paginate(20, ['*'], 'returns');
            foreach ($returns as $detail) {
                $used = '0.000000';
                foreach (DB::table('ppl_ap_reconciliations')->where('purchase_return_detail_id', $detail->id)->where('status', 'approved')->get() as $allocation) {
                    $used = bcadd($used, (string) $allocation->quantity, 6);
                }
                $detail->remaining = bcsub((string) $detail->qty_returned, $used, 6);
            }
            $journals = \App\Support\ProtectedJournalQuery::table('journal_headers')
                ->where(fn ($q) => $q->where('source_doc_no', 'like', '%'.mb_substr($this->journalSearch, 0, 100).'%')
                    ->orWhere('journal_id', 'like', '%'.mb_substr($this->journalSearch, 0, 100).'%'))
                ->when(!$bill, fn ($q) => $q->whereRaw('1 = 0'))
                ->orderBy('journal_id')->paginate(20, ['*'], 'journals');
            foreach ($journals as $journal) {
                try {
                    $journal->preview = app(ApReconciliation::class)->previewSource($journal->journal_id, $bill,
                        ($this->proposal['purchase_return_detail_id'] ?? null) ?: null);
                } catch (\Illuminate\Validation\ValidationException $e) {
                    $journal->preview = ['blocked' => $e->errors()['reconciliation'][0]];
                }
            }
            return compact('queue', 'invoices', 'returns', 'journals') + ['resolutions' => DB::table('ppl_ap_reconciliations')
                ->where('company_id', $company)->orderByDesc('id')->paginate(20, ['*'], 'resolutions')];
        });
    }
}