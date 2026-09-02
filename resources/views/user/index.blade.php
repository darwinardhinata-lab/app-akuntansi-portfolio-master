@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Pengaturan' => '#', 'Manajemen Pengguna' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Manajemen Pengguna</h3>
            <p class="text-muted small mb-0">Kontrol hak akses, jabatan, dan divisi pengguna sistem ERP.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <a href="{{ route('users.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success shadow-sm fw-bold"><i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger shadow-sm fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}</div>
    @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> Filter Analitik Pencarian</div>
        <form action="{{ route('users.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Role / Hak Akses</label>
                <select name="role" class="form-select form-select-sm">
                    <option value="">Semua Role</option>
                    <option value="ADMIN" {{ request('role') == 'ADMIN' ? 'selected' : '' }}>ADMIN</option>
                    <option value="FINANCE" {{ request('role') == 'FINANCE' ? 'selected' : '' }}>FINANCE</option>
                    <option value="STAFF" {{ request('role') == 'STAFF' ? 'selected' : '' }}>STAFF</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-6">
                <label class="form-label small fw-bold text-muted mb-1">Pencarian Nomor / Keterangan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik nama atau email..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('users.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-light text-muted text-uppercase" style="font-size: 0.75rem;">
                    <tr>
                        <th class="ps-4 py-3">Nama Lengkap</th>
                        <th>Email Login</th>
                        <th class="text-center">Hak Akses (Role)</th>
                        <th class="text-center">Bagian / Divisi</th>
                        <th class="text-center pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($users as $u)
                    <tr>
                        <td class="ps-4 py-3 fw-bold text-dark">{{ $u->name }}</td>
                        <td class="text-muted">{{ $u->email }}</td>
                        <td class="text-center">
                            @if($u->role == 'ADMIN') <span class="badge bg-danger">ADMINISTRATOR</span>
                            @elseif($u->role == 'FINANCE') <span class="badge bg-primary">FINANCE</span>
                            @else <span class="badge bg-secondary">STAFF BIASA</span> @endif
                        </td>
                        <td class="text-center fw-medium">{{ $u->nama_divisi ?? 'SEMUA DIVISI (ALL)' }}</td>
                        <td class="text-center pe-4">
                            <a href="{{ route('users.edit', $u->id) }}" class="btn btn-sm btn-outline-warning"><i class="fa-solid fa-pen-to-square"></i></a>
                            <form action="{{ route('users.destroy', $u->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus pengguna ini secara permanen?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger" {{ auth()->id() == $u->id ? 'disabled' : '' }}><i class="fa-solid fa-trash-can"></i></button>
                            </form>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection