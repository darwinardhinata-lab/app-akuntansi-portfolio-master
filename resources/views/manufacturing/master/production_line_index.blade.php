@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', 'Master Line Produksi' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div><h3 class="fw-bold mb-1 text-dark">Master Line Produksi</h3><p class="text-muted small mb-0">Line proses produksi terpisah dari master Gudang.</p></div>
        <button type="button" class="btn btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreate"><i class="fa-solid fa-plus me-1"></i> Tambah Line</button>
    </div>
    @if(session('success'))<div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <div class="card shadow-sm border-0 mb-3"><div class="card-body py-2"><form method="GET" class="row g-2"><div class="col-md-4"><input name="search" class="form-control form-control-sm" value="{{ $search }}" placeholder="Cari kode, nama, atau area line"></div><div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Cari</button></div></form></div></div>
    <div class="card shadow-sm border-0"><div class="card-body p-0"><div class="table-responsive"><table class="table table-bordered table-striped table-hover align-middle mb-0"><thead class="bg-primary text-white text-center"><tr><th>Kode</th><th>Nama Line</th><th>Area</th><th>Kapasitas/Hari</th><th>Status</th><th>Aksi</th></tr></thead><tbody>
    @forelse($lines as $line)<tr><td class="fw-bold">{{ $line->line_code }}</td><td>{{ $line->line_name }}</td><td>{{ $line->area ?: '-' }}</td><td class="text-end">{{ $line->daily_capacity ? number_format($line->daily_capacity) : '-' }}</td><td class="text-center"><span class="badge {{ $line->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $line->is_active ? 'AKTIF' : 'NONAKTIF' }}</span></td><td class="text-center"><button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#edit{{ $line->id }}">Edit</button>@if($line->is_active)<form class="d-inline" method="POST" action="{{ route('mfg.production-lines.destroy', $line->id) }}" onsubmit="return confirm('Nonaktifkan line ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Nonaktifkan</button></form>@endif</td></tr>
    <div class="modal fade" id="edit{{ $line->id }}" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('mfg.production-lines.update', $line->id) }}">@csrf @method('PUT')<div class="modal-content"><div class="modal-header"><h5 class="modal-title">Edit Line Produksi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">@include('manufacturing.master.production_line_fields', ['line' => $line])</div><div class="modal-footer"><button class="btn btn-primary">Simpan</button></div></div></form></div></div>
    @empty<tr><td colspan="6" class="text-center py-5 text-muted">Belum ada Line Produksi.</td></tr>@endforelse
    </tbody></table></div></div></div>{{ $lines->links() }}
</div>
<div class="modal fade" id="modalCreate" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('mfg.production-lines.store') }}">@csrf<div class="modal-content"><div class="modal-header"><h5 class="modal-title">Tambah Line Produksi</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body">@include('manufacturing.master.production_line_fields', ['line' => null])</div><div class="modal-footer"><button class="btn btn-primary">Simpan</button></div></div></form></div></div>
@endsection