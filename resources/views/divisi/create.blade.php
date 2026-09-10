@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('divisi.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_settings') => '#', __('erp.division_label') => route('divisi.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">

    @if ($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm border-0" role="alert">
            <div class="fw-bold mb-1"><i class="fa-solid fa-circle-exclamation me-1"></i> {{ __('erp.division_save_failed_check') }}</div>
            <ul class="mb-0 small">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plus-circle me-2 text-primary"></i> {{ __('erp.add_new_division') }}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('divisi.store') }}" method="POST">
                @csrf
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label fw-bold">{{ __('erp.division_code') }} <span class="text-danger">*</span></label>
                        <input type="text" name="kode_divisi" value="{{ old('kode_divisi') }}" class="form-control text-uppercase {{ $errors->has('kode_divisi') ? 'is-invalid' : '' }}" placeholder="Contoh: FIN" maxlength="10" required>
                        <div class="form-text">{{ __('erp.max_10_chars_payment_plan') }}</div>
                        @error('kode_divisi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12 col-md-8">
                        <label class="form-label fw-bold">{{ __('erp.division_name') }} <span class="text-danger">*</span></label>
                        <input type="text" name="nama_divisi" value="{{ old('nama_divisi') }}" class="form-control {{ $errors->has('nama_divisi') ? 'is-invalid' : '' }}" placeholder="Contoh: Divisi Keuangan" maxlength="50" required>
                        @error('nama_divisi')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                    <div class="col-12">
                        <div class="form-check form-switch">
                            <input class="form-check-input" type="checkbox" name="status_aktif" value="1" id="statusAktif" {{ old('status_aktif', 1) ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold" for="statusAktif">{{ __('erp.active_status') }}</label>
                            <div class="form-text">{{ __('erp.inactive_division_hint') }}</div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary fw-bold">{{ __('erp.save_btn') }}</button>
                    <a href="{{ route('divisi.index') }}" class="btn btn-secondary fw-bold">{{ __('erp.cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
