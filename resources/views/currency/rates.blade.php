{{-- FIX: multicurrency — KMK/BI, rentang kalender, input per unit dan audit aktor tepercaya. --}}
@extends('layouts.app')
@section('title', __('currency.rates'))
@section('content')
<div class="container-fluid py-3">
    <h1 class="h4">{{ __('currency.rates') }}</h1>
    <a href="{{ route('currency.index') }}">{{ __('currency.title') }}</a>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <nav class="nav nav-tabs my-3">@foreach(['KMK','BI','MANUAL'] as $tab)
        <a class="nav-link {{ $source === $tab ? 'active' : '' }}" href="{{ route('currency.rates.index', ['source' => $tab, 'from' => $from, 'to' => $to]) }}">{{ $tab === 'MANUAL' ? __('currency.manual') : $tab }}</a>
    @endforeach</nav>
    @if($source !== 'MANUAL')<div class="alert alert-info">{{ __('currency.'.($source === 'KMK' ? 'kmk_help' : 'bi_help')) }} <a target="_blank" rel="noopener" href="{{ $source === 'KMK' ? 'https://fiskal.kemenkeu.go.id/informasi-publik/kurs-pajak' : 'https://www.bi.go.id/id/statistik/informasi-kurs/transaksi-bi/default.aspx' }}">{{ $source }}</a></div>@endif
    <form class="row g-2 mb-3" method="GET">
        <input type="hidden" name="source" value="{{ $source }}">
        <div class="col-auto"><label>{{ __('currency.from') }}<input class="form-control" type="date" name="from" value="{{ $from }}" required></label></div>
        <div class="col-auto"><label>{{ __('currency.to') }}<input class="form-control" type="date" name="to" value="{{ $to }}" required></label></div>
        <div class="col-auto align-self-end"><button class="btn btn-secondary">{{ __('currency.filter') }}</button></div>
    </form>
    <h2 class="h5">{{ __('currency.add') }}</h2>
    <form class="row g-2 mb-4" method="POST" action="{{ route('currency.rates.store') }}">@csrf
        <input type="hidden" name="source" value="{{ $source }}">
        <div class="col-auto"><label>{{ __('currency.code') }}<select class="form-select" name="currency_code">@foreach($currencies as $currency)<option>{{ $currency->code }}</option>@endforeach</select></label></div>
        <div class="col-auto"><label>{{ __('currency.date') }}<input class="form-control" type="date" name="rate_date" value="{{ old('rate_date', $from) }}" required></label></div>
        <div class="col-auto"><label>{{ __('currency.until') }}<input class="form-control" type="date" name="valid_until" value="{{ old('valid_until') }}" @required($source === 'KMK')></label></div>
        @if($source === 'KMK')
        <div class="col-auto"><label>{{ __('currency.quoted_unit') }}<input class="form-control" type="number" name="quoted_unit" min="1" max="4294967295" value="{{ old('quoted_unit', 1) }}" required></label></div>
        <div class="col-auto"><label>{{ __('currency.quoted_rate') }}<input class="form-control" type="text" inputmode="decimal" name="quoted_rate" value="{{ old('quoted_rate') }}" required></label><small>{{ __('currency.quoted_help') }}</small></div>
        @else
        <div class="col-auto"><label>{{ __('currency.rate') }}<input class="form-control" type="text" inputmode="decimal" name="rate" value="{{ old('rate') }}" required></label></div>
        @endif
        <div class="col-auto"><label>{{ __('currency.type') }}<select class="form-select" name="type">@foreach($source === 'KMK' ? ['TAX'=>'tax'] : ($source === 'BI' ? ['SPOT'=>'spot'] : ['SPOT'=>'spot','TAX'=>'tax','AVERAGE'=>'average']) as $type => $label)<option value="{{ $type }}">{{ __('currency.'.$label) }}</option>@endforeach</select></label></div>
        <div class="col-auto align-self-end"><button class="btn btn-primary">{{ __('currency.save') }}</button></div>
    </form>
    <p>{{ __('currency.csv_help') }}</p>
    <form class="d-flex gap-2 mb-3" method="POST" enctype="multipart/form-data" action="{{ route('currency.rates.import') }}">@csrf
        <input class="form-control" aria-label="{{ __('currency.import') }}" type="file" name="file" accept=".csv,text/csv" required><button class="btn btn-secondary text-nowrap">{{ __('currency.import') }}</button>
    </form>
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr>@foreach(['date','until','code','rate','type','created','updated','already_used','save','delete'] as $key)<th>{{ __('currency.'.$key) }}</th>@endforeach</tr></thead>
        <tbody>@forelse($rates as $rate)
            <tr>
                <td>{{ $rate->rate_date->toDateString() }}</td><td>{{ $rate->valid_until?->toDateString() ?? '-' }}</td><td>{{ $rate->currency_code }}</td>
                <td>
                    @if($source === 'KMK')
                    <label>{{ __('currency.quoted_unit') }}<input class="form-control" type="number" min="1" max="4294967295" form="rate-{{ $rate->id }}" name="quoted_unit" value="{{ $rate->quoted_unit }}" @disabled($rate->isUsed()) required></label>
                    <label>{{ __('currency.quoted_rate') }}<input class="form-control" form="rate-{{ $rate->id }}" name="quoted_rate" value="{{ $rate->quoted_rate }}" inputmode="decimal" @disabled($rate->isUsed())></label>
                    <div>{{ __('currency.rate') }}: {{ $rate->rate }}</div>
                    @endif
                    <input class="form-control" aria-label="{{ __('currency.rate') }}" form="rate-{{ $rate->id }}" name="rate" value="{{ $rate->rate }}" inputmode="decimal" @readonly($source === 'KMK') @disabled($rate->isUsed()) required>
                </td>
                <td>{{ __('currency.'.strtolower($rate->type)) }}</td>
                <td>{{ $rate->creator?->name ?? '-' }}<br>{{ $rate->created_at }}</td><td>{{ $rate->updater?->name ?? '-' }}<br>{{ $rate->updated_at }}</td><td>{{ $rate->isUsed() ? $rate->first_used_at : '-' }}</td>
                <td><form id="rate-{{ $rate->id }}" method="POST" action="{{ route('currency.rates.update', $rate->id) }}">@csrf @method('PUT')
                    @foreach(['currency_code','type','source'] as $field)<input type="hidden" name="{{ $field }}" value="{{ $rate->$field }}">@endforeach
                    <input type="hidden" name="rate_date" value="{{ $rate->rate_date->toDateString() }}"><input type="hidden" name="valid_until" value="{{ $rate->valid_until?->toDateString() }}">
                    <button class="btn btn-sm btn-primary" @disabled($rate->isUsed())>{{ __('currency.save') }}</button>
                </form></td>
                <td><form method="POST" action="{{ route('currency.rates.destroy', $rate->id) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger" @disabled($rate->isUsed())>{{ __('currency.delete') }}</button></form></td>
            </tr>
        @empty<tr><td colspan="10">{{ __('currency.empty') }}</td></tr>@endforelse</tbody>
    </table></div>
    {{ $rates->links() }}
</div>
@endsection