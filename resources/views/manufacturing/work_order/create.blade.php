@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Manufaktur' => route('mfg.work-orders.index'), 'Surat Perintah Kerja (SPK)' => route('mfg.work-orders.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold mb-1 text-dark">Buat SPK Baru</h3>
    <p class="text-muted small mb-4">Header Surat Perintah Kerja — akan menampung seluruh biaya WIP mulai tahap Cutting.</p>

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-body">
            <form action="{{ route('mfg.work-orders.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Tanggal SPK</label>
                        <input type="date" name="order_date" class="form-control" value="{{ old('order_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Target Selesai</label>
                        <input type="date" name="target_date" class="form-control" value="{{ old('target_date') }}">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Style SKU (opsional)</label>
                        <input type="text" name="style_sku" class="form-control" value="{{ old('style_sku') }}" placeholder="STY-2026-001">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Nama Garmen</label>
                        <input type="text" name="garment_name" class="form-control" value="{{ old('garment_name') }}" required placeholder="Kaos Polo Lengan Pendek">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Qty Rencana (pcs)</label>
                        <input type="number" name="planned_qty" class="form-control" value="{{ old('planned_qty') }}" min="1" required>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Produk Tujuan (SKU Barang Jadi)</label>
                        <select name="product_id" class="form-select">
                            <option value="">-- Pilih nanti saat SPK selesai --</option>
                            @foreach($products as $p)
                                <option value="{{ $p->id }}" {{ old('product_id') == $p->id ? 'selected' : '' }}>{{ $p->sku ?? $p->id }} - {{ $p->name }}</option>
                            @endforeach
                        </select>
                        <div class="form-text">Bisa dikosongkan dulu, diisi saat SPK selesai (WorkOrderService::complete).</div>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Catatan</label>
                        <textarea name="remarks" class="form-control" rows="2">{{ old('remarks') }}</textarea>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> Simpan SPK</button>
                    <a href="{{ route('mfg.work-orders.index') }}" class="btn btn-outline-secondary px-4">Batal</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
