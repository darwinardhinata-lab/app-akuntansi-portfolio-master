@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Manufaktur' => '#', 'Material Receipt (MRN)' => route('mfg.material-receipts.index'), $receipt->receipt_number => null]" />
@endsection

@push('styles')
<style>
    @media print {
        .sidebar, .topbar, header, .bottom-nav, .no-print { display: none !important; }
        .card { border: 1px solid #ddd !important; box-shadow: none !important; }
    }
</style>
@endpush

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-end gap-2 mb-2 no-print">
        <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3">
            <i class="fa-solid fa-print me-1"></i> Cetak MRN
        </button>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h3 class="fw-bold mb-1">{{ $receipt->receipt_number }} <span class="badge {{ $receipt->status === 'POSTED' ? 'bg-success' : ($receipt->status === 'VOIDED' ? 'bg-danger' : 'bg-secondary') }}">{{ $receipt->status }}</span></h3>
                    <p class="text-muted mb-0">Supplier: {{ $receipt->supplier->supplier_name ?? '-' }} · Tanggal: {{ \Carbon\Carbon::parse($receipt->receipt_date)->format('d M Y') }}</p>
                    @if($receipt->supplier_doc_no)<p class="text-muted small mb-0">No. Dokumen Supplier: {{ $receipt->supplier_doc_no }}</p>@endif
                </div>
                <div class="text-end">
                    <div class="small text-muted">Bruto: Rp {{ number_format($receipt->gross_amount, 2) }}</div>
                    <div class="small text-muted">Pajak: Rp {{ number_format($receipt->tax_amount, 2) }}</div>
                    <div class="fw-bold text-primary">Neto: Rp {{ number_format($receipt->net_amount, 2) }}</div>
                    @if($receipt->status === 'POSTED')
                        <form action="{{ route('mfg.material-receipts.void', $receipt->id) }}" method="POST" class="mt-2 no-print"
                              onsubmit="return confirm('Yakin void MRN ini? Jurnal & kartu stok akan dibalik. Hanya bisa jika stoknya belum terpakai.')">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-rotate-left me-1"></i>Void MRN</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><b>Item Bahan Baku</b></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" style="font-size: 13.5px;">
                    <thead class="table-light"><tr><th>Tipe</th><th>Item</th><th>Qty</th><th>Satuan</th><th>Rate</th><th>Jumlah</th><th>Lot</th></tr></thead>
                    <tbody>
                        @foreach($receipt->details as $d)
                            <tr>
                                <td><span class="badge bg-info text-dark">{{ $d->item_type }}</span></td>
                                <td>{{ $d->item_name }} <small class="text-muted">({{ $d->yarn->yarn_code ?? $d->fabric->fabric_code ?? '-' }})</small></td>
                                <td class="text-end">{{ number_format($d->qty, 2) }}</td>
                                <td class="text-center">{{ $d->unit }}</td>
                                <td class="text-end">Rp {{ number_format($d->rate, 2) }}</td>
                                <td class="text-end fw-bold">Rp {{ number_format($d->amount, 2) }}</td>
                                <td>{{ $d->lot_number ?? '-' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    @if($receipt->journal)
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-white"><b>Jurnal Terkait ({{ $receipt->journal->journal_id }})</b></div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" style="font-size: 13.5px;">
                    <thead class="table-light"><tr><th>Akun</th><th>Posisi</th><th>Jumlah</th></tr></thead>
                    <tbody>
                        @foreach($receipt->journal->details as $jd)
                            <tr>
                                <td>{{ $jd->account_code }}</td>
                                <td><span class="badge {{ $jd->position === 'DEBET' ? 'bg-primary' : 'bg-danger' }}">{{ $jd->position }}</span></td>
                                <td class="text-end">Rp {{ number_format($jd->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    @endif

    <a href="{{ route('mfg.material-receipts.index') }}" class="btn btn-outline-secondary no-print">&larr; Kembali</a>
</div>
@endsection
