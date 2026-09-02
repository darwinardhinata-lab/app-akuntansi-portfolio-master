@extends('layouts.app')
@section('top_bar_left')
    <x-breadcrumb :links="['Warehouse' => '#', 'Barang Keluar' => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-arrow-right-from-bracket text-danger me-2"></i>Barang Keluar (Outbound)</h3>
            <p class="text-muted small mb-0">Riwayat pengeluaran barang dan mutasi keluar lainnya.</p>
        </div>
        <div>
            <a href="{{ route('warehouse.outbound.create') }}" class="btn btn-danger fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat Pengeluaran Manual
            </a>
        </div>
    </div>

    {{-- SUB-SUB-MENU / NAV PILLS --}}
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded border shadow-sm flex-nowrap overflow-auto" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'transfer_keluar' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.outbound', ['tab' => 'transfer_keluar']) }}">Transfer Keluar</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'retur_pembelian' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.outbound', ['tab' => 'retur_pembelian']) }}">Retur Pembelian</a>
        </li>
    </ul>

    @if(session('success')) <div class="alert alert-success fw-bold shadow-sm">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger fw-bold shadow-sm">{{ session('error') }}</div> @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">Tanggal</th>
                        <th class="py-3">No. Referensi</th>
                        <th class="py-3">Produk / SKU</th>
                        <th class="text-center py-3">Qty Keluar</th>
                        <th class="text-end pe-4 py-3">HPP / Unit (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgers as $l)
                    <tr>
                        <td class="ps-4 fw-medium">{{ \Carbon\Carbon::parse($l->transaction_date)->format('d M Y') }}</td>
                        <td class="fw-bold text-primary">{{ $l->evidence_number }}</td>
                        <td class="fw-bold text-dark">{{ $l->product->sku ?? '-' }} <br><span class="text-muted small fw-normal">{{ $l->product->name ?? '-' }}</span></td>
                        <td class="text-center fw-bold text-danger">-{{ $l->qty }}</td>
                        <td class="text-end pe-4 font-monospace">{{ number_format($l->unit_cost, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada riwayat barang keluar.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">{{ $ledgers->links() }}</div>
    </div>
</div>
@endsection