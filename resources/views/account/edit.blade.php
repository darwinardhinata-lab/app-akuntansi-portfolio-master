@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('account.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Akuntansi' => '#', 'Daftar Akun (COA)' => route('account.index'), 'Edit' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square me-2"></i> Edit Master Akun (COA)</h4>
        </div>
        <form action="{{ route('account.update', $account->account_code) }}" method="POST" class="card-body p-4">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Kode Akun (ID)</label>
                    <input type="text" class="form-control fw-bold bg-light" value="{{ $account->account_code }}" disabled>
                    <small class="text-muted" style="font-size: 0.7rem;">Kode akun tidak dapat diubah untuk menjaga integritas jurnal.</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Akun *</label>
                    <input type="text" name="account_name" class="form-control" value="{{ $account->account_name }}" required>
                </div>
                
                @php 
                    $type = $account->coa_type; 
                @endphp
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Tipe Akun (COA Type) *</label>
                    <select name="coa_type" class="form-select fw-bold text-primary" required>
                        <option value="">-- Pilih Kategori Akun --</option>
                        <option value="Cash & Bank" {{ $type == 'Cash & Bank' ? 'selected' : '' }}>Cash & Bank</option>
                        <option value="Piutang Dagang" {{ $type == 'Piutang Dagang' ? 'selected' : '' }}>Piutang Dagang</option>
                        <option value="Persediaan" {{ $type == 'Persediaan' ? 'selected' : '' }}>Persediaan</option>
                        <option value="Aset Lancar Lainnya" {{ $type == 'Aset Lancar Lainnya' ? 'selected' : '' }}>Aset Lancar Lainnya</option>
                        <option value="Aset Tetap" {{ $type == 'Aset Tetap' ? 'selected' : '' }}>Aset Tetap</option>
                        <option value="Investasi Jangka Panjang" {{ $type == 'Investasi Jangka Panjang' ? 'selected' : '' }}>Investasi Jangka Panjang</option>
                        <option value="Hutang Dagang" {{ $type == 'Hutang Dagang' ? 'selected' : '' }}>Hutang Dagang</option>
                        <option value="Hutang Lainnya" {{ $type == 'Hutang Lainnya' ? 'selected' : '' }}>Hutang Lainnya</option>
                        <option value="Hutang Jangka Panjang" {{ $type == 'Hutang Jangka Panjang' ? 'selected' : '' }}>Hutang Jangka Panjang</option>
                        <option value="Modal" {{ $type == 'Modal' ? 'selected' : '' }}>Modal</option>
                        <option value="Pendapatan" {{ $type == 'Pendapatan' ? 'selected' : '' }}>Pendapatan</option>
                        <option value="Pendapatan Lainnya" {{ $type == 'Pendapatan Lainnya' ? 'selected' : '' }}>Pendapatan Lainnya</option>
                        <option value="Harga Pokok Penjualan" {{ $type == 'Harga Pokok Penjualan' ? 'selected' : '' }}>Harga Pokok Penjualan</option>
                        <option value="Biaya" {{ $type == 'Biaya' ? 'selected' : '' }}>Biaya</option>
                        <option value="Biaya Lainnya" {{ $type == 'Biaya Lainnya' ? 'selected' : '' }}>Biaya Lainnya</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Pos Saldo Normal *</label>
                    <select name="normal_balance" class="form-select" required>
                        <option value="DEBET" {{ $account->normal_balance == 'DEBET' ? 'selected' : '' }}>DEBET</option>
                        <option value="KREDIT" {{ $account->normal_balance == 'KREDIT' ? 'selected' : '' }}>KREDIT</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">Pos Laporan *</label>
                    <select name="report_pos" class="form-select" required>
                        <option value="NERACA" {{ $account->report_pos == 'NERACA' ? 'selected' : '' }}>NERACA</option>
                        <option value="LABA RUGI" {{ $account->report_pos == 'LABA RUGI' ? 'selected' : '' }}>LABA RUGI</option>
                    </select>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('account.index') }}" class="btn btn-outline-secondary fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>
@endsection