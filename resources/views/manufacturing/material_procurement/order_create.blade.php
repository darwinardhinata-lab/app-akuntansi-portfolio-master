@extends('layouts.app')
@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold">Buat Material Purchase Order</h3>
    <p class="text-muted">Sumber PR: {{ $materialRequest->request_number }}. PO belum membuat stok atau jurnal.</p>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    <form method="POST" action="{{ route('mfg.material-orders.store') }}" class="card card-body">@csrf
        <input type="hidden" name="request_id" value="{{ $materialRequest->id }}">
        <div class="row g-3"><div class="col-md-4"><label class="form-label">Supplier Material</label><select class="form-select" name="supplier_id" required><option value="">Pilih Supplier</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}">{{ $supplier->supplier_code }} - {{ $supplier->supplier_name }}</option>@endforeach</select></div><div class="col-md-3"><label class="form-label">Tanggal PO</label><input class="form-control" type="date" name="po_date" value="{{ now()->toDateString() }}" required></div><div class="col-md-5"><label class="form-label">Catatan</label><input class="form-control" name="remarks"></div></div>
        <hr><h6>Detail dari PR Approved</h6>
        @foreach($materialRequest->details as $index => $detail)
            <div class="row g-2 mb-2 align-items-end border-bottom pb-2">
                <input type="hidden" name="items[{{ $index }}][source_request_detail_id]" value="{{ $detail->id }}">
                <input type="hidden" name="items[{{ $index }}][item_type]" value="{{ $detail->item_type }}">
                <input type="hidden" name="items[{{ $index }}][yarn_id]" value="{{ $detail->yarn_id }}">
                <input type="hidden" name="items[{{ $index }}][fabric_id]" value="{{ $detail->fabric_id }}">
                <input type="hidden" name="items[{{ $index }}][auxiliary_material_id]" value="{{ $detail->auxiliary_material_id }}">
                <div class="col-md-2"><label class="form-label">Tipe</label><div><span class="badge bg-secondary">{{ $detail->item_type }}</span></div></div>
                <div class="col-md-3"><label class="form-label">Material</label><input class="form-control" name="items[{{ $index }}][item_name]" value="{{ $detail->item_name }}" required></div>
                <div class="col-md-2"><label class="form-label">Qty</label><input class="form-control" type="number" step="0.01" min="0.01" name="items[{{ $index }}][qty]" value="{{ $detail->qty_requested - $detail->qty_ordered }}" required></div>
                <div class="col-md-2"><label class="form-label">UOM</label><input class="form-control" name="items[{{ $index }}][unit]" value="{{ $detail->unit }}" required></div>
                <div class="col-md-3"><label class="form-label">Harga Satuan</label><input class="form-control" type="number" step="0.01" min="0" name="items[{{ $index }}][rate]" required></div>
            </div>
        @endforeach
        <div class="mt-4"><button class="btn btn-primary">Simpan Draft PO</button><a class="btn btn-outline-secondary" href="{{ route('mfg.material-requests.index') }}">Batal</a></div>
    </form>
</div>
@endsection