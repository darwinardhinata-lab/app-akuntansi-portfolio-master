@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_cust') => route('customs.index'), __('customs.dashboard.title') => route('customs.index'), $document->internal_number => null]" />
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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
                <div>
                    <h4 class="fw-bold mb-1 text-dark">{{ $document->internal_number }}</h4>
                    <span class="badge {{ $document->document_type === 'PIB' ? 'bg-info text-dark' : 'bg-success' }} me-2">
                        {{ $document->document_type }}
                    </span>
                    @php
                        $statusClass = match($document->status) {
                            'DRAFT' => 'bg-secondary',
                            'QUEUED' => 'bg-warning text-dark',
                            'SUBMITTED', 'UNDER_REVIEW' => 'bg-info text-dark',
                            'NEED_CORRECTION' => 'bg-warning text-dark',
                            'SPPB_ISSUED', 'NPE_ISSUED' => 'bg-success',
                            'REJECTED' => 'bg-danger',
                            'VOIDED' => 'bg-dark',
                            default => 'bg-secondary',
                        };
                        $statusKey = strtolower($document->status);
                        $statusLabel = __('customs.status.' . $statusKey);
                        if ($statusLabel === 'customs.status.' . $statusKey) {
                            $statusLabel = $document->status;
                        }
                    @endphp
                    <span class="badge {{ $statusClass }}">{{ $statusLabel }}</span>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a href="{{ route('customs.index') }}" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
                    </a>
                    @if($document->status === 'DRAFT')
                        <a href="{{ route('customs.edit', $document->id) }}" class="btn btn-outline-primary">
                            <i class="fa-solid fa-pen me-1"></i> {{ __('customs.dashboard.edit') }}
                        </a>
                    @endif
                    @if(in_array($document->status, ['DRAFT', 'NEED_CORRECTION']))
                        <form action="{{ route('customs.submit', $document->id) }}" method="POST"
                              onsubmit="return confirm('{{ __('customs.dashboard.confirm_submit') }}')">
                            @csrf
                            <button type="submit" class="btn btn-primary">
                                <i class="fa-solid fa-paper-plane me-1"></i> {{ __('customs.dashboard.submit') }}
                            </button>
                        </form>
                    @endif
                    @if(in_array($document->status, ['NEED_CORRECTION', 'REJECTED']))
                        <form action="{{ route('customs.retry', $document->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="btn btn-warning">
                                <i class="fa-solid fa-rotate-right me-1"></i> {{ __('customs.dashboard.retry') }}
                            </button>
                        </form>
                    @endif
                    @if(in_array($document->status, ['DRAFT', 'NEED_CORRECTION', 'REJECTED']))
                        <form action="{{ route('customs.void', $document->id) }}" method="POST"
                              onsubmit="return confirm('{{ __('customs.dashboard.confirm_void') }}')">
                            @csrf
                            <button type="submit" class="btn btn-outline-danger">
                                <i class="fa-solid fa-ban me-1"></i> {{ __('customs.dashboard.void') }}
                            </button>
                        </form>
                    @endif
                    <a href="{{ route('customs.print', $document->id) }}" target="_blank" class="btn btn-outline-secondary">
                        <i class="fa-solid fa-print me-1"></i> Cetak
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if($document->last_error_message)
        <div class="alert alert-danger shadow-sm">
            <strong><i class="fa-solid fa-triangle-exclamation me-1"></i> Error terakhir:</strong>
            {{ $document->last_error_message }}
            @if($document->last_error_code)
                <span class="badge bg-dark ms-2">{{ $document->last_error_code }}</span>
            @endif
        </div>
    @endif

    {{-- PART2 --}}
    <div class="row g-3">
        <div class="col-lg-7">
            {{-- Header Dokumen --}}
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-file-lines me-1 text-info"></i> Header Dokumen</div>
                <div class="card-body py-2">
                    <table class="table table-sm mb-0" style="font-size: 13px;">
                        <tr><td class="text-muted" style="width: 40%;">{{ __('customs.field.internal_number') }}</td><td class="fw-bold">{{ $document->internal_number }}</td></tr>
                        <tr><td class="text-muted">{{ __('customs.field.document_type') }}</td><td>{{ $document->document_type }}</td></tr>
                        <tr><td class="text-muted">Sumber</td>
                            <td>{{ $document->source_type ?? '-' }} #{{ $document->source_id ?? '-' }}</td></tr>
                        <tr><td class="text-muted">{{ __('customs.field.nomor_aju') }}</td><td>{{ $document->nomor_aju ?? '-' }}</td></tr>
                        <tr><td class="text-muted">{{ __('customs.field.nomor_pendaftaran') }}</td><td>{{ $document->nomor_pendaftaran ?? '-' }}</td></tr>
                        <tr><td class="text-muted">{{ __('customs.field.kode_kantor') }}</td><td>{{ $document->kode_kantor ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Mata Uang</td><td>{{ $document->currency ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Kurs</td><td>{{ $document->exchange_rate ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Total Nilai</td><td>{{ $document->total_value ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Environment</td><td>{{ $document->environment }}</td></tr>
                        <tr><td class="text-muted">Dikirim</td><td>{{ $document->submitted_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Respons Terakhir</td><td>{{ $document->responded_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                        <tr><td class="text-muted">Jumlah Retry</td><td>{{ $document->retry_count ?? 0 }}</td></tr>
                    </table>
                </div>
            </div>

            {{-- PART3 --}}
            {{-- Detail Barang (Line Items) --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-boxes-stacked me-1 text-info"></i> Detail Barang</div>
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
                                    <th class="text-end">BM %</th>
                                    <th class="text-end">PPN %</th>
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
                                        <td class="text-end">{{ $detail->tarif_bm }}</td>
                                        <td class="text-end">{{ $detail->tarif_ppn }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="8" class="text-center text-muted py-4">Belum ada detail barang.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- PART4 --}}
        <div class="col-lg-5">
            {{-- Timeline Status --}}
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-timeline me-1 text-info"></i> Riwayat Status</div>
                <div class="card-body py-2">
                    @forelse($document->statusHistory->sortByDesc('changed_at') as $history)
                        <div class="d-flex border-bottom pb-2 mb-2">
                            <div class="flex-shrink-0 me-2">
                                <span class="badge bg-info text-dark">{{ $history->status }}</span>
                            </div>
                            <div>
                                <div style="font-size: 13px;">{{ $history->note }}</div>
                                <small class="text-muted">{{ $history->changed_at?->format('d/m/Y H:i:s') }}</small>
                            </div>
                        </div>
                    @empty
                        <p class="text-muted mb-0 py-2 text-center">Belum ada riwayat status.</p>
                    @endforelse
                </div>
            </div>

            {{-- Log Komunikasi (ringkas — payload mentah TIDAK ditampilkan di UI,
                 cukup referensi event untuk audit; payload tersimpan di tabel log) --}}
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white fw-bold"><i class="fa-solid fa-server me-1 text-info"></i> Log Komunikasi CEISA</div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped align-middle mb-0" style="font-size: 12px;">
                            <thead class="bg-light">
                                <tr>
                                    <th>Event</th>
                                    <th class="text-center">Arah</th>
                                    <th class="text-center">HTTP</th>
                                    <th>Waktu</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($document->logs->sortByDesc('created_at')->take(20) as $log)
                                    <tr>
                                        <td>{{ $log->event_type }}</td>
                                        <td class="text-center">
                                            <span class="badge {{ $log->direction === 'OUTBOUND' ? 'bg-primary' : 'bg-success' }}">{{ $log->direction }}</span>
                                        </td>
                                        <td class="text-center">{{ $log->http_status ?? '-' }}</td>
                                        <td>{{ $log->created_at?->format('d/m/Y H:i:s') }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted py-4">Belum ada log komunikasi.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
