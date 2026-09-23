@extends('layouts.app')
@section('top_bar_left')
<x-breadcrumb :links="['ERP' => '#', 'Laporan CEISA' => route('customs-reports.index')]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Laporan CEISA</h3>
            <p class="text-muted small mb-0">7 jenis laporan bea cukai (manual / Excel).</p>
        </div>
        <a href="{{ route('customs-reports.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm">
            <i class="fa-solid fa-plus me-1"></i> Buat Draft Periode
        </a>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <select name="report_type" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Jenis Laporan</option>
                        @foreach($typeLabels as $val => $lbl)
                            <option value="{{ $val }}" {{ ($filters['report_type'] ?? '') == $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0" style="font-size:13px">
                <thead class="bg-light">
                    <tr>
                        <th class="text-center">#</th><th>Jenis Laporan</th>
                        <th class="text-center">Bulan</th><th class="text-center">Tahun</th>
                        <th class="text-center">Status</th><th class="text-center">Finalized</th>
                        <th class="text-center">Diunggah</th><th class="text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($periods as $period)
                        @php
                            $sc = match($period->status) {
                                'DRAFT'=>'bg-secondary','FINAL'=>'bg-warning text-dark',
                                'DIUNGGAH'=>'bg-success',default=>'bg-secondary' };
                        @endphp
                        <tr>
                            <td class="text-center">{{ $periods->firstItem() + $loop->index }}</td>
                            <td><span class="fw-medium">{{ $period->label() }}</span>
                                @if($period->catatan)<br><small class="text-muted">{{ \Illuminate\Support\Str::limit($period->catatan, 60) }}</small>@endif</td>
                            <td class="text-center">{{ $period->periode_bulan }}</td>
                            <td class="text-center">{{ $period->periode_tahun }}</td>
                            <td class="text-center"><span class="badge {{ $sc }}">{{ $period->status }}</span></td>
                            <td class="text-center">{{ $period->finalized_at ? $period->finalized_at->format('d/m/Y') : '-' }}</td>
                            <td class="text-center">{{ $period->uploaded_at ? $period->uploaded_at->format('d/m/Y') : '-' }}</td>
                            <td class="text-center">
                                <a href="{{ route('customs-reports.show', $period) }}" class="btn btn-sm btn-outline-primary" title="Lihat"><i class="fa-solid fa-eye"></i></a>
                                @if($period->isDraft())
                                    <a href="{{ route('customs-reports.edit', $period) }}" class="btn btn-sm btn-outline-secondary" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                    <form action="{{ route('customs-reports.destroy', $period) }}" method="POST" onsubmit="return confirm('Hapus? Semua line items akan hilang.')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted py-4">
                            <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                            <p>Belum ada periode laporan.</p>
                        </td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($periods->hasPages())
        <div class="d-flex justify-content-center mt-3">{{ $periods->links() }}</div>
    @endif
</div>
@endsection