<?php

namespace App\Modules\Manufacturing\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Modules\Manufacturing\Models\WorkOrder;
use App\Modules\Manufacturing\Models\CuttingCheck;
use App\Modules\Manufacturing\Exports\ManufacturingHppExport;
use Maatwebsite\Excel\Facades\Excel;

/**
 * STAGE 5 — Laporan HPP Manufaktur: rekap SEMUA SPK berstatus COMPLETED
 * dalam rentang tanggal, breakdown biaya bahan vs proses vs wastage, serta
 * HPP per pcs. Filter tanggal memakai tanggal jurnal penyelesaian SPK
 * (Jurnal #6, via relasi `journal`), BUKAN tanggal `order_date` SPK dibuat —
 * supaya laporan mencerminkan kapan barang benar-benar selesai & masuk stok.
 */
class ManufacturingReportController extends Controller
{
    public function hpp(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->format('Y-m-d'));
        $endDate = $request->get('end_date', now()->format('Y-m-d'));

        $workOrders = WorkOrder::with(['product', 'journal', 'cuttingOrders.checks'])
            ->where('status', 'COMPLETED')
            ->whereHas('journal', function ($q) use ($startDate, $endDate) {
                $q->whereBetween('transaction_date', [$startDate, $endDate]);
            })
            ->orderBy('order_date')
            ->get()
            ->map(function ($wo) {
                $wastageCost = $wo->cuttingOrders->flatMap->checks->sum('wastage_cost_amount');
                $qtyFinished = (float) \App\Models\InventoryLedger::where('evidence_number', $wo->spk_number)->sum('qty');
                $unitCost = $qtyFinished > 0 ? $wo->total_wip_cost / $qtyFinished : 0;

                return (object) [
                    'work_order'    => $wo,
                    'material_cost' => $wo->total_material_cost,
                    'process_cost'  => $wo->total_process_cost,
                    'wastage_cost'  => $wastageCost,
                    'total_wip'     => $wo->total_wip_cost,
                    'qty_finished'  => $qtyFinished,
                    'unit_cost'     => $unitCost,
                    'completed_at'  => $wo->journal->transaction_date ?? $wo->order_date,
                ];
            });

        $summary = (object) [
            'total_material' => $workOrders->sum('material_cost'),
            'total_process'  => $workOrders->sum('process_cost'),
            'total_wastage'  => $workOrders->sum('wastage_cost'),
            'total_wip'      => $workOrders->sum('total_wip'),
            'total_qty'      => $workOrders->sum('qty_finished'),
            'total_spk'      => $workOrders->count(),
        ];

        if ($request->get('export') === 'excel') {
            return Excel::download(
                new ManufacturingHppExport($workOrders),
                'Laporan_HPP_Manufaktur_' . $startDate . '_sd_' . $endDate . '.xlsx'
            );
        }

        return view('manufacturing.report.hpp', compact('workOrders', 'summary', 'startDate', 'endDate'));
    }
}
