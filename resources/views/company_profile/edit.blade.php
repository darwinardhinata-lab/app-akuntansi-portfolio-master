@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Pengaturan' => '#', 'Profil Perusahaan' => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4 mb-5" style="max-width: 900px;">
    @if(session('success'))
        <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
        </div>
    @endif

    <div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden;">
        <div class="card-header bg-dark text-white border-bottom p-4 d-flex align-items-center gap-3">
            <div class="bg-primary text-white d-flex align-items-center justify-content-center rounded-3" style="width: 45px; height: 45px; font-size: 1.3rem;">
                <i class="fa-solid fa-building"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-0">Identitas Profil Perusahaan</h5>
                <p class="text-muted small mb-0 text-white-50">Informasi ini otomatis dicetak di kop atas lembar print/export Excel seluruh laporan keuangan.</p>
            </div>
        </div>
        <form action="{{ route('company.update') }}" method="POST" enctype="multipart/form-data" class="card-body p-4 bg-white">
            @csrf @method('PUT')
            
            {{-- Logo Upload Section --}}
            <div class="row mb-4 pb-4 border-bottom border-light">
                <div class="col-12 text-center">
                    @if($profile->logo)
                        <div class="mb-3">
                            <img src="{{ asset('storage/' . $profile->logo) }}" alt="Logo Perusahaan" class="img-fluid rounded-3 shadow-sm border p-2" style="max-height: 120px; max-width: 300px; object-fit: contain; background-color: #f8fafc;">
                        </div>
                    @else
                        <div class="mb-3 d-inline-flex align-items-center justify-content-center bg-light text-muted rounded-3 shadow-sm border" style="width: 120px; height: 120px; font-size: 2.5rem;">
                            <i class="fa-solid fa-image"></i>
                        </div>
                    @endif
                    
                    <div class="mx-auto" style="max-width: 400px;">
                        <label class="form-label fw-bold small text-muted text-start d-block">Upload Logo Baru (Opsional)</label>
                        <input type="file" name="logo" class="form-control" accept=".jpg,.jpeg,.png">
                        <small class="text-muted d-block mt-1 text-start" style="font-size: 0.75rem;"><i class="fa-solid fa-paperclip"></i> Format: JPG, PNG (Maks. 2MB). Rekomendasi rasio Landscape/Kotak transparan.</small>
                    </div>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Nama Perusahaan *</label>
                    <input type="text" name="company_name" class="form-control fw-bold" value="{{ $profile->company_name }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">NPWP</label>
                    <input type="text" name="npwp" class="form-control font-monospace" value="{{ $profile->npwp }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">Website</label>
                    <input type="text" name="website" class="form-control" value="{{ $profile->website }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">No. Telepon / Kontak *</label>
                    <input type="text" name="phone" class="form-control fw-bold" value="{{ $profile->phone }}" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted">Email Perusahaan</label>
                    <input type="email" name="email" class="form-control" value="{{ $profile->email }}">
                </div>

                <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>

                <div class="col-md-12">
                    <label class="form-label fw-bold small text-danger">PIN Karyawan (Form Pengajuan)</label>
                    <input type="text" name="employee_pin" inputmode="numeric" pattern="[0-9]*" class="form-control font-monospace" value="{{ $profile->employee_pin ?? '' }}" placeholder="Masukkan PIN numerik untuk otorisasi karyawan">
                    <small class="text-muted d-block mt-1 text-start" style="font-size: 0.75rem;"><i class="fa-solid fa-triangle-exclamation me-1"></i> Hanya Administrator yang dapat mengubah PIN ini. PIN ini digunakan di form pengajuan karyawan.</small>
                </div>

                <div class="col-12"><hr class="my-2 text-muted opacity-25"></div>

                <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted">Detail Alamat Jalan</label>
                    <textarea name="address" class="form-control" rows="2">{{ $profile->address }}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Provinsi</label>
                    <input type="text" name="province" class="form-control" value="{{ $profile->province }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Kota / Kabupaten</label>
                    <input type="text" name="city" class="form-control" value="{{ $profile->city }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Kode Pos</label>
                    <input type="text" name="postal_code" class="form-control font-monospace" value="{{ $profile->postal_code }}">
                </div>
                <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted">Negara</label>
                    <input type="text" name="country" class="form-control" value="{{ $profile->country }}">
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary fw-bold px-4 py-2" style="border-radius: 10px;"><i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan Profil</button>
            </div>
        </form>
    </div>
</div>
@endsection