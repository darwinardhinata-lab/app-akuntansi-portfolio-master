@extends('layouts.app')
@section('top_bar_left')
    <x-breadcrumb :links="['Warehouse' => '#', 'Barang Masuk' => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-arrow-right-to-bracket text-success me-2"></i>Barang Masuk (Inbound)</h3>
            <p class="text-muted small mb-0">Riwayat penerimaan barang dan mutasi masuk lainnya.</p>
        </div>
        <div>
            <a href="{{ route('warehouse.inbound.create') }}" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat Penerimaan Manual
            </a>
        </div>
    </div>

    {{-- SUB-SUB-MENU / NAV PILLS --}}
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded border shadow-sm flex-nowrap overflow-auto" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'penerimaan_barang' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'penerimaan_barang']) }}">Penerimaan Barang (BIL)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'pesanan_pembelian' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'pesanan_pembelian']) }}">Pesanan Pembelian (PO)</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'retur_online' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'retur_online']) }}">Retur Channel Online</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'transfer_masuk' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'transfer_masuk']) }}">Transfer Masuk</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'penempatan_barang' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'penempatan_barang']) }}">Penempatan Barang</a>
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
                        <th class="text-center py-3">Qty Masuk</th>
                        <th class="text-end pe-4 py-3">HPP / Unit (Rp)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgers as $l)
                    <tr>
                        <td class="ps-4 fw-medium">{{ \Carbon\Carbon::parse($l->transaction_date)->format('d M Y') }}</td>
                        <td class="fw-bold text-primary">{{ $l->evidence_number }}</td>
                        <td class="fw-bold text-dark">{{ $l->product->sku ?? '-' }} <br><span class="text-muted small fw-normal">{{ $l->product->name ?? '-' }}</span></td>
                        <td class="text-center fw-bold text-success">+{{ $l->qty }}</td>
                        <td class="text-end pe-4 font-monospace">{{ number_format($l->unit_cost, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada riwayat barang masuk.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">{{ $ledgers->links() }}</div>
    </div>
</div>
@endsection