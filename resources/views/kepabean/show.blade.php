@extends('layouts.app')
@section('top_bar_left')
<x-breadcrumb :links="[__('kepabean.documents') => route('kepabean.documents'), $document->internal_number => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between mb-3"><h3>{{ $document->document_type }} — {{ $document->internal_number }}</h3><div><a class="btn btn-outline-secondary" href="{{ route('kepabean.documents') }}">{{ __('kepabean.back') }}</a> @if($document->status === 'DRAFT')<a class="btn btn-primary" href="{{ route('kepabean.edit', $document) }}">{{ __('kepabean.edit') }}</a>@endif</div></div>
    @include('kepabean.alerts')
    <div class="card mb-3"><div class="card-body row g-3">
        @foreach(['nomor_aju' => 'aju', 'nomor_pendaftaran' => 'registration', 'status' => 'status', 'kode_kantor' => 'office', 'currency' => 'currency', 'exchange_rate' => 'rate'] as $field => $label)
        <div class="col-md-4"><small class="text-muted">{{ __('kepabean.'.$label) }}</small><div>{{ $document->$field ?: '—' }}</div></div>@endforeach
        <div class="col-md-4"><small>{{ __('kepabean.value') }}</small><div>{{ number_format((float) $document->total_value, 2, ',', '.') }}</div></div>
    </div></div>
    <p class="text-muted small">{{ __('kepabean.registration_notice') }}</p>
    <div class="card mb-3"><div class="table-responsive"><table class="table mb-0"><thead><tr><th>HS Code</th>@foreach(['description','qty','unit','net','value'] as $key)<th>{{ __('kepabean.'.$key) }}</th>@endforeach</tr></thead><tbody>
        @foreach($document->details as $line)<tr><td>{{ $line->hs_code }}</td><td>{{ $line->deskripsi_barang }}</td><td>{{ number_format((float) $line->qty, 4, ',', '.') }}</td><td>{{ $line->satuan }}</td><td>{{ $line->berat_bersih === null ? '—' : number_format((float) $line->berat_bersih, 4, ',', '.') }}</td><td>{{ number_format((float) $line->nilai, 2, ',', '.') }}</td></tr>@endforeach
    </tbody></table></div></div>
    <div class="card"><div class="card-body"><h5>{{ __('kepabean.history') }}</h5>@foreach($document->statusHistory->sortByDesc('changed_at') as $history)<div class="border-top py-2">{{ $history->changed_at->format('d-m-Y H:i:s') }} · {{ $history->status }} · {{ $history->note }}</div>@endforeach</div></div>
</div>
@endsection