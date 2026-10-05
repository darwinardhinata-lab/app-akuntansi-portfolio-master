@extends('layouts.app')
@section('title', 'Edit Material Purchase Order')
@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold">Edit Material Purchase Order {{ $materialOrder->po_number }}</h3>
    <p class="text-muted">Supplier dan PR sumber tetap. Edit DRAFT tidak membuat stok atau jurnal.</p>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('mfg.material-orders.update', $materialOrder->id) }}" class="card card-body">
        @csrf @method('PUT')
        <div class="row g-3"><div class="col-md-4"><label class="form-label">Supplier Material</label><div>{{ $materialOrder->supplier?->supplier_name ?? '-' }}</div></div><div class="col-md-3"><label class="form-label">Tanggal PO</label><input class="form-control" type="date" name="po_date" value="{{ old('po_date', $materialOrder->po_date?->format('Y-m-d')) }}" required></div><div class="col-md-5"><label class="form-label">Catatan</label><input class="form-control" name="remarks" value="{{ old('remarks', $materialOrder->remarks) }}"></div></div>
        @php
            $editItems = old('items', $materialOrder->details->map(fn ($detail) => [
                'source_request_detail_id' => $detail->source_request_detail_id, 'item_type' => $detail->item_type,
                'yarn_id' => $detail->yarn_id, 'fabric_id' => $detail->fabric_id, 'auxiliary_material_id' => $detail->auxiliary_material_id,
                'item_name' => $detail->item_name, 'qty' => $detail->qty, 'unit' => $detail->unit, 'rate' => $detail->rate,
            ])->all());
        @endphp
        <hr><h6>Detail dari PR Approved</h6>
        @foreach($editItems as $index => $item)
        <div class="row g-2 mb-2 align-items-end border-bottom pb-2">
            @foreach(['source_request_detail_id', 'item_type', 'yarn_id', 'fabric_id', 'auxiliary_material_id'] as $field)<input type="hidden" name="items[{{ $index }}][{{ $field }}]" value="{{ $item[$field] ?? '' }}">@endforeach
            <div class="col-md-2"><label class="form-label">Tipe</label><div><span class="badge bg-secondary">{{ $item['item_type'] ?? '-' }}</span></div></div>
            <div class="col-md-3"><label class="form-label">Material</label><input class="form-control" name="items[{{ $index }}][item_name]" value="{{ $item['item_name'] ?? '' }}" required maxlength="255"></div>
            <div class="col-md-2"><label class="form-label">Qty</label><input class="form-control" type="number" step="0.01" min="0.01" name="items[{{ $index }}][qty]" value="{{ $item['qty'] ?? '' }}" required></div>
            <div class="col-md-2"><label class="form-label">UOM</label><input class="form-control" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? '' }}" required maxlength="20"></div>
            <div class="col-md-3"><label class="form-label">Harga Satuan</label><input class="form-control" type="number" step="0.01" min="0" name="items[{{ $index }}][rate]" value="{{ $item['rate'] ?? '' }}" required></div>
        </div>
        @endforeach
        <div class="mt-4"><button class="btn btn-primary">Simpan Perubahan PO</button><a class="btn btn-outline-secondary" href="{{ route('mfg.material-orders.show', $materialOrder->id) }}">Batal</a></div>
    </form>
</div>
@endsection