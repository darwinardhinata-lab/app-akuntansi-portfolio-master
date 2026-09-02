@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('divisi.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Pengaturan' => '#', 'Divisi' => route('divisi.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plus-circle me-2 text-primary"></i> Tambah Divisi Baru</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('divisi.store') }}" method="POST">
                @csrf
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Divisi</label>
                    <input type="text" name="nama_divisi" class="form-control" placeholder="Contoh: Divisi Keuangan" required>
                </div>
                <button type="submit" class="btn btn-primary fw-bold">Simpan</button>
                <a href="{{ route('divisi.index') }}" class="btn btn-secondary fw-bold">Batal</a>
            </form>
        </div>
    </div>
</div>
@endsection