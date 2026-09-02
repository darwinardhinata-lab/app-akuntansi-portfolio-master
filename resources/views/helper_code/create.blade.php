@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('helper.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Master Data' => '#', 'Helper Code' => route('helper.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">Tambah Kode Bantu Baru</h4>
        </div>
        <form action="{{ route('helper.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Kode Bantu (ID)</label>
                    <input type="text" name="helper_code" class="form-control fw-bold" placeholder="Contoh: CUST-001" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Pos Saldo Normal</label>
                    <select name="normal_balance" class="form-select fw-bold" required>
                        <option value="DEBET">DEBET</option>
                        <option value="KREDIT">KREDIT</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Entitas / Toko</label>
                    <input type="text" name="entity_name" class="form-control" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Marketing / PIC (Opsional)</label>
                    <input type="text" name="marketing_name" class="form-control">
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('helper.index') }}" class="btn btn-outline-secondary fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Kode Bantu</button>
            </div>
        </form>
    </div>
</div>
@endsection