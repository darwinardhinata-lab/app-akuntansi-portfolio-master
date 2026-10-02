<?php

namespace App\Modules\Manufacturing\Services;

use App\Models\SystemLog;
use App\Modules\Manufacturing\Models\Fabric;
use App\Modules\Manufacturing\Models\FabricIssue;
use App\Modules\Manufacturing\Models\KnitOrder;
use App\Modules\Manufacturing\Models\ProcessingOrder;
use App\Modules\Manufacturing\Models\Yarn;
use App\Modules\Manufacturing\Models\YarnIssue;
use App\Modules\Manufacturing\Support\MaterialCostHelper;
use App\Support\MaklunReversalAuthorization;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class MaklunIssueReversalService
{
    public function reverse(bool $knitting, int $id, string $reason = ''): bool
    {
        $reason = MaklunReversalAuthorization::validate($reason);

        return DB::transaction(function () use ($knitting, $id, $reason) {
            $candidate = ($knitting ? YarnIssue::query() : FabricIssue::query())->findOrFail($id);
            $order = ($knitting ? KnitOrder::query() : ProcessingOrder::query())->lockForUpdate()->findOrFail($knitting ? $candidate->knit_order_id : $candidate->processing_order_id);
            $issue = ($knitting ? YarnIssue::query() : FabricIssue::query())->lockForUpdate()->findOrFail($id);
            $receipts = $knitting ? $order->greyFabricReceipts() : $order->fabricReceipts();
            if (! $issue->source_account_code || $issue->reversed_at || (float) ($issue->returned_qty ?? 0) != 0
                || ! in_array($order->status, ['ISSUED', 'CANCELED'], true)
                || $receipts->where(function ($q) {
                    $q->whereNull('posting_status')->orWhere('posting_status', '!=', 'REVERSED');
                })->exists()) {
                throw new RuntimeException('Issue legacy, sudah dibalik, partial-return atau sudah dikonsumsi tidak dapat dibalik.');
            }
            $item = ($knitting ? Yarn::query() : Fabric::query())->lockForUpdate()->findOrFail($knitting ? $issue->yarn_id : $issue->fabric_id);
            if ($item->inventory_account_code !== $issue->source_account_code || (float) $issue->qty_issued <= 0) {
                throw new RuntimeException('Akun asal berubah atau kuantitas issue invalid; reklasifikasi diperlukan.');
            }
            $number = 'REV-'.$issue->issue_number;
            MaterialCostHelper::receiveStock($item, $knitting ? 'YARN' : 'FABRIC', (float) $issue->qty_issued,
                (float) $issue->total_cost / (float) $issue->qty_issued, now()->toDateString(), $number, 'Reversal penuh '.$issue->issue_number);
            $issue->forceFill(['reversed_at' => now(), 'reversal_number' => $number,
                'reversal_reason' => $reason, 'reversed_by' => auth()->id()])->save();
            $active = ($knitting ? $order->yarnIssues() : $order->fabricIssues())->whereNull('reversed_at')->exists();
            if (! $active) {
                $order->update(['status' => 'CANCELED']);
            }
            SystemLog::record('VOID', 'Maklun', 'Reversal issue penuh '.$issue->issue_number.' -> '.$number.'; alasan: '.$reason);

            return true;
        });
    }
}
