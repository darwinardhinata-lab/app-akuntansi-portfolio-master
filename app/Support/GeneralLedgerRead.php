<?php

namespace App\Support;

use App\Models\Account;
use App\Models\JournalDetail;
use App\Models\JournalHeader;
use App\Modules\Currency\Support\Money;
use App\Modules\PaymentPlan\Domain\V33ApprovalDomain;
use App\Modules\PaymentPlan\Support\ErpActor;
use App\Modules\Platform\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Shared legacy GL is readable only in the existing single-MGI panel boundary. */
final class GeneralLedgerRead
{
    public static function allowed(): bool
    {
        $access = app(ErpPanelAccess::class);
        return $access->allows(request(), auth()->user())
            && $access->allows(request(), auth()->user(), 'gl.view');
    }

    public function read(string $start, string $end, string $account, string $journal, int $page): array
    {
        abort_unless(self::allowed(), 403);
        $company = app(CompanyContext::class)->requireCompany(request());
        // One consistent read transaction; no locks, seeding or balance projection writes.
        return DB::transaction(function () use ($start, $end, $account, $journal, $page, $company) {
            $summary = [];
            $query = JournalDetail::join('journal_headers as h', 'journal_details.journal_id', '=', 'h.journal_id')
                ->where('h.transaction_date', '<', date('Y-m-d', strtotime($end.' +1 day')))
                ->select('journal_details.account_code', 'journal_details.position', 'journal_details.amount', 'h.transaction_date');
            foreach ($query->cursor() as $line) {
                $code = (string) $line->account_code;
                $summary[$code] ??= ['opening_debit' => '0.00', 'opening_credit' => '0.00', 'debit' => '0.00', 'credit' => '0.00'];
                $field = ($line->transaction_date < $start ? 'opening_' : '').($line->position === 'DEBET' ? 'debit' : 'credit');
                $summary[$code][$field] = Money::add($summary[$code][$field], (string) $line->amount);
            }
            $accounts = Account::with('translations')->orderBy('account_code')->get()->keyBy('account_code');
            // Include orphaned account references explicitly rather than silently dropping ledger money.
            foreach (array_unique(array_merge($accounts->keys()->map(fn ($k) => (string) $k)->all(), array_keys($summary))) as $code) {
                $normal = $accounts->get($code)?->normal_balance;
                $debitNormal = $normal ? strtoupper(trim($normal)) === 'DEBET' : in_array(substr($code, 0, 1), ['1', '5', '6', '9']);
                $row = $summary[$code] ?? ['opening_debit' => '0.00', 'opening_credit' => '0.00', 'debit' => '0.00', 'credit' => '0.00'];
                $row['name'] = $accounts->get($code)?->translatedName() ?? __('gl.missing_account');
                $row['debit_normal'] = $debitNormal;
                $row['opening'] = $debitNormal ? Money::sub($row['opening_debit'], $row['opening_credit']) : Money::sub($row['opening_credit'], $row['opening_debit']);
                $row['closing'] = Money::add($row['opening'], $debitNormal ? Money::sub($row['debit'], $row['credit']) : Money::sub($row['credit'], $row['debit']));
                $summary[$code] = $row;
            }
            ksort($summary);
            $transactions = null;
            $detail = null;
            $source = null;
            $pageOpening = '0.00';
            if ($account !== '') {
                abort_unless(isset($summary[$account]), 404);
                $rows = JournalDetail::join('journal_headers as h', 'journal_details.journal_id', '=', 'h.journal_id')
                    ->where('journal_details.account_code', $account)->where('h.transaction_date', '>=', $start)
                    ->where('h.transaction_date', '<', date('Y-m-d', strtotime($end.' +1 day')))
                    ->select('journal_details.*', 'h.transaction_date', 'h.evidence_number', 'h.source_doc_no')
                    ->orderBy('h.transaction_date')->orderBy('journal_details.id');
                $pageOpening = $summary[$account]['opening'];
                foreach ((clone $rows)->limit(($page - 1) * 25)->get() as $line) {
                    $pageOpening = $this->move($pageOpening, $line, $summary[$account]['debit_normal']);
                }
                $transactions = $rows->paginate(25, ['*'], 'page', $page);
                $running = $pageOpening;
                foreach ($transactions as $line) {
                    $running = $this->move($running, $line, $summary[$account]['debit_normal']);
                    $line->setAttribute('running_balance', $running);
                }
                if ($journal !== '') {
                    // The journal must actually contribute to this account and selected period.
                    abort_unless((clone $rows)->where('journal_details.journal_id', $journal)->exists(), 404);
                    $detail = JournalHeader::with('details.account.translations')->findOrFail($journal);
                    $source = $this->source($journal, (int) $company->id);
                }
            } else {
                abort_if($journal !== '', 404);
            }
            return compact('summary', 'transactions', 'detail', 'source', 'pageOpening', 'company');
        });
    }

    private function move(string $balance, object $line, bool $debitNormal): string
    {
        return ($line->position === 'DEBET') === $debitNormal
            ? Money::add($balance, (string) $line->amount) : Money::sub($balance, (string) $line->amount);
    }

    private function source(string $journal, int $company): ?array
    {
        // Purchasing refs must be resolved as a whole, never fall through an ambiguous domain.
        if (DB::table('purchase_receipts')->where('journal_id', $journal)->exists()
            || DB::table('purchase_bills')->where('journal_id', $journal)->exists()) {
            return $this->purchaseBillSource($journal, $company);
        }
        // Only a real FK-backed payment event qualifies. No prefix/number inference.
        $event = DB::table('ppl_payment_events')->where('journal_id', $journal)->where('company_id', $company)->first();
        if (!$event || !config('payment_plan_v33.bank_workflow')) return null;
        $request = DB::table('ppl_requests')->where('id', $event->request_id)->where('company_id', $company)->first();
        if (!$request) return null;
        try {
            $actor = app(ErpActor::class)->resolve(request());
        } catch (HttpException $exception) {
            return null;
        }
        $payload = json_decode($request->payload, true, 512, JSON_THROW_ON_ERROR);
        if (!(new V33ApprovalDomain(fn () => new \DateTimeImmutable()))->canSee($actor, $payload)) return null;
        return ['id' => $request->id, 'label' => $request->doc, 'url' => route('payment-plan-v33.print', $request->id)];
    }

    private function purchaseBillSource(string $journal, int $company): ?array
    {
        // Count all candidates before applying actor/company filters: no first-record winner.
        $receipts = DB::table('purchase_receipts')->where('journal_id', $journal)->limit(2)->get();
        $bills = \App\Models\PurchaseBill::where('journal_id', $journal)->limit(2)->get();
        if ($receipts->count() !== 1 || $bills->count() !== 1
            || DB::table('ppl_payment_events')->where('journal_id', $journal)->exists()) return null;
        $receipt = $receipts->sole();
        $bill = $bills->sole();
        $po = DB::table('purchase_orders')->where('id', $bill->purchase_order_id)->first();
        $header = JournalHeader::find($journal);
        if (! $po || ! $header || !config('platform.order_company_scope_enabled')
            || $receipt->status !== 'POSTED' || $po->receipt_mode !== 'GRN_V1'
            || (int) $receipt->company_id !== $company || (int) $po->company_id !== $company
            || (int) $receipt->purchase_bill_id !== (int) $bill->id
            || (int) $receipt->purchase_order_id !== (int) $po->id
            || ! $receipt->idempotency_key || ! $receipt->payload_hash
            || $header->source_doc_no !== $bill->bill_number
            || substr((string) $header->transaction_date, 0, 10) !== $bill->getRawOriginal('bill_date')
            || (string) $receipt->receipt_date !== $bill->getRawOriginal('bill_date')) return null;
        try {
            app(\App\Modules\Platform\Support\PurchaseBillReadAccess::class)->authorize(request(), $bill);
        } catch (HttpException $exception) {
            return null;
        }
        return ['id' => $bill->id, 'label' => $bill->bill_number, 'url' => route('purchase-bills.show', $bill->id)];
    }
}