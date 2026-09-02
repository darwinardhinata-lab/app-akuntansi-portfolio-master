@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('product.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Master Data' => '#', 'Produk' => route('product.index'), 'Edit' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark">Edit Master Barang</h4>
        </div>
        <form action="{{ route('product.update', $product->id) }}" method="POST" class="card-body p-4">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">SKU (Kode Barang)</label>
                    <input type="text" name="sku" class="form-control fw-bold" value="{{ $product->sku }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Produk</label>
                    <input type="text" name="name" class="form-control" value="{{ $product->name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Kategori</label>
                    <input type="text" name="category_name" class="form-control" value="{{ $product->category_name }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Varian</label>
                    <input type="text" name="variation" class="form-control" value="{{ $product->variation }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Harga Jual (Rp)</label>
                    <input type="number" name="sell_price" class="form-control fw-bold text-success" value="{{ (int)$product->sell_price }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Stok Aktual</label>
                    <input type="number" name="stock_quantity" class="form-control fw-bold text-primary" value="{{ $product->stock_quantity }}" required>
                </div>
            </div>
            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('product.index') }}" class="btn btn-outline-secondary fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection
