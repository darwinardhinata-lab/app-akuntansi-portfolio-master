<?php

namespace App\Modules\CustomsReports\Services;

use App\Modules\CustomsReports\Models\ReportPeriod;
use App\Modules\CustomsReports\Models\DokumenPabeanLine;
use App\Modules\CustomsReports\Models\MutasiLine;
use App\Modules\CustomsReports\Models\PosisiLine;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Read-only operational sources; only draft report snapshots are written. */
class InternalReportSyncService
{
    public function sync(ReportPeriod $period): void
    {
        DB::transaction(function () use ($period) {
            $period = ReportPeriod::lockForUpdate()->findOrFail($period->id);
            if (! $period->isDraft()) {
                return;
            }
            $start = Carbon::create($period->periode_tahun, $period->periode_bulan, 1)->startOfMonth()->toDateString();
            $end = Carbon::parse($start)->endOfMonth()->toDateString();
            // Rebuild the snapshot, including removals/voids in operational sources.
            $period->lines()->delete();
            match ($period->report_type) {
                ReportPeriod::TYPE_PEMASUKAN => $this->receipts($period, $start, $end),
                ReportPeriod::TYPE_PENGELUARAN => $this->shipments($period, $start, $end),
                ReportPeriod::TYPE_MUTASI_BAHAN_BAKU => app(ReportPeriodService::class)->populateMutasiBahanBaku($period),
                ReportPeriod::TYPE_MUTASI_BARANG_JADI => app(ReportPeriodService::class)->populateMutasiBarangJadi($period),
                ReportPeriod::TYPE_WIP => $this->wip($period, $end),
                ReportPeriod::TYPE_MUTASI_BARANG_MODAL => $this->assets($period, $start, $end),
                ReportPeriod::TYPE_MUTASI_REJECT => $this->rejects($period, $start, $end),
            };
        });
    }

    private function document(ReportPeriod $period, object $row): void
    {
        DokumenPabeanLine::create([
            'report_period_id' => $period->id,
            'jenis_dok_pabean' => '', 'no_pendaftaran_dok_pabean' => '', 'tgl_dok_pabean' => null,
            'no_bukti' => $row->number, 'tgl_bukti' => $row->date,
            'pihak_terkait' => $row->party ?? '', 'kode_barang' => $row->code ?? '',
            'nama_barang' => $row->name ?? $row->code ?? '', 'jumlah_barang' => $row->qty,
            'satuan_barang' => $row->unit ?? '', 'mata_uang' => 'IDR', 'nilai' => $row->amount,
            'harga_idr' => $row->amount,
        ]);
    }

    private function receipts(ReportPeriod $period, string $start, string $end): void
    {
        $rows = DB::table('purchase_receipt_details as d')
            ->join('purchase_receipts as h', 'h.id', '=', 'd.purchase_receipt_id')
            ->leftJoin('purchase_orders as o', 'o.id', '=', 'h.purchase_order_id')
            ->leftJoin('products as p', 'p.id', '=', 'd.product_id')
            ->where('h.status', 'POSTED')->whereNotNull('d.product_id')
            ->whereBetween('h.receipt_date', [$start, $end])
            ->selectRaw('h.receipt_number as number, h.receipt_date as date, o.contact_name as party, p.sku as code, d.description as name, d.qty_received as qty, p.unit, d.amount');
        foreach ($rows->cursor() as $row) {
            $this->document($period, $row);
        }
        $rows = DB::table('mfg_material_receipt_details as d')
            ->join('mfg_material_receipts as h', 'h.id', '=', 'd.receipt_id')
            ->leftJoin('mfg_suppliers as s', 's.id', '=', 'h.supplier_id')
            ->leftJoin('mfg_yarns as y', 'y.id', '=', 'd.yarn_id')
            ->leftJoin('mfg_fabrics as f', 'f.id', '=', 'd.fabric_id')
            ->where('h.status', 'POSTED')->whereBetween('h.receipt_date', [$start, $end])
            ->selectRaw("h.receipt_number as number, h.receipt_date as date, s.supplier_name as party, COALESCE(y.yarn_code, f.fabric_code, d.item_name) as code, d.item_name as name, d.qty, d.unit, d.amount");
        foreach ($rows->cursor() as $row) {
            $this->document($period, $row);
        }
    }

    private function shipments(ReportPeriod $period, string $start, string $end): void
    {
        // Only invoices with a corresponding stock-out count as physical shipments.
        $rows = DB::table('sales_invoice_details as d')
            ->join('sales_invoices as h', 'h.id', '=', 'd.sales_invoice_id')
            ->join('products as p', 'p.id', '=', 'd.product_id')
            ->whereBetween('h.transaction_date', [$start, $end])
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('inventory_ledgers as l')
                    ->whereColumn('l.evidence_number', 'h.invoice_number')->whereColumn('l.product_id', 'd.product_id')->where('l.type', 'OUT');
            })
            ->selectRaw('h.invoice_number as number, h.transaction_date as date, h.contact_name as party, p.sku as code, d.description as name, d.qty_actual as qty, p.unit, d.amount');
        foreach ($rows->cursor() as $row) {
            $this->document($period, $row);
        }
    }

    private function wip(ReportPeriod $period, string $end): void
    {
        foreach (DB::table('mfg_work_orders')->where('order_date', '<=', $end)->cursor() as $wo) {
            $cut = DB::table('mfg_cutting_checks as c')->join('mfg_cutting_orders as o', 'o.id', '=', 'c.cutting_order_id')
                ->where('o.work_order_id', $wo->id)->where('c.check_date', '<=', $end)
                ->when(Schema::hasColumn('mfg_cutting_checks', 'voided_at'), fn ($q) =>
                    $q->where(fn ($filter) => $filter->whereNull('c.voided_at')->orWhereDate('c.voided_at', '>', $end)))
                ->sum('c.pieces_ok');
            $reject = DB::table('mfg_finishing_stages')->where('work_order_id', $wo->id)->where('stage_date', '<=', $end)->sum('pieces_rejected');
            $completed = DB::table('inventory_ledgers')->where('evidence_number', $wo->spk_number)->where('type', 'IN')->where('transaction_date', '<=', $end)->sum('qty');
            $qty = max(0, $cut - $reject - $completed);
            if ($qty > 0) {
                PosisiLine::create(['report_period_id' => $period->id, 'kode_barang' => $wo->spk_number,
                    'nama_barang' => $wo->garment_name ?? $wo->style_sku ?? $wo->spk_number,
                    'satuan_barang' => 'PCS', 'jumlah_barang' => $qty,
                    'keterangan' => 'WIP fisik: hasil cutting OK dikurangi reject finishing dan barang jadi diterima, per '.$end]);
            }
        }
    }

    private function movement(ReportPeriod $period, string $code, string $name, string $unit, mixed $opening, mixed $incoming, string $note): void
    {
        MutasiLine::create(['report_period_id' => $period->id, 'kode_barang' => $code, 'nama_barang' => $name,
            'satuan_barang' => $unit, 'jumlah_barang' => 0, 'saldo_awal' => $opening,
            'jumlah_pemasukan_barang' => $incoming, 'jumlah_pengeluaran_barang' => 0,
            'penyesuaian_adjustment' => 0, 'saldo_akhir' => $opening + $incoming,
            'hasil_pencacahan' => 'Belum', 'jumlah_selisih' => 0, 'keterangan' => $note]);
    }

    private function assets(ReportPeriod $period, string $start, string $end): void
    {
        foreach (DB::table('assets')->where('purchase_date', '<=', $end)->cursor() as $asset) {
            $opening = $asset->purchase_date < $start ? $asset->quantity : 0;
            $incoming = $asset->purchase_date >= $start ? $asset->quantity : 0;
            $this->movement($period, $asset->asset_code, $asset->asset_name, 'UNIT', $opening, $incoming,
                'Register aset: perolehan tercatat. Histori pelepasan belum tersedia; status nonaktif tidak dianggap pengeluaran.');
        }
    }

    private function rejects(ReportPeriod $period, string $start, string $end): void
    {
        // Separate stages and units: never add kilograms to pieces or re-label rejects as good stock.
        $sources = [
            ['mfg_grey_fabric_receipts', 'receipt_date', 'qty_rejected', 'GREY', 'fabric_id', null],
            ['mfg_fabric_receipts', 'receipt_date', 'qty_rejected', 'FABRIC', 'fabric_id', null],
            ['mfg_cutting_checks', 'check_date', 'pieces_rejected', 'CUT-REJECT', 'cutting_order_id', 'PCS'],
            ['mfg_cutting_checks', 'check_date', 'scrap_kg', 'CUT-SCRAP', 'cutting_order_id', 'KGS'],
            ['mfg_cutting_checks', 'check_date', 'fabric_wastage_kg', 'CUT-WASTE', 'cutting_order_id', 'KGS'],
            ['mfg_finishing_stages', 'stage_date', 'pieces_rejected', 'FINISH', 'stitching_order_id', 'PCS'],
        ];
        foreach ($sources as [$table, $date, $qty, $prefix, $item, $unit]) {
            // Older operational schemas do not yet record valued scrap separately.
            if (! Schema::hasColumn($table, $qty)) {
                continue;
            }
            $query = DB::table($table)->where($date, '<=', $end)->where($qty, '>', 0);
            if ($table === 'mfg_cutting_checks' && Schema::hasColumn($table, 'voided_at')) {
                $query->where(fn ($q) => $q->whereNull('voided_at')->orWhereDate('voided_at', '>', $end));
            }
            foreach ($query->cursor() as $row) {
                $actualUnit = $unit ?? DB::table('mfg_fabrics')->where('id', $row->fabric_id)->value('unit');
                $opening = $row->{$date} < $start ? $row->{$qty} : 0;
                $incoming = $row->{$date} >= $start ? $row->{$qty} : 0;
                $this->movement($period, $prefix.'-'.$row->id, $prefix.' #'.$row->{$item}, $actualUnit ?? '', $opening, $incoming,
                    'Akumulasi reject/sisa per kejadian '.$row->{$date}.'. Pengeluaran reject belum dicatat dalam sistem; bukan hasil pencacahan fisik.');
            }
        }
    }
}