@extends('layouts.app')

@php
    use App\Modules\CustomsReports\Models\ReportPeriod;
    $sc = match($period->status) {
        'DRAFT'=>'bg-secondary','FINAL'=>'bg-warning text-dark',
        'DIUNGGAH'=>'bg-success',default=>'bg-secondary'
    };
@endphp

@section('top_bar_left')
<x-breadcrumb :links="['ERP'=>'#','Laporan CEISA'=>route('customs-reports.index'),$period->label()=>null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ $period->label() }}</h3>
            <p class="text-muted small mb-0">
                Periode: {{ sprintf('%02d', $period->periode_bulan) }}/{{ $period->periode_tahun }}
                &middot; Status: <span class="badge {{ $sc }}">{{ $period->status }}</span>
            </p>
        </div>
        <a href="{{ route('customs-reports.index') }}" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Kembali
        </a>
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

    @include('customs-reports.partials.show-actions', ['period' => $period, 'lines' => $lines, 'sc' => $sc])

    @if(! config('customs.enabled'))
        <div class="alert alert-info">{{ __('customs_settings.scope') }}</div>
    @endif

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold">
            <i class="fa-solid fa-list me-1"></i> Data Line Items ({{ $lines->count() }} baris)
        </div>
        <div class="table-responsive">
            @include('customs-reports.partials.table-reference', ['lines' => $lines, 'period' => $period])
        </div>
    </div>

    @if($period->report_type === ReportPeriod::TYPE_MUTASI_REJECT)
        <div class="card shadow-sm border-0 mt-3">
            <div class="card-header bg-warning-subtle fw-bold">Rincian sumber reject dan sisa produksi</div>
            <div class="card-body">
                <p class="text-muted small">Laporan diisi otomatis per kejadian. Rincian berikut untuk penelusuran sumber; satuan berbeda tidak dijumlahkan lintas tahap.</p>
                @foreach(['grey_fabric' => 'Penerimaan Kain Grey', 'fabric' => 'Penerimaan Kain Jadi', 'cutting' => 'Pemeriksaan Cutting', 'finishing' => 'Tahap Finishing'] as $key => $title)
                    <h6 class="mt-3">{{ $title }}</h6>
                    <div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead><tr>@foreach(($rejectAssistData[$key]->first() ? array_keys((array) $rejectAssistData[$key]->first()) : []) as $column)<th>{{ str_replace('_', ' ', $column) }}</th>@endforeach</tr></thead><tbody>
                    @forelse($rejectAssistData[$key] as $row)<tr>@foreach((array) $row as $value)<td>{{ $value }}</td>@endforeach</tr>@empty<tr><td class="text-muted">Tidak ada data reject/wastage.</td></tr>@endforelse
                    </tbody></table></div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection