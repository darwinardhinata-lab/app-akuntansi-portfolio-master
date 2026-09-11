@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_asset_list') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h4 class="mb-0 text-gray-800">
            <i class="fa-solid fa-table-list text-primary me-2"></i> List Depresiasi Aset
        </h4>
        
        <div class="d-flex gap-2">
            {{-- 👇 TOMBOL SAKTI GENERATE PENYUSUTAN 👇 --}}
            <form action="{{ route('aset.generate_depreciation') }}" method="POST" onsubmit="return confirm('Proses Jurnal Penyusutan untuk bulan ini? Pastikan Anda belum memprosesnya agar tidak terjadi jurnal ganda.')">
                @csrf
                <button type="submit" class="btn btn-warning btn-sm fw-bold shadow-sm text-dark">
                    <i class="fa-solid fa-calculator me-1"></i> {{ __('erp.process_journal_btn') }}
                </button>
            </form>

            <a href="{{ route('aset.index') }}" class="btn btn-outline-primary btn-sm fw-bold shadow-sm bg-white">
                <i class="fa-solid fa-plus me-1"></i> Kelola Data Aset Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h6 class="m-0 font-weight-bold text-primary">
                Matriks Nilai Buku Aset per {{ \Carbon\Carbon::parse($today)->translatedFormat('d F Y') }}
            </h6>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover table-bordered mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light text-center align-middle">
                        <tr>
                            <th width="3%">{{ __('erp.no_abbr') }}</th>
                            <th width="8%">{{ __('erp.purchase_date_short') }}</th>
                            <th width="12%">{{ __('erp.asset_code') }}</th>
                            <th width="18%">{{ __('erp.asset_name') }}</th>
                            <th width="12%">{{ __('erp.purchase_price') }}</th>
                            <th width="7%">{{ __('erp.useful_life_months') }}</th>
                            <th width="12%">{{ __('erp.depreciation_per_month_v2') }}</th>
                            <th width="13%">{{ __('erp.accumulated_depreciation_v2') }}</th>
                            <th width="15%">{{ __('erp.ending_book_value') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($assets as $index => $asset)
                            <tr class="align-middle">
                                <td class="text-center">{{ $index + 1 }}</td>
                                <td class="text-center">{{ \Carbon\Carbon::parse($asset->purchase_date)->format('d/m/Y') }}</td>
                                <td class="text-center"><span class="badge bg-secondary">{{ $asset->asset_code }}</span></td>
                                <td class="fw-medium">{{ $asset->asset_name }}</td>
                                <td class="text-end">Rp {{ number_format($asset->purchase_price, 2, ',', '.') }}</td>
                                <td class="text-center">{{ $asset->useful_life_months }}</td>
                                <td class="text-end text-danger">
                                    Rp {{ number_format($asset->depreciation_per_month, 2, ',', '.') }}
                                </td>
                                <td class="text-end text-warning" style="font-weight: 500;">
                                    Rp {{ number_format($asset->accumulated, 2, ',', '.') }}
                                </td>
                                <td class="text-end text-success fw-bold">
                                    Rp {{ number_format($asset->book_value, 2, ',', '.') }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open mb-3" style="font-size: 2rem;"></i><br>
                                    {{ __('erp.no_depreciation_data') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                    <tfoot class="table-light fw-bold align-middle">
                        <tr>
                            <td colspan="4" class="text-end">{{ __('erp.total_overall_colon_caps') }}</td>
                            <td class="text-end">Rp {{ number_format($assets->sum('purchase_price'), 2, ',', '.') }}</td>
                            <td></td>
                            <td class="text-end text-danger">Rp {{ number_format($assets->sum('depreciation_per_month'), 2, ',', '.') }}</td>
                            <td class="text-end text-warning">Rp {{ number_format($assets->sum('accumulated'), 2, ',', '.') }}</td>
                            <td class="text-end text-success fs-6">Rp {{ number_format($assets->sum('book_value'), 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
