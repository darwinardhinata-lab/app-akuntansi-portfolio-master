@extends('layouts.app')
@section('top_bar_left')
<x-breadcrumb :links="[__('erp.customs_module') => '#', __('kepabean.dashboard') => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div><h3>{{ __('kepabean.dashboard') }}</h3><p class="text-muted mb-0">{{ $month->translatedFormat('F Y') }}</p></div>
        <form class="d-flex gap-2" method="GET"><input class="form-control" type="month" name="month" value="{{ $month->format('Y-m') }}" aria-label="{{ __('kepabean.month') }}"><button class="btn btn-primary">{{ __('kepabean.filter') }}</button></form>
        <a href="{{ route('kepabean.documents') }}" class="btn btn-primary">{{ __('kepabean.documents') }}</a>
    </div>
    <div class="row g-3 mb-3">
        @foreach(['monthly' => $count, 'submitted' => $submitted, 'drafts' => $drafts, 'total' => $total] as $key => $value)
        <div class="col-md-3"><div class="card h-100"><div class="card-body"><div class="text-muted small">{{ __('kepabean.'.$key) }}</div><h3 class="mt-2">{{ number_format($value) }}</h3>
            @if($key === 'monthly')<small>{{ $change === null ? __('kepabean.no_baseline') : sprintf('%+.1f%%', $change).' vs '.__('kepabean.previous') }}</small>@endif
        </div></div></div>
        @endforeach
    </div>
    <div class="row g-3">
        <div class="col-lg-8"><div class="card h-100"><div class="card-body"><h5>{{ __('kepabean.trend') }}</h5>
            @php
                $max = max(1, max($trend));
                $points = [];
                foreach(array_values($trend) as $i => $value) { $points[] = (30 + $i * 930 / max(1, count($trend)-1)).','. (190 - $value * 160 / $max); }
            @endphp
            <svg viewBox="0 0 1000 230" role="img" aria-label="{{ __('kepabean.trend') }}" class="w-100">
                <line x1="30" y1="190" x2="960" y2="190" stroke="currentColor" opacity=".3" />
                <text x="5" y="32" fill="currentColor" font-size="14">{{ $max }}</text><text x="5" y="194" fill="currentColor" font-size="14">0</text>
                <polyline points="{{ implode(' ', $points) }}" fill="none" stroke="#6875f5" stroke-width="3" />
                @foreach($trend as $date => $value)
                <circle cx="{{ 30 + $loop->index * 930 / max(1, count($trend)-1) }}" cy="{{ 190 - $value * 160 / $max }}" r="3" fill="#6875f5"><title>{{ $date }}: {{ $value }}</title></circle>
                @endforeach
                <text x="30" y="220" fill="currentColor" font-size="14">{{ $month->format('d/m') }}</text><text x="930" y="220" fill="currentColor" font-size="14">{{ $month->copy()->endOfMonth()->format('d/m') }}</text>
            </svg>
        </div></div></div>
        <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h5>{{ __('kepabean.distribution') }}</h5>
            @forelse($types as $type => $value)
            <div class="d-flex justify-content-between mt-3"><span>{{ $type }}</span><span>{{ $value }} ({{ $count ? round($value/$count*100, 1) : 0 }}%)</span></div>
            <div class="progress mt-1" style="height:8px"><div class="progress-bar" style="width:{{ $count ? $value/$count*100 : 0 }}%"></div></div>
            @empty<p class="text-muted mt-4">{{ __('kepabean.empty') }}</p>@endforelse
        </div></div></div>
        <div class="col-lg-8"><div class="card h-100"><div class="card-body"><h5>{{ __('kepabean.by_type') }}</h5><div class="row">
            @foreach(array_unique(array_merge(\App\Modules\Customs\Services\KepabeanService::TYPES, $types->keys()->all())) as $type)
            <div class="col-md-6 mt-3"><div class="d-flex justify-content-between"><a href="{{ route('kepabean.documents', ['document_type' => $type]) }}">{{ $type }}</a><span>{{ $types[$type] ?? 0 }}</span></div>
                <div class="progress mt-1" style="height:6px"><div class="progress-bar" style="width:{{ $count ? ($types[$type] ?? 0)/$count*100 : 0 }}%"></div></div>
            </div>@endforeach
        </div></div></div></div>
        <div class="col-lg-4"><div class="card h-100"><div class="card-body"><h5>{{ __('kepabean.latest') }}</h5>
            @forelse($latest as $document)
            <div class="border-top mt-3 pt-2"><a href="{{ route('kepabean.show', $document) }}">{{ $document->document_type }} · {{ $document->nomor_aju ?: $document->internal_number }}</a><div class="d-flex justify-content-between small mt-1"><span>{{ __('customs.status.'.strtolower($document->status)) }}</span><span>{{ $document->created_at->format('d-m-Y H:i') }}</span></div></div>
            @empty<p class="text-muted">{{ __('kepabean.empty') }}</p>@endforelse
        </div></div></div>
    </div>
</div>
@endsection