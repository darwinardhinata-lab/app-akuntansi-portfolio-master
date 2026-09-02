@extends('layouts.app')
@section('top_bar_left')
    <x-breadcrumb :links="['Pembelian' => '#', 'Retur Pembelian' => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Retur Pembelian</h3>
            <p class="text-muted small mb-0">Manajemen pengembalian barang ke Supplier (Debit Note).</p>
        </div>
        <div>
            <a href="{{ route('purchase-returns.create') }}" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat Retur Baru
            </a>
        </div>
    </div>
    @if(session('success'))
        <div class="alert alert-success fw-bold shadow-sm"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}</div>
    @endif
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">Tanggal</th>
                        <th class="py-3">No. Retur</th>
                        <th class="py-3">Ref. PO Asli</th>
                        <th class="text-center py-3">Status</th>
                        <th class="text-center pe-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $r)
                    <tr>
                        <td class="ps-4 fw-medium">{{ \Carbon\Carbon::parse($r->return_date)->format('d M Y') }}</td>
                        <td class="fw-bold text-danger">{{ $r->return_number }}</td>
                        <td class="fw-bold text-primary">{{ $r->purchaseOrder->po_number ?? '-' }}</td>
                        <td class="text-center"><span class="badge bg-warning text-dark">{{ $r->status }}</span></td>
                        <td class="text-center pe-4">
                            <button class="btn btn-sm btn-outline-secondary" disabled>Detail</button>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">Belum ada data Retur Pembelian.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection