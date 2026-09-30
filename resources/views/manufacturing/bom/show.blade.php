@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h3 class="fw-bold mb-1">BOM Produk</h3><p class="text-muted mb-0">{{ $product->sku }} — {{ $product->name }}</p></div>
        <a href="{{ route('product.index') }}" class="btn btn-outline-secondary">Kembali ke Master Barang</a>
    </div>
    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if(session('error'))<div class="alert alert-danger">{{ session('error') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="card shadow-sm border-0 mb-3"><div class="card-header bg-white"><b>Tambah Komponen BOM</b></div><div class="card-body">
        <form method="POST" action="{{ route('mfg.product-boms.store', $product->id) }}" class="row g-2 align-items-end">@csrf
            <div class="col-md-2"><label class="form-label">Jenis Bahan</label><select name="item_type" id="itemType" class="form-select" required><option value="YARN">Yarn</option><option value="FABRIC">Fabric</option><option value="AUXILIARY">Bahan Penolong</option></select></div>
            <div class="col-md-4"><label class="form-label">Material</label><select name="material_id" id="materialId" class="form-select" required></select></div>
            <div class="col-md-2"><label class="form-label">Qty per Unit</label><input name="qty_per_unit" type="number" step="0.000001" min="0.000001" class="form-control" required></div>
            <div class="col-md-2"><label class="form-label">Waste %</label><input name="waste_percent" type="number" step="0.0001" min="0" max="100" class="form-control" value="0"></div>
            <div class="col-md-2"><button class="btn btn-primary w-100">Tambah BOM</button></div>
            <div class="col-12"><label class="form-label">Catatan</label><input name="remarks" class="form-control" placeholder="Opsional"></div>
        </form>
    </div></div>

    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-sm table-bordered mb-0"><thead class="table-light"><tr><th>Jenis</th><th>Kode</th><th>Material</th><th>Unit</th><th class="text-end">Qty/Unit</th><th class="text-end">Waste %</th><th>Catatan</th><th>Aksi</th></tr></thead><tbody>
        @forelse($boms as $bom)
            @php($material = $bom->item_type === 'YARN' ? $bom->yarn : ($bom->item_type === 'FABRIC' ? $bom->fabric : $bom->auxiliaryMaterial))
            <tr><td>{{ $bom->item_type }}</td><td>{{ $material?->yarn_code ?? $material?->fabric_code ?? $material?->material_code ?? '-' }}</td><td>{{ $material?->description ?? $material?->material_name ?? $material?->yarn_type ?? $material?->fabric_type ?? '-' }}</td><td>{{ $material?->unit ?? '-' }}</td><td class="text-end">{{ number_format($bom->qty_per_unit, 6) }}</td><td class="text-end">{{ number_format($bom->waste_percent, 4) }}</td><td>{{ $bom->remarks ?? '-' }}</td><td><form method="POST" action="{{ route('mfg.product-boms.destroy', [$product->id, $bom->id]) }}" onsubmit="return confirm('Hapus item BOM ini?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Hapus</button></form></td></tr>
        @empty <tr><td colspan="8" class="text-center text-muted py-4">Belum ada BOM. Tambahkan bahan untuk membuat kebutuhan otomatis pada SPK.</td></tr>
        @endforelse
    </tbody></table></div></div>
</div>
@endsection

@push('scripts')
<script>
const materials = {
    YARN: @json($yarns->map(fn ($m) => ['id' => $m->id, 'label' => $m->yarn_code.' - '.($m->description ?? $m->yarn_type).' ('.$m->unit.')'])->values()),
    FABRIC: @json($fabrics->map(fn ($m) => ['id' => $m->id, 'label' => $m->fabric_code.' - '.($m->description ?? $m->fabric_type).' ('.$m->unit.')'])->values()),
    AUXILIARY: @json($auxiliaryMaterials->map(fn ($m) => ['id' => $m->id, 'label' => $m->material_code.' - '.($m->description ?? $m->material_name).' ('.$m->unit.')'])->values()),
};
const type = document.getElementById('itemType'), material = document.getElementById('materialId');
function refreshMaterials() { material.innerHTML = (materials[type.value] || []).map(item => `<option value="${item.id}">${item.label}</option>`).join(''); }
type.addEventListener('change', refreshMaterials); refreshMaterials();
</script>
@endpush