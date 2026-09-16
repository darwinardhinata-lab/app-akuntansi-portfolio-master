@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_cust') => '#', __('customs.dashboard.title') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('customs.dashboard.title') }}</h3>
            <p class="text-muted small mb-0">{{ __('customs.module_title') }} — Host-to-Host CEISA 4.0</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('customs.export') }}" class="btn btn-outline-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> {{ __('customs.dashboard.export') }}
            </a>
        </div>
    </div>

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
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <select name="document_type" class="form-select form-select-sm">
                        <option value="">{{ __('customs.dashboard.all_types') }}</option>
                        <option value="PIB" {{ ($filters['document_type'] ?? '') == 'PIB' ? 'selected' : '' }}>PIB (Impor)</option>
                        <option value="PEB" {{ ($filters['document_type'] ?? '') == 'PEB' ? 'selected' : '' }}>PEB (Ekspor)</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select form-select-sm">
                        <option value="">{{ __('customs.dashboard.all_status') }}</option>
                        <option value="DRAFT" {{ ($filters['status'] ?? '') == 'DRAFT' ? 'selected' : '' }}>{{ __('customs.status.draft') }}</option>
                        <option value="QUEUED" {{ ($filters['status'] ?? '') == 'QUEUED' ? 'selected' : '' }}>{{ __('customs.status.queued') }}</option>
                        <option value="SUBMITTED" {{ ($filters['status'] ?? '') == 'SUBMITTED' ? 'selected' : '' }}>{{ __('customs.status.submitted') }}</option>
                        <option value="UNDER_REVIEW" {{ ($filters['status'] ?? '') == 'UNDER_REVIEW' ? 'selected' : '' }}>{{ __('customs.status.under_review') }}</option>
                        <option value="NEED_CORRECTION" {{ ($filters['status'] ?? '') == 'NEED_CORRECTION' ? 'selected' : '' }}>{{ __('customs.status.need_correction') }}</option>
                        <option value="SPPB_ISSUED" {{ ($filters['status'] ?? '') == 'SPPB_ISSUED' ? 'selected' : '' }}>{{ __('customs.status.sppb_issued') }}</option>
                        <option value="NPE_ISSUED" {{ ($filters['status'] ?? '') == 'NPE_ISSUED' ? 'selected' : '' }}>{{ __('customs.status.npe_issued') }}</option>
                        <option value="REJECTED" {{ ($filters['status'] ?? '') == 'REJECTED' ? 'selected' : '' }}>{{ __('customs.status.rejected') }}</option>
                        <option value="VOIDED" {{ ($filters['status'] ?? '') == 'VOIDED' ? 'selected' : '' }}>{{ __('customs.status.voided') }}</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-outline-primary w-100">
                        <i class="fa-solid fa-filter me-1"></i> {{ __('customs.dashboard.filter') }}
                    </button>
                </div>
                @if(!empty($filters['document_type']) || !empty($filters['status']))
                <div class="col-md-2">
                    <a href="{{ route('customs.index') }}" class="btn btn-sm btn-outline-secondary w-100">
                        <i class="fa-solid fa-rotate-left me-1"></i> Reset
                    </a>
                </div>
                @endif
            </form>
        </div>
    </div>

    @if($documents->count() > 0)
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                        <thead class="bg-primary text-white text-center align-middle">
                            <tr>
                                <th>{{ __('customs.field.internal_number') }}</th>
                                <th>{{ __('customs.field.document_type') }}</th>
                                <th>{{ __('customs.field.nomor_aju') }}</th>
                                <th>Status</th>
                                <th>{{ __('erp.date') }}</th>
                                <th>{{ __('customs.dashboard.actions') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($documents as $doc)
                            <tr>
                                <td class="py-2">
                                    <strong>{{ $doc->internal_number }}</strong>
                                    <br><small class="text-muted">{{ strtoupper($doc->source_type ?? '-') }} #{{ $doc->source_id ?? '-' }}</small>
                                </td>
                                <td class="text-center py-2">
                                    @if($doc->document_type === 'PIB')
                                        <span class="badge bg-info text-dark">PIB</span>
                                    @else
                                        <span class="badge bg-success">PEB</span>
                                    @endif
                                </td>
                                <td class="py-2 text-center">
                                    {{ $doc->nomor_aju ?? '-' }}
                                    @if($doc->nomor_pendaftaran)
                                        <br><small class="text-muted">Reg: {{ $doc->nomor_pendaftaran }}</small>
                                    @endif
                                </td>
                                <td class="text-center py-2">
                                    @php
                                        $statusClass = match($doc->status) {
                                            'DRAFT' => 'bg-secondary',
                                            'QUEUED' => 'bg-warning text-dark',
                                            'SUBMITTED', 'UNDER_REVIEW' => 'bg-info text-dark',
                                            'NEED_CORRECTION' => 'bg-warning text-dark',
                                            'SPPB_ISSUED', 'NPE_ISSUED' => 'bg-success',
                                            'REJECTED' => 'bg-danger',
                                            'VOIDED' => 'bg-dark',
                                            default => 'bg-secondary',
                                        };
                                        $statusKey = strtolower($doc->status);
                                        $statusLabel = __('customs.status.' . $statusKey);
                                        if ($statusLabel === 'customs.status.' . $statusKey) {
                                            $statusLabel = $doc->status;
                                        }
                                    @endphp
                                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                                </td>
                                <td class="text-center py-2">{{ $doc->created_at ? $doc->created_at->format('d/m/Y H:i') : '-' }}</td>
                                <td class="text-center py-2">
                                    <a href="{{ route('customs.show', $doc->id) }}" class="btn btn-sm btn-outline-primary">
                                        <i class="fa-solid fa-eye me-1"></i> {{ __('customs.dashboard.view') }}
                                    </a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="d-flex justify-content-center mt-3">
            {{ $documents->links() }}
        </div>
    @else
        <div class="card shadow-sm border-0 py-5 text-center">
            <div class="card-body">
                <i class="fa-solid fa-file-signature text-muted fa-3x mb-3"></i>
                <h5 class="text-muted fw-bold">{{ __('customs.dashboard.no_documents') }}</h5>
                <p class="text-muted small mb-0">Dokumen PIB/PEB dapat dibuat langsung dari halaman detail Purchase Order atau Sales Order.</p>
            </div>
        </div>
    @endif
</div>
@endsection