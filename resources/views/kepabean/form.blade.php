@extends('layouts.app')
@section('top_bar_left')
<x-breadcrumb :links="[__('kepabean.documents') => route('kepabean.documents'), __('kepabean.new') => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <h3>{{ $document->exists ? __('kepabean.edit').' — '.$document->internal_number : __('kepabean.new') }}</h3>
    @include('kepabean.alerts')
    <div class="alert alert-info">{{ __('kepabean.local_notice') }}</div>
    <form method="POST" action="{{ $document->exists ? route('kepabean.update', $document) : route('kepabean.store') }}">
        @csrf @if($document->exists) @method('PUT') @endif
        <div class="card mb-3"><div class="card-body row g-3">
            <div class="col-md-4"><label class="form-label">{{ __('kepabean.type') }}</label><select name="document_type" class="form-select" required>@foreach($types as $type)<option @selected(old('document_type', $document->document_type) === $type)>{{ $type }}</option>@endforeach</select></div>
            @foreach(['nomor_aju' => 'aju', 'kode_kantor' => 'office', 'currency' => 'currency', 'exchange_rate' => 'rate'] as $field => $label)
            <div class="col-md-4"><label class="form-label">{{ __('kepabean.'.$label) }}</label><input name="{{ $field }}" class="form-control" value="{{ old($field, $document->$field) }}" @if($field === 'exchange_rate') type="number" step="0.000001" min="0.000001" required @elseif($field === 'currency') maxlength="3" required @else maxlength="{{ $field === 'nomor_aju' ? 100 : 20 }}" @endif></div>
            @endforeach
        </div></div>
        <div class="card mb-3"><div class="card-body"><h5>{{ __('kepabean.items') }}</h5>
            <div class="table-responsive"><table class="table"><thead><tr><th>HS Code</th>@foreach(['description','qty','unit','net','value'] as $key)<th>{{ __('kepabean.'.$key) }}</th>@endforeach<th></th></tr></thead><tbody id="goodsRows">
                @php($rows = old('details', $document->exists ? $document->details->map(fn($line) => $line->only(['hs_code','deskripsi_barang','qty','satuan','berat_bersih','nilai']))->all() : [[]]))
                @foreach($rows as $i => $row)
                <tr>@foreach(['hs_code','deskripsi_barang','qty','satuan','berat_bersih','nilai'] as $field)<td><input class="form-control form-control-sm" name="details[{{ $i }}][{{ $field }}]" value="{{ $row[$field] ?? '' }}" @if(in_array($field, ['qty','berat_bersih','nilai'])) type="number" step="0.0001" min="{{ $field === 'qty' ? '0.0001' : '0' }}" @else maxlength="{{ $field === 'deskripsi_barang' ? 255 : 20 }}" @endif @if(! in_array($field, ['hs_code','berat_bersih'])) required @endif></td>@endforeach<td><button type="button" class="btn btn-sm btn-outline-danger remove-good">×</button></td></tr>
                @endforeach
            </tbody></table></div>
            <button type="button" id="addGood" class="btn btn-outline-primary">+ {{ __('kepabean.add_item') }}</button>
        </div></div>
        <button class="btn btn-primary">{{ __('kepabean.save') }}</button> <a href="{{ route('kepabean.documents') }}" class="btn btn-outline-secondary">{{ __('kepabean.back') }}</a>
    </form>
</div>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const rows = document.getElementById('goodsRows');
    let index = {{ count($rows) }};
    const template = rows.firstElementChild.cloneNode(true);
    document.getElementById('addGood').addEventListener('click', () => {
        const row = template.cloneNode(true);
        row.querySelectorAll('input').forEach(input => { input.name = input.name.replace(/\[\d+\]/, '[' + index + ']'); input.value = ''; });
        index++; rows.appendChild(row);
    });
    rows.addEventListener('click', event => {
        if (event.target.classList.contains('remove-good') && rows.children.length > 1) event.target.closest('tr').remove();
    });
});
</script>
@endsection