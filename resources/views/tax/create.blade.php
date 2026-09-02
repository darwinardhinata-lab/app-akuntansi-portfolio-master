@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('tax.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Pembelian' => '#', 'Pajak' => route('tax.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">Tambah Pajak Baru</h4>
        </div>
        <form action="{{ route('tax.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Pajak *</label>
                    <input type="text" name="tax_name" class="form-control fw-bold" placeholder="Contoh: PPN 11%" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Tipe Pajak *</label>
                    <select name="type" class="form-select" required>
                        <option value="addition">Penambah (PPN)</option>
                        <option value="deduction">Pemotong (PPh)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Rate (%) *</label>
                    <input type="number" name="rate" class="form-control fw-bold text-primary" value="0" step="0.01" required>
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('tax.index') }}" class="btn btn-outline-secondary fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Pajak</button>
            </div>
        </form>
    </div>
</div>
@endsection