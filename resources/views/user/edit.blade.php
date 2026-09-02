@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('users.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Pengaturan' => '#', 'Manajemen Pengguna' => route('users.index'), 'Edit' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto px-0">
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-user-edit me-2 text-primary"></i> Edit Pengguna</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('users.update', $user->id) }}" method="POST">
                @csrf @method('PUT')
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Lengkap</label>
                    <input type="text" name="name" class="form-control" value="{{ $user->name }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Email</label>
                    <input type="email" name="email" class="form-control" value="{{ $user->email }}" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Password (biarkan kosong jika tidak diubah)</label>
                    <input type="password" name="password" class="form-control">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Role / Hak Akses</label>
                    <select name="role" class="form-select">
                        <option value="STAFF" {{ $user->role == 'STAFF' ? 'selected' : '' }}>STAFF</option>
                        <option value="FINANCE" {{ $user->role == 'FINANCE' ? 'selected' : '' }}>FINANCE</option>
                        <option value="ADMIN" {{ $user->role == 'ADMIN' ? 'selected' : '' }}>ADMIN</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning fw-bold">Update</button>
                <a href="{{ route('users.index') }}" class="btn btn-secondary fw-bold">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection