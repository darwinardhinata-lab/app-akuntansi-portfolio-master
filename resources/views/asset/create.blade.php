@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Akuntansi' => '#', 'Aset Management' => route('aset.index'), 'Setting Umur Penyusutan' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Setting Umur Penyusutan Aset</h3>
            <p class="text-muted small mb-0">Pilih aset yang belum mengalami penyusutan untuk mengatur lama masa pakai (umur penyusutan).</p>
        </div>
        <a href="{{ route('aset.index') }}" class="btn btn-outline-secondary btn-sm fw-bold shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali ke Daftar Aset
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if($assetsNeedingInput->isEmpty())
        <div class="alert alert-info shadow-sm fw-bold">
            <i class="fa-solid fa-circle-info me-2"></i> Semua aset sudah memiliki umur penyusutan. 
            Jika ingin menambah aset, buat jurnal pembelian dengan kode akun 12000 (Aset Tetap) di menu Jurnal Umum.
        </div>
    @else
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-header bg-white py-3">
                <h6 class="m-0 font-weight-bold text-primary">
                    <i class="fa-solid fa-clock-rotate-left me-2"></i> Daftar Aset Perlu Setting Umur ({{ $assetsNeedingInput->count() }} item)
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light text-uppercase text-muted" style="font-size: 0.75rem;">
                            <tr>
                                <th width="3%">No</th>
                                <th width="8%">Tgl Beli</th>
                                <th width="12%">Kode Aset</th>
                                <th width="18%">Nama Aset</th>
                                <th width="12%">Harga Perolehan</th>
                                <th width="10%" class="text-end">Nilai Sisa</th>
                                <th width="10%" class="text-center">Umur (Bulan)</th>
                                <th width="12%">Penyusutan / Bln</th>
                                <th width="13%">Akumulasi Depresiasi</th>
                                <th width="15%">Nilai Saldo Akhir</th>
                                <th width="10%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($assetsNeedingInput as $index => $asset)
                                <tr class="align-middle">
                                    <td class="text-center">{{ $index + 1 }}</td>
                                    <td class="text-center">{{ \Carbon\Carbon::parse($asset->purchase_date)->format('d/m/Y') }}</td>
                                    <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $asset->asset_code }}</span></td>
                                    <td class="fw-medium">{{ $asset->asset_name }}</td>
                                    <td class="text-end text-dark fw-medium">Rp {{ number_format($asset->purchase_price, 2, ',', '.') }}</td>
                                    <td class="text-end text-muted">Rp {{ number_format($asset->residual_value ?? 0, 2, ',', '.') }}</td>
                                    <form action="{{ route('aset.store') }}" method="POST" class="d-inline">
                                        @csrf
                                        <input type="hidden" name="asset_id" value="{{ $asset->id }}">
                                        <td class="text-center" width="120">
                                            <input type="number" name="useful_life_months" class="form-control form-control-sm text-center" 
                                                   value="{{ $asset->useful_life_months > 0 ? $asset->useful_life_months : '' }}" 
                                                   min="1" placeholder="Contoh: 36" required>
                                        </td>
                                        <td class="text-end text-danger" style="font-weight: 500;">
                                            Rp {{ number_format($asset->depreciation_per_month ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td class="text-end text-warning" style="font-weight: 500;">
                                            - Rp {{ number_format($asset->accumulated ?? 0, 2, ',', '.') }}
                                        </td>
                                        <td class="text-end text-success fw-bold">
                                            Rp {{ number_format($asset->book_value ?? $asset->purchase_price, 2, ',', '.') }}
                                        </td>
                                        <td class="text-center">
                                            <button type="submit" class="btn btn-sm btn-primary fw-bold">Simpan</button>
                                        </td>
                                    </form>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

</div>
@endsection