@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('users.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Pengaturan' => '#', 'Manajemen Pengguna' => route('users.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-plus me-2 text-primary"></i> Tambah Pengguna Baru</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('users.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Role / Hak Akses</label>
                    <select name="role" class="form-select">
                        <option value="STAFF">STAFF</option>
                        <option value="FINANCE">FINANCE</option>
                        <option value="ADMIN">ADMIN</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary fw-bold">Simpan</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary fw-bold">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection