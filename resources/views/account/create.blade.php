@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('account.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Akuntansi' => '#', 'Daftar Akun (COA)' => route('account.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-primary text-white border-bottom p-4">
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-plus me-2"></i> Tambah Master Akun (COA)</h4>
        </div>
        <form action="{{ route('account.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Kode Akun *</label>
                    <input type="text" name="account_code" class="form-control fw-bold" placeholder="Contoh: 11101" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Akun *</label>
                    <input type="text" name="account_name" class="form-control" placeholder="Contoh: Kas Besar" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Tipe Akun (COA Type) *</label>
                    <select name="coa_type" class="form-select fw-bold text-primary" required>
                        <option value="">-- Pilih Kategori Akun --</option>
                        <option value="Cash & Bank">Cash & Bank</option>
                        <option value="Piutang Dagang">Piutang Dagang</option>
                        <option value="Persediaan">Persediaan</option>
                        <option value="Aset Lancar Lainnya">Aset Lancar Lainnya</option>
                        <option value="Aset Tetap">Aset Tetap</option>
                        <option value="Investasi Jangka Panjang">Investasi Jangka Panjang</option>
                        <option value="Hutang Dagang">Hutang Dagang</option>
                        <option value="Hutang Lainnya">Hutang Lainnya</option>
                        <option value="Hutang Jangka Panjang">Hutang Jangka Panjang</option>
                        <option value="Modal">Modal</option>
                        <option value="Pendapatan">Pendapatan</option>
                        <option value="Pendapatan Lainnya">Pendapatan Lainnya</option>
                        <option value="Harga Pokok Penjualan">Harga Pokok Penjualan</option>
                        <option value="Biaya">Biaya</option>
                        <option value="Biaya Lainnya">Biaya Lainnya</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Pos Saldo Normal *</label>
                    <select name="normal_balance" class="form-select" required>
                        <option value="DEBET">DEBET</option>
                        <option value="KREDIT">KREDIT</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Pos Laporan *</label>
                    <select name="report_pos" class="form-select" required>
                        <option value="NERACA">NERACA</option>
                        <option value="LABA RUGI">LABA RUGI</option>
                    </select>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('account.index') }}" class="btn btn-outline-secondary fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> Simpan Akun</button>
            </div>
        </form>
    </div>
</div>
@endsection