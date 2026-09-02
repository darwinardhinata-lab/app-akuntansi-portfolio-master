@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Penjualan' => '#', 'Retur Penjualan' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Retur Penjualan</h3>
            <p class="text-muted small mb-0">Pemeriksaan fisik barang retur dan resolusi jurnal akuntansi.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <a href="{{ route('sales-returns.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat Retur Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show border-start border-success border-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show border-start border-danger border-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> Filter Analitik Pencarian</div>
        <form action="{{ route('sales-returns.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Mulai Tgl</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Sampai Tgl</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Status Retur</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="PENDING_INSPECTION" {{ request('status') == 'PENDING_INSPECTION' ? 'selected' : '' }}>Pending (Gudang)</option>
                    <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>Disetujui</option>
                    <option value="REJECTED" {{ request('status') == 'REJECTED' ? 'selected' : '' }}>Ditolak</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Pencarian Nomor</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik No Retur..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('sales-returns.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white p-3 border-bottom">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-boxes-return text-danger me-2"></i> Daftar Retur Penjualan</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark">
                    <tr>
                        <th class="ps-4 py-3">Tanggal</th>
                        <th class="py-3">No. Retur</th>
                        <th class="py-3">Ref. Faktur</th>
                        <th class="text-center py-3">Status</th>
                        <th class="text-center py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($returns as $ret)
                    <tr>
                        <td class="ps-4 fw-medium">{{ date('d M Y', strtotime($ret->return_date)) }}</td>
                        <td class="fw-bold text-danger">{{ $ret->return_number }}</td>
                        <td class="fw-bold text-primary">{{ $ret->invoice?->invoice_number ?? '-' }}</td>
                        <td class="text-center">
                            @php
                                $badge = match($ret->status) {
                                    'PENDING_INSPECTION' => 'bg-warning text-dark',
                                    'APPROVED'           => 'bg-success',
                                    'REJECTED'           => 'bg-danger',
                                    'FAILED_DELIVERY'    => 'bg-secondary',
                                    default              => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $badge }} px-3 py-2">{{ str_replace('_', ' ', $ret->status) }}</span>
                        </td>
                        <td class="text-center">
                            <a href="{{ route('sales-returns.show', $ret->id) }}" class="btn btn-sm btn-outline-primary">
                                <i class="fa-solid fa-eye me-1"></i> Detail
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="5" class="text-center py-5 text-muted">Belum ada data retur penjualan.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white p-3 d-flex justify-content-end">
            {{ $returns->links() }}
        </div>
    </div>
</div>
@endsection