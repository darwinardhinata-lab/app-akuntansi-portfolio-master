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

    <div class="card shadow-sm border-0">
        <div class="card-header bg-white fw-bold">
            <i class="fa-solid fa-list me-1"></i> Data Line Items ({{ $lines->count() }} baris)
        </div>
        <div class="table-responsive">
            @if($period->report_type === ReportPeriod::TYPE_WIP)
                @include('customs-reports.partials.table-posisi', ['lines' => $lines])
            @elseif(in_array($period->report_type, [ReportPeriod::TYPE_MUTASI_BAHAN_BAKU, ReportPeriod::TYPE_MUTASI_BARANG_JADI, ReportPeriod::TYPE_MUTASI_BARANG_MODAL, ReportPeriod::TYPE_MUTASI_REJECT]))
                @include('customs-reports.partials.table-mutasi', ['lines' => $lines])
            @else
                @include('customs-reports.partials.table-dokumen', ['lines' => $lines, 'period' => $period])
            @endif
        </div>
    </div>
</div>
@endsection