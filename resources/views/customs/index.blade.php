@extends('layouts.app')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>{{ __('customs.title_dashboard') }}</h2>
        <div>
            <a href="{{ route('customs.export') }}" class="btn btn-outline-primary">{{ __('customs.export') }}</a>
        </div>
    </div>

    <form method="GET" class="row g-3 mb-4">
        <div class="col-md-3">
            <select name="document_type" class="form-control">
                <option value="">{{ __('customs.all_types') }}</option>
                <option value="PIB" {{ ($filters['document_type'] ?? '') == 'PIB' ? 'selected' : '' }}>PIB (Impor)</option>
                <option value="PEB" {{ ($filters['document_type'] ?? '') == 'PEB' ? 'selected' : '' }}>PEB (Ekspor)</option>
            </select>
        </div>
        <div class="col-md-3">
            <select name="status" class="form-control">
                <option value="">{{ __('customs.all_status') }}</option>
                <option value="DRAFT" {{ ($filters['status'] ?? '') == 'DRAFT' ? 'selected' : '' }}>Draft</option>
                <option value="SUBMITTED" {{ ($filters['status'] ?? '') == 'SUBMITTED' ? 'selected' : '' }}>Dikirim</option>
                <option value="SPPB_ISSUED" {{ ($filters['status'] ?? '') == 'SPPB_ISSUED' ? 'selected' : '' }}>SPPB Terbit</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary">{{ __('customs.filter') }}</button>
        </div>
    </form>

    @if($documents->count() > 0)
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>{{ __('customs.internal_number') }}</th>
                        <th>{{ __('customs.doc_type') }}</th>
                        <th>{{ __('customs.status') }}</th>
                        <th>{{ __('customs.created_at') }}</th>
                        <th>{{ __('customs.actions') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($documents as $doc)
                    <tr>
                        <td>
                            <strong>{{ $doc->internal_number }}</strong>
                            <br><small class="text-muted">{{ $doc->source_type }} #{{ $doc->source_id }}</small>
                        </td>
                        <td>
                            @if($doc->document_type === 'PIB')
                                <span class="badge bg-info">PIB</span>
                            @else
                                <span class="badge bg-success">PEB</span>
                            @endif
                        </td>
                        <td>
                            @php
                                $statusClass = match($doc->status) {
                                    'DRAFT' => 'bg-secondary',
                                    'SUBMITTED', 'UNDER_REVIEW' => 'bg-info',
                                    'SPPB_ISSUED', 'NPE_ISSUED' => 'bg-success',
                                    'REJECTED' => 'bg-danger',
                                    'VOIDED' => 'bg-dark',
                                    default => 'bg-secondary',
                                };
                            @endphp
                            <span class="badge {{ $statusClass }}">{{ $doc->status }}</span>
                        </td>
                        <td>{{ $doc->created_at->format('d/m/Y H:i') }}</td>
                        <td>
                            <a href="{{ route('customs.show', $doc) }}" class="btn btn-sm btn-outline-secondary">View</a>
                            @if($doc->status === 'DRAFT')
                                <a href="{{ route('customs.edit', $doc) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="d-flex justify-content-center mt-3">
            {{ $documents->links() }}
        </div>
    @else
        <div class="text-center py-5">
            <h4 class="text-muted">{{ __('customs.no_documents') }}</h4>
        </div>
    @endif
</div>
@endsection