<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\Account;
use App\Models\JournalHeader;
use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\FabricReceipt;
use App\Modules\Manufacturing\Models\GreyFabricReceipt;
use App\Modules\Manufacturing\Models\KnitOrder;
use App\Modules\Manufacturing\Models\MaterialLedger;
use App\Modules\Manufacturing\Models\ProcessingOrder;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use App\Support\DocumentSequence;
use App\Support\FabricInventoryAccount;
use App\Support\MaklunJournalProtection;
use App\Support\MaklunReversalAuthorization;
use App\Support\PostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

class MaklunReceiptService
{
    public function receive(bool $knitting, int $orderId, array $data)
    {
        if (! config('platform.maklun_receipt_enabled')) {
            throw new RuntimeException('Receipt maklun nonaktif; snapshot COA dan readiness harus diverifikasi.');
        }
        Validator::make($data, [
            'receipt_date' => 'required|date_format:Y-m-d', 'qty_received' => 'required|numeric|min:0.01',
            'qty_rejected' => 'nullable|numeric|max:0|min:0', 'shrinkage_percent' => 'nullable|numeric|max:0|min:0',
            'full_completion' => 'required|accepted', 'liability_account_code' => 'required|in:212001',
            'knitting_cost_amount' => 'nullable|decimal:0,2|min:0', 'process_cost_amount' => 'nullable|decimal:0,2|min:0',
        ])->validate();

        return DB::transaction(function () use ($knitting, $orderId, $data) {
            $order = ($knitting ? KnitOrder::query() : ProcessingOrder::query())->lockForUpdate()->findOrFail($orderId);
            $receipts = $knitting ? $order->greyFabricReceipts() : $order->fabricReceipts();
            if ($order->status !== 'ISSUED' || $receipts->exists()) {
                throw new RuntimeException('Order bukan ISSUED atau sudah mempunyai receipt; partial/re-receipt belum didukung.');
            }
            $issues = ($knitting ? $order->yarnIssues() : $order->fabricIssues())->orderBy('id')->lockForUpdate()->get();
            if ($issues->isEmpty()) {
                throw new RuntimeException('Issue belum tersedia.');
            }
            $credits = [];
            $material = '0.00';
            $issueSnapshot = [];
            foreach ($issues as $issue) {
                if (! $issue->source_account_code || $issue->reversed_at || (float) ($issue->returned_qty ?? 0) != 0
                    || $issue->issue_date->toDateString() > $data['receipt_date']) {
                    throw new RuntimeException('Issue legacy/retur/tanggal tidak valid; jangan backfill dari master terbaru.');
                }
                $code = FabricInventoryAccount::resolve($issue->source_account_code);
                $amount = (string) $issue->total_cost;
                if (bccomp($amount, '0', 2) <= 0) {
                    throw new RuntimeException('Nilai issue harus positif.');
                }
                $credits[$code] = bcadd($credits[$code] ?? '0', $amount, 2);
                $material = bcadd($material, $amount, 2);
                $issueSnapshot[] = ['id' => $issue->id, 'account' => $code, 'amount' => $amount];
            }
            $fabric = Fabric::lockForUpdate()->findOrFail($knitting ? $order->fabric_id : ($data['finished_fabric_id'] ?? 0));
            if ($fabric->state !== ($knitting ? 'GREY' : 'FINISHED')
                || (! $knitting && $issues->contains('fabric_id', $fabric->id))) {
                throw new RuntimeException('State/SKU hasil tidak sesuai tahap maklun.');
            }
            $destination = FabricInventoryAccount::resolve($fabric->inventory_account_code);
            $liability = Account::find($data['liability_account_code']);
            if (! $liability || $liability->normal_balance !== 'KREDIT' || $liability->report_pos !== 'NERACA') {
                throw new RuntimeException('Akun kewajiban jasa tidak valid.');
            }
            $costKey = $knitting ? 'knitting_cost_amount' : 'process_cost_amount';
            $cost = bcadd((string) ($data[$costKey] ?? '0'), '0', 2);
            $total = bcadd($material, $cost, 2);
            $model = $knitting ? new GreyFabricReceipt : new FabricReceipt;
            $number = DocumentSequence::generateSecure($model->getTable(), 'receipt_number', ($knitting ? 'GFR-' : 'FR-').now()->format('Ymd').'-');
            $before = ['qty' => (string) $fabric->stock_quantity, 'mac' => (string) $fabric->average_cost];
            $lines = [['account_code' => $destination, 'position' => 'DEBET', 'amount' => $total]];
            foreach ($credits as $code => $amount) {
                $lines[] = ['account_code' => (string) $code, 'position' => 'KREDIT', 'amount' => $amount];
            }
            if (bccomp($cost, '0', 2) > 0) {
                $lines[] = ['account_code' => $liability->account_code, 'position' => 'KREDIT', 'amount' => $cost];
            }
            $journal = PostingService::post(['source_doc_no' => $number, 'transaction_date' => $data['receipt_date'],
                'transaction_type' => 'Maklun Receipt', 'description' => 'Penyelesaian penuh maklun '.$number], $lines);
            MaterialCostHelper::receiveStock($fabric, 'FABRIC', (float) $data['qty_received'], (float) $total / (float) $data['qty_received'], $data['receipt_date'], $number, 'Maklun completion');
            $ledger = MaterialLedger::where('evidence_number', $number)->latest('id')->firstOrFail();
            $model->fill($data);
            $model->forceFill(['receipt_number' => $number, $knitting ? 'knit_order_id' : 'processing_order_id' => $orderId,
                'fabric_id' => $fabric->id, $costKey => $cost, 'posting_status' => 'POSTED', 'journal_id' => $journal->getKey(),
                'coa_snapshot' => json_encode(['issues' => $issueSnapshot, 'destination' => $destination,
                    'liability' => $liability->account_code, 'total' => $total, 'before' => $before, 'ledger_id' => $ledger->id], JSON_THROW_ON_ERROR)])->save();
            $order->update(['status' => 'COMPLETED']);
            MaklunJournalProtection::seal($journal->getKey());
            SystemLog::record('POST', 'Maklun', 'Receipt penuh '.$number.'; jurnal '.$journal->getKey());

            return $model;
        });
    }

    public function reverse(bool $knitting, int $receiptId, string $reason = ''): bool
    {
        $reason = MaklunReversalAuthorization::validate($reason);

        return DB::transaction(function () use ($knitting, $receiptId, $reason) {
            $receipt = ($knitting ? GreyFabricReceipt::query() : FabricReceipt::query())->lockForUpdate()->findOrFail($receiptId);
            if ($receipt->posting_status !== 'POSTED' || ! $receipt->coa_snapshot || ! $receipt->journal_id) {
                throw new RuntimeException('Receipt legacy/void tanpa snapshot tidak dapat dibalik otomatis.');
            }
            $snapshot = json_decode($receipt->coa_snapshot, true, 512, JSON_THROW_ON_ERROR);
            $order = ($knitting ? KnitOrder::query() : ProcessingOrder::query())->lockForUpdate()->findOrFail($knitting ? $receipt->knit_order_id : $receipt->processing_order_id);
            $fabric = Fabric::lockForUpdate()->findOrFail($receipt->fabric_id);
            $last = MaterialLedger::where('item_type', 'FABRIC')->where('item_id', $fabric->id)->latest('id')->first();
            if (! $last || $last->id != $snapshot['ledger_id'] || $fabric->inventory_account_code !== $snapshot['destination']
                || bccomp((string) $fabric->stock_quantity, (string) $last->running_qty, 2) !== 0
                || bccomp((string) $fabric->average_cost, (string) $last->moving_average_cost, 2) !== 0) {
                throw new RuntimeException('Ada transaksi/perubahan stok setelah receipt; reversal otomatis ditolak.');
            }
            $original = JournalHeader::with('details')->findOrFail($receipt->journal_id);
            $lines = $original->details->map(fn ($line) => ['account_code' => $line->account_code,
                'position' => $line->position === 'DEBET' ? 'KREDIT' : 'DEBET', 'amount' => $line->amount])->all();
            if (! $lines) {
                throw new RuntimeException('Jurnal asal kosong.');
            }
            $number = 'REV-'.$receipt->receipt_number;
            $reversal = PostingService::post(['journal_id' => 'REV-'.$receipt->journal_id, 'source_doc_no' => $number,
                'transaction_date' => now()->toDateString(), 'transaction_type' => 'Maklun Reversal', 'description' => 'Reversal '.$receipt->journal_id], $lines);
            $fabric->update(['stock_quantity' => $snapshot['before']['qty'], 'average_cost' => $snapshot['before']['mac']]);
            MaterialLedger::create(['transaction_date' => now()->toDateString(), 'evidence_number' => $number,
                'item_type' => 'FABRIC', 'item_id' => $fabric->id, 'type' => 'OUT', 'qty' => $receipt->qty_received,
                'unit_cost' => (float) $snapshot['total'] / (float) $receipt->qty_received, 'total_cost' => $snapshot['total'],
                'running_qty' => $fabric->stock_quantity, 'running_value' => (float) $fabric->stock_quantity * (float) $fabric->average_cost,
                'moving_average_cost' => $fabric->average_cost, 'description' => 'Reversal '.$receipt->receipt_number]);
            $receipt->forceFill(['posting_status' => 'REVERSED', 'reversal_journal_id' => $reversal->getKey(),
                'reversal_reason' => $reason, 'reversed_by' => auth()->id()])->save();
            MaklunJournalProtection::seal($reversal->getKey());
            $order->update(['status' => 'CANCELED']);
            SystemLog::record('VOID', 'Maklun', 'Reversal '.$receipt->receipt_number.'; jurnal '.$reversal->getKey().'; alasan: '.$reason);

            return true;
        });
    }
}
