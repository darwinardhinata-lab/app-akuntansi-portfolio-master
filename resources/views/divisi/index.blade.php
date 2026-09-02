@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Pengaturan' => '#', 'Divisi' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Master Divisi</h3>
            <p class="text-muted small mb-0">Kelola daftar divisi/bagian untuk struktur organisasi dan pengajuan Payment Plan.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <a href="{{ route('divisi.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success fw-bold shadow-sm">{{ session('success') }}</div>
    @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> Filter Analitik Pencarian</div>
        <form action="{{ route('divisi.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-12 col-md-8">
                <label class="form-label small fw-bold text-muted mb-1">Pencarian Nomor / Keterangan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik nama divisi..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('divisi.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-light text-uppercase text-muted">
                    <tr>
                        <th class="ps-4 py-3">Nama Divisi</th>
                        <th class="text-center py-3">Total Plot Akun</th>
                        <th class="text-center pe-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($divisi as $div)
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark">{{ $div->nama_divisi }}</td>
                        <td class="text-center py-3">{{ $div->akun_count ?? 0 }}</td>
                        <td class="text-center pe-4 py-3">
                            <div class="btn-group">
                                <button type="button" onclick="showEntityLog('{{ $div->kode_divisi }}')" class="btn btn-sm btn-outline-info shadow-sm" title="Jejak Log Aktivitas"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                <a href="{{ route('divisi.edit', $div->id_divisi) }}" class="btn btn-sm btn-outline-warning shadow-sm" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                <form action="{{ route('divisi.destroy', $div->id_divisi) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus divisi ini secara permanen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center py-5 text-muted">Belum ada data divisi.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection