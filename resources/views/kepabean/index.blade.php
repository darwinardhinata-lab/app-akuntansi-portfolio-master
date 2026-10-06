@extends('layouts.app')
@section('top_bar_left')
<x-breadcrumb :links="[__('kepabean.dashboard') => route('kepabean.dashboard'), __('kepabean.documents') => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between gap-2 mb-3"><h3>{{ __('kepabean.documents') }}</h3><div><a href="{{ request()->fullUrl() }}" class="btn btn-outline-secondary">{{ __('kepabean.refresh') }}</a> <a class="btn btn-primary" href="{{ route('kepabean.create') }}">+ {{ __('kepabean.new') }}</a></div></div>
    @include('kepabean.alerts')
    <p class="text-muted small">{{ __('kepabean.local_notice') }}</p>
    <form method="GET" class="row g-2 mb-3">
        <div class="col-md-5"><input name="q" value="{{ $filters['q'] ?? '' }}" class="form-control" placeholder="{{ __('kepabean.search') }}"></div>
        <div class="col-md-3"><select name="document_type" class="form-select"><option value="">{{ __('kepabean.all') }} — {{ __('kepabean.type') }}</option>@foreach($types as $type)<option @selected(($filters['document_type'] ?? '') === $type)>{{ $type }}</option>@endforeach</select></div>
        <div class="col-md-3"><select name="status" class="form-select"><option value="">{{ __('kepabean.all') }} — {{ __('kepabean.status') }}</option>@foreach(['DRAFT','QUEUED','SUBMITTED','UNDER_REVIEW','NEED_CORRECTION','REJECTED','SPPB_ISSUED','NPE_ISSUED','VOIDED'] as $status)<option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ __('customs.status.'.strtolower($status)) }}</option>@endforeach</select></div>
        <div class="col-md-1"><button class="btn btn-primary">{{ __('kepabean.filter') }}</button></div>
    </form>
    <div class="card"><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>#</th>@foreach(['type','aju','registration','status','created'] as $key)<th>{{ __('kepabean.'.$key) }}</th>@endforeach<th></th></tr></thead><tbody>
        @forelse($documents as $document)
        <tr><td>{{ $documents->firstItem() + $loop->index }}</td><td>{{ $document->document_type }}</td><td><span class="font-monospace">{{ $document->nomor_aju ?: '—' }}</span><small class="d-block text-muted">{{ $document->internal_number }}</small></td><td>{{ $document->nomor_pendaftaran ?: '—' }}</td><td><span class="badge {{ $document->status === 'DRAFT' ? 'bg-warning text-dark' : 'bg-secondary' }}">{{ __('customs.status.'.strtolower($document->status)) }}</span></td><td class="text-nowrap">{{ $document->created_at->format('d-m-Y H:i:s') }}</td><td class="text-nowrap">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('kepabean.show', $document) }}">{{ __('kepabean.preview') }}</a>
            @if($document->status === 'DRAFT')<a class="btn btn-sm btn-outline-secondary" href="{{ route('kepabean.edit', $document) }}">{{ __('kepabean.edit') }}</a>
            <form class="d-inline" method="POST" action="{{ route('kepabean.destroy', $document) }}" onsubmit="return confirm(@js(__('kepabean.confirm_delete')))">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">{{ __('kepabean.delete') }}</button></form>@endif
        </td></tr>
        @empty<tr><td colspan="7" class="text-center py-5">{{ __('kepabean.empty') }}</td></tr>@endforelse
    </tbody></table></div></div>
    <p class="text-muted small mt-2">{{ __('kepabean.registration_notice') }}</p>
    <div class="d-flex justify-content-between mt-3"><span>{{ $documents->total() }} records</span>{{ $documents->links() }}</div>
</div>
@endsection