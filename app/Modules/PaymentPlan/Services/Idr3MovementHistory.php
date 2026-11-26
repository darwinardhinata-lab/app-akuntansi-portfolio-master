<?php

namespace App\Modules\PaymentPlan\Services;

use App\Filament\Pages\GeneralLedger;
use App\Modules\PaymentPlan\Domain\V33ApprovalDomain;
use App\Support\GeneralLedgerRead;
use Illuminate\Support\Facades\DB;

final class Idr3MovementHistory
{
    public const ROLES = ['finance_staff', 'finance_manager', 'accountant', 'director', 'maker', 'general_manager'];
    private const PER_PAGE = 25;

    public function read(array $actor, array $input): array
    {
        return DB::transaction(function () use ($actor, $input) {
            $company = $actor['company_id'];
            $raw = DB::table('ppl_financial_contexts')->where('company_id', $company)->value('settings');
            abort_unless($raw, 404);
            $settings = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            $accounts = array_values(array_filter($settings['accounts'], fn ($account) => $account['id'] === 'idr3'));
            abort_unless(count($accounts) === 1, 404);
            $erp = new ErpBankBalance;
            $projection = $erp->project($company, $accounts[0], $settings);
            $today = now('Asia/Jakarta')->toDateString();
            $start = $input['start'] ?? $projection['cutoff'];
            $end = $input['end'] ?? $today;
            $page = (int) ($input['page'] ?? 1);
            abort_if($start < $projection['cutoff'] || $end < $start || $end > $today, 422, __('idr3.period_error'));

            $opening = $running = $pageOpening = $pageClosing = '0.00';
            $rows = [];
            $total = 0;
            $offset = ($page - 1) * self::PER_PAGE;
            $periodRow = null;
            $flush = function () use (&$periodRow, &$total, &$rows, &$running, &$pageOpening, &$pageClosing, $offset): void {
                if (!$periodRow) return;
                if ($total === $offset) $pageOpening = $periodRow['before'];
                if ($total >= $offset && $total < $offset + self::PER_PAGE) {
                    unset($periodRow['before']);
                    $rows[] = $periodRow + ['balance' => $running];
                    $pageClosing = $running;
                }
                $total++;
            };
            $allowJournal = GeneralLedgerRead::allowed();
            $lines = $erp->ledgerLines($projection['account_code'], $end)
                ->orderBy('h.transaction_date')->orderBy('h.journal_id')->orderBy('journal_details.id')
                ->select('h.transaction_date', 'h.journal_id', 'h.notes', 'journal_details.position', 'journal_details.amount');
            foreach ($lines->cursor() as $line) {
                $date = substr($line->transaction_date, 0, 10);
                if ($date < $start) {
                    $opening = $running = bcadd($running, $erp->signedAmount($line), 2);
                    continue;
                }
                if (($periodRow['journal'] ?? null) !== $line->journal_id) {
                    $flush();
                    $periodRow = ['date' => $date, 'journal' => $line->journal_id, 'description' => $allowJournal ? $line->notes : null,
                        'before' => $running, 'debit' => '0.00', 'credit' => '0.00'];
                }
                $running = bcadd($running, $erp->signedAmount($line), 2);
                $side = $line->position === 'DEBET' ? 'debit' : 'credit';
                $periodRow[$side] = bcadd($periodRow[$side], (string) $line->amount, 2);
            }
            $flush();
            $last = max(1, (int) ceil($total / self::PER_PAGE));
            abort_if($page > $last, 404);
            if (!$total) $pageOpening = $pageClosing = $opening;
            $this->links($rows, $actor, $projection['account_code'], $allowJournal);
            return compact('projection', 'start', 'end', 'today', 'page', 'last', 'opening', 'running', 'pageOpening', 'pageClosing', 'rows');
        });
    }

    private function links(array &$rows, array $actor, string $code, bool $allowJournal): void
    {
        $events = DB::table('ppl_payment_events as e')->join('ppl_requests as r', function ($join) {
            $join->on('e.request_id', '=', 'r.id')->on('e.company_id', '=', 'r.company_id');
        })->where('e.company_id', $actor['company_id'])->whereIn('e.journal_id', array_column($rows, 'journal'))
            ->get(['e.journal_id', 'e.snapshot', 'r.payload', 'r.id'])->groupBy('journal_id');
        $domain = new V33ApprovalDomain(fn () => new \DateTimeImmutable());
        foreach ($rows as &$row) {
            $row['journal_url'] = $allowJournal ? GeneralLedger::getUrl(['start' => $row['date'], 'end' => $row['date'],
                'account' => $code, 'journal' => $row['journal']], panel: 'erp') : null;
            $row['request_url'] = null;
            $matches = $events->get($row['journal']);
            if ($matches?->count() !== 1) continue;
            $event = $matches->sole();
            $snapshot = json_decode($event->snapshot, true, 512, JSON_THROW_ON_ERROR);
            $payload = json_decode($event->payload, true, 512, JSON_THROW_ON_ERROR);
            if (($snapshot['settlement']['sourceId'] ?? null) === 'idr3' && $snapshot['paidDate'] === $row['date']
                && $domain->canSee($actor, $payload)) {
                $row['request_url'] = route('payment-plan-v33.print', $event->id);
            }
        }
    }
}