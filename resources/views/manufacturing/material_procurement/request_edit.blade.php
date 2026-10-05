@extends('layouts.app')
@section('title', 'Edit Material Purchase Request')
@section('content')
<div class="container-fluid px-0">
    <h3 class="fw-bold">Edit Material Purchase Request {{ $materialRequest->request_number }}</h3>
    <p class="text-muted">Edit DRAFT revisi {{ $materialRequest->revision_no }}; tidak membuat stok atau jurnal.</p>
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
    <form method="POST" action="{{ route('mfg.material-requests.update', $materialRequest->id) }}" class="card card-body">
        @csrf @method('PUT')
        <div class="row g-3">
            <div class="col-md-4"><label class="form-label">Tanggal Permintaan</label><input class="form-control" type="date" name="request_date" value="{{ old('request_date', $materialRequest->request_date?->format('Y-m-d')) }}" required></div>
            <div class="col-md-4"><label class="form-label">Tanggal Dibutuhkan</label><input class="form-control" type="date" name="required_date" value="{{ old('required_date', $materialRequest->required_date?->format('Y-m-d')) }}"></div>
            <div class="col-md-4"><label class="form-label">Catatan</label><input class="form-control" name="remarks" value="{{ old('remarks', $materialRequest->remarks) }}"></div>
        </div>
        @php
            $editItems = old('items', $materialRequest->details->map(fn ($detail) => [
                'item_type' => $detail->item_type, 'yarn_id' => $detail->yarn_id, 'fabric_id' => $detail->fabric_id,
                'auxiliary_material_id' => $detail->auxiliary_material_id, 'item_name' => $detail->item_name,
                'qty' => $detail->qty_requested, 'unit' => $detail->unit, 'remarks' => $detail->remarks,
            ])->all());
        @endphp
        @foreach($editItems as $index => $item)
        <hr><div class="row g-3">
            <div class="col-md-2"><label class="form-label">Tipe</label><select class="form-select" name="items[{{ $index }}][item_type]">@foreach(['YARN' => 'Yarn', 'FABRIC' => 'Fabric', 'AUXILIARY' => 'Bahan Penolong'] as $type => $label)<option value="{{ $type }}" @selected(($item['item_type'] ?? '') === $type)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Yarn</label><select class="form-select" name="items[{{ $index }}][yarn_id]"><option value="">Pilih Yarn</option>@foreach($yarns as $y)<option value="{{ $y->id }}" @selected((string) ($item['yarn_id'] ?? '') === (string) $y->id)>{{ $y->yarn_code }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Fabric</label><select class="form-select" name="items[{{ $index }}][fabric_id]"><option value="">Pilih Fabric</option>@foreach($fabrics as $f)<option value="{{ $f->id }}" @selected((string) ($item['fabric_id'] ?? '') === (string) $f->id)>{{ $f->fabric_code }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Bahan Penolong</label><select class="form-select" name="items[{{ $index }}][auxiliary_material_id]"><option value="">Pilih Material</option>@foreach($auxiliaryMaterials as $a)<option value="{{ $a->id }}" @selected((string) ($item['auxiliary_material_id'] ?? '') === (string) $a->id)>{{ $a->material_code }}</option>@endforeach</select></div>
            <div class="col-md-2"><label class="form-label">Nama</label><input class="form-control" name="items[{{ $index }}][item_name]" value="{{ $item['item_name'] ?? '' }}" required maxlength="255"></div>
            <div class="col-md-1"><label class="form-label">Qty</label><input class="form-control" type="number" step="0.01" min="0.01" name="items[{{ $index }}][qty]" value="{{ $item['qty'] ?? '' }}" required></div>
            <div class="col-md-1"><label class="form-label">UOM</label><input class="form-control" name="items[{{ $index }}][unit]" value="{{ $item['unit'] ?? '' }}" required maxlength="20"></div>
            <div class="col-md-12"><label class="form-label">Catatan Detail</label><input class="form-control" name="items[{{ $index }}][remarks]" value="{{ $item['remarks'] ?? '' }}"></div>
        </div>
        @endforeach
        <div class="mt-4"><button class="btn btn-primary">Simpan Perubahan PR</button><a class="btn btn-outline-secondary" href="{{ route('mfg.material-requests.show', $materialRequest->id) }}">Batal</a></div>
    </form>
</div>
@endsection