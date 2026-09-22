@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[
        __('erp.bc_cust') => '#',
        'Laporan CEISA' => route('customs-reports.index'),
        $period->exists ? 'Edit' : 'Buat Baru' => null,
    ]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3 class="fw-bold mb-1 text-dark">
            {{ $period->exists ? 'Edit' : 'Buat' }} Periode Laporan CEISA
        </h3>
        <a href="{{ route('customs-reports.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
        </a>
    </div>

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold">
            <i class="fa-solid fa-file-invoice text-info me-1"></i>
            {{ $period->exists ? 'Edit' : 'Buat' }} Periode Laporan
        </div>
        <div class="card-body">
            <form action="{{ $period->exists ? route('customs-reports.update', $period) : route('customs-reports.store') }}" method="POST" class="row g-3">
                @csrf
                @if($period->exists) @method('PUT') @endif

                @if(!$period->exists)
                    <div class="col-md-6">
                        <label for="report_type" class="form-label small text-muted mb-1">Jenis Laporan</label>
                        <select name="report_type" id="report_type" class="form-select @error('report_type') is-invalid @enderror" required>
                            <option value="">-- Pilih Jenis Laporan --</option>
                            @foreach($allTypes as $type)
                                <option value="{{ $type }}" {{ old('report_type', $period->report_type ?? '') == $type ? 'selected' : '' }}>
                                    {{ $typeLabels[$type] }}
                                </option>
                            @endforeach
                        </select>
                        @error('report_type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="periode_bulan" class="form-label small text-muted mb-1">Bulan</label>
                        <select name="periode_bulan" id="periode_bulan" class="form-select @error('periode_bulan') is-invalid @enderror" required>
                            @for($m = 1; $m <= 12; $m++)
                                <option value="{{ $m }}" {{ old('periode_bulan', $period->periode_bulan ?? '') == $m ? 'selected' : '' }}>
                                    {{ sprintf('%02d', $m) }}
                                </option>
                            @endfor
                        </select>
                        @error('periode_bulan')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="col-md-3">
                        <label for="periode_tahun" class="form-label small text-muted mb-1">Tahun</label>
                        <input type="number" name="periode_tahun" id="periode_tahun"
                               value="{{ old('periode_tahun', $period->periode_tahun ?? date('Y')) }}"
                               class="form-control @error('periode_tahun') is-invalid @enderror" min="1900" max="2100" required>
                        @error('periode_tahun')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                @else
                    <input type="hidden" name="report_type" value="{{ $period->report_type }}">
                    <input type="hidden" name="periode_bulan" value="{{ $period->periode_bulan }}">
                    <input type="hidden" name="periode_tahun" value="{{ $period->periode_tahun }}">
                @endif

                <div class="col-12">
                    <label for="catatan" class="form-label small text-muted mb-1">Catatan</label>
                    <textarea name="catatan" id="catatan" rows="3" class="form-control @error('catatan') is-invalid @enderror"
                              placeholder="Opsional — catatan tambahan untuk periode ini.">{{ old('catatan', $period->catatan ?? '') }}</textarea>
                    @error('catatan')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> {{ $period->exists ? 'Simpan' : 'Buat' }}
                    </button>
                    <a href="{{ route('customs-reports.index') }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-x me-1"></i> Batal
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection