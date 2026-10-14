{{-- FIX: multicurrency — master Show/Active terpisah; Base tidak disimpan ulang. --}}
@extends('layouts.app')
@section('title', __('currency.title'))
@section('content')
<div class="container-fluid py-3">
    <h1 class="h4">{{ __('currency.title') }}</h1>
    <a href="{{ route('currency.rates.index') }}" class="btn btn-outline-primary mb-3">{{ __('currency.rates') }}</a>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif
    <div class="table-responsive"><table class="table table-striped align-middle">
        <thead><tr>@foreach(['code','name','symbol','decimals','base','show','active','save'] as $key)<th>{{ __('currency.'.$key) }}</th>@endforeach</tr></thead>
        <tbody>@foreach($currencies as $currency)
        <tr>
            <td>{{ $currency->code }}</td><td>{{ $currency->name }}</td><td>{{ $currency->symbol }}</td>
            <td>{{ $currency->decimal_places ?? '-' }}</td><td>{{ $currency->code === $base ? __('currency.base') : '-' }}</td>
            <td><input form="master-{{ $currency->code }}" type="hidden" name="show" value="0"><input form="master-{{ $currency->code }}" aria-label="{{ __('currency.show') }}" type="checkbox" name="show" value="1" @checked($currency->show)></td>
            <td><input form="master-{{ $currency->code }}" type="hidden" name="active" value="0"><input form="master-{{ $currency->code }}" aria-label="{{ __('currency.active') }}" type="checkbox" name="active" value="1" @checked($currency->active) @disabled($currency->decimal_places === null)></td>
            <td><form id="master-{{ $currency->code }}" method="POST" action="{{ route('currency.update', $currency->code) }}">@csrf @method('PUT')<button class="btn btn-sm btn-primary">{{ __('currency.save') }}</button></form></td>
        </tr>@endforeach</tbody>
    </table></div>
    {{ $currencies->links() }}
</div>
@endsection