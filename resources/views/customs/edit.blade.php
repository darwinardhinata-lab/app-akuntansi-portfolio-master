@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_cust') => route('customs.index'), __('customs.dashboard.title') => route('customs.index'), $document->internal_number => route('customs.show', $document->id), 'Edit' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-circle-check me-1"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">
            <i class="fa-solid fa-triangle-exclamation me-1"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-3">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">Edit {{ $document->internal_number }}</h4>
                    <span class="badge {{ $document->document_type === 'PIB' ? 'bg-info text-dark' : 'bg-success' }} me-2">
                        {{ $document->document_type }}
                    </span>
                    <span class="badge bg-secondary">{{ __('customs.status.' . strtolower($document->status)) }}</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="{{ route('customs.show', $document->id) }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- PART2 --}}
    {{-- Form edit header — hanya field yang diizinkan oleh
         CustomsDocumentService::updateFromPayload(): kode_kantor, currency, exchange_rate --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-header bg-white fw-bold"><i class="fa-solid fa-pen me-1 text-info"></i> Header Dokumen</div>
        <div class="card-body py-3">
            <form action="{{ route('customs.update', $document->id) }}" method="POST" class="row g-3">
                @csrf
                @method('PUT')

                <div class="col-md-4">
                    <label for="kode_kantor" class="form-label small text-muted mb-1">{{ __('customs.field.kode_kantor') }}</label>
                    <input type="text" name="kode_kantor" id="kode_kantor" value="{{ old('kode_kantor', $document->kode_kantor) }}"
                           class="form-control @error('kode_kantor') is-invalid @enderror" placeholder="mis. BE001">
                    @error('kode_kantor')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="currency" class="form-label small text-muted mb-1">Mata Uang</label>
                    <input type="text" name="currency" id="currency" value="{{ old('currency', $document->currency) }}"
                           class="form-control @error('currency') is-invalid @enderror" maxlength="3" placeholder="mis. USD">
                    @error('currency')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-md-4">
                    <label for="exchange_rate" class="form-label small text-muted mb-1">Kurs</label>
                    <input type="number" step="0.000001" min="0.000001" name="exchange_rate" id="exchange_rate"
                           value="{{ old('exchange_rate', $document->exchange_rate) }}"
                           class="form-control @error('exchange_rate') is-invalid @enderror" placeholder="mis. 15500">
                    @error('exchange_rate')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Simpan Perubahan
                    </button>
                    <span class="text-muted small ms-2">Hanya dokumen berstatus DRAFT yang dapat diubah.</span>
                </div>
            </form>
        </div>
    </div>

    {{-- Detail barang: read-only untuk konteks.
         Manajemen line-item penuh (tambah/ubah/hapus detail) menyusul — belum di scope Fase 1-B. --}}
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold"><i class="fa-solid fa-boxes-stacked me-1 text-info"></i> Detail Barang (read-only)</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-striped align-middle mb-0" style="font-size: 13px;">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center">No</th>
                            <th>Deskripsi Barang</th>
                            <th>HS Code</th>
                            <th class="text-end">Qty</th>
                            <th class="text-center">Satuan</th>
                            <th class="text-end">Nilai</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($document->details as $detail)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $detail->deskripsi_barang ?? '-' }}</td>
                                <td>{{ $detail->hs_code ?? '-' }}</td>
                                <td class="text-end">{{ $detail->qty }}</td>
                                <td class="text-center">{{ $detail->satuan ?? '-' }}</td>
                                <td class="text-end">{{ number_format((float) $detail->nilai, 2) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted py-4">Belum ada detail barang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
