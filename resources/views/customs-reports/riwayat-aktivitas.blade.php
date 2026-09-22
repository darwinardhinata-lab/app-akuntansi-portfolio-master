@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['ERP' => '#', 'Riwayat Aktivitas' => route('customs-reports.riwayat-aktivitas')]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="mb-4">
        <h3 class="fw-bold mb-1 text-dark">Riwayat Aktivitas</h3>
        <p class="text-muted small mb-0">Aktivitas pengguna yang telah tercatat di sistem.</p>
    </div>

    <div class="alert alert-info shadow-sm border-0 small" role="alert">
        <i class="fa-solid fa-circle-info me-1"></i>
        Laporan ini menampilkan aktivitas yang sudah tercatat sistem. Beberapa jenis transaksi (mis. penerimaan barang, Sales Order) belum otomatis tercatat — lihat rekomendasi cakupan logging untuk pengembangan lebih lanjut.
    </div>

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label for="date_from" class="form-label small text-muted mb-1">Dari</label>
                    <input type="date" name="date_from" id="date_from" value="{{ $filters['date_from'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-3">
                    <label for="date_to" class="form-label small text-muted mb-1">Sampai</label>
                    <input type="date" name="date_to" id="date_to" value="{{ $filters['date_to'] ?? '' }}" class="form-control">
                </div>
                <div class="col-md-4">
                    <label for="keyword" class="form-label small text-muted mb-1">Kata Kunci Keterangan</label>
                    <input type="text" name="keyword" id="keyword" value="{{ $filters['keyword'] ?? '' }}" class="form-control" placeholder="Cari keterangan aktivitas">
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="{{ route('customs-reports.riwayat-aktivitas') }}" class="btn btn-outline-secondary">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-sm table-striped align-middle mb-0" style="font-size: 13px">
                <thead class="bg-light">
                    <tr>
                        <th>Tanggal</th>
                        <th>Type Transaksi</th>
                        <th>Nomor Transaksi</th>
                        <th>Pengguna</th>
                        <th>Waktu Input</th>
                        <th>Keterangan</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activities as $activity)
                        <tr>
                            <td>{{ $activity->created_at->format('d/m/Y') }}</td>
                            <td>{{ $activity->module }} + {{ $activity->action }}</td>
                            <td>{{ $activityReportService->extractTransactionNumber($activity->description) ?? '—' }}</td>
                            <td>{{ $activity->user?->name ?? '—' }}</td>
                            <td>{{ $activity->created_at->format('H:i:s') }}</td>
                            <td>{{ $activity->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center text-muted py-4">
                                <i class="fa-solid fa-inbox fa-2x mb-2"></i>
                                <p class="mb-0">Belum ada aktivitas yang sesuai.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if($activities->hasPages())
        <div class="d-flex justify-content-center mt-3">{{ $activities->links() }}</div>
    @endif
</div>
@endsection