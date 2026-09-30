@extends('layouts.app')

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
        <div><h3 class="fw-bold mb-1">BOM Produk</h3><p class="text-muted mb-0">Pilih barang jadi untuk menetapkan kebutuhan bahan per unit produksi.</p></div>
        <div class="d-flex gap-2 flex-wrap"><a href="{{ route('mfg.product-boms.index', ['export' => 'excel']) }}" class="btn btn-success"><i class="fa-solid fa-file-excel me-1"></i> Export BOM</a><a href="{{ route('mfg.product-boms.download-template') }}" class="btn btn-outline-info"><i class="fa-solid fa-download me-1"></i> Template Import</a><button class="btn btn-info text-white" data-bs-toggle="modal" data-bs-target="#importBom"><i class="fa-solid fa-file-import me-1"></i> Import BOM</button><a href="{{ route('product.index') }}" class="btn btn-outline-secondary"><i class="fa-solid fa-boxes-stacked me-1"></i> Master Barang</a></div>
    </div>
    <form method="GET" class="card card-body shadow-sm border-0 mb-3"><div class="row g-2"><div class="col-md-5"><input name="search" class="form-control" value="{{ $search }}" placeholder="Cari SKU atau nama barang jadi"></div><div class="col-md-2"><button class="btn btn-outline-primary w-100">Cari</button></div></div></form>
    <div class="card shadow-sm border-0"><div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead class="table-light"><tr><th>SKU</th><th>Nama Barang Jadi</th><th>Kategori</th><th class="text-center">Item BOM Aktif</th><th class="text-center">Aksi</th></tr></thead><tbody>
        @forelse($products as $product)
            <tr><td class="fw-bold text-primary">{{ $product->sku }}</td><td>{{ $product->name }}<br><small class="text-muted">{{ $product->variation }}</small></td><td>{{ $product->category_name ?? '-' }}</td><td class="text-center"><span class="badge {{ $product->active_bom_count ? 'bg-success' : 'bg-secondary' }}">{{ $product->active_bom_count }} item</span></td><td class="text-center"><a class="btn btn-sm btn-success" href="{{ route('mfg.product-boms.show', $product->id) }}"><i class="fa-solid fa-list-check me-1"></i> Atur BOM</a></td></tr>
        @empty <tr><td colspan="5" class="text-center text-muted py-4">Belum ada barang jadi. Buat barang di Master Barang terlebih dahulu.</td></tr>
        @endforelse
    </tbody></table></div><div class="card-footer bg-white">{{ $products->links() }}</div></div>
</div>
<div class="modal fade" id="importBom" tabindex="-1"><div class="modal-dialog"><form action="{{ route('mfg.product-boms.import') }}" method="POST" enctype="multipart/form-data" class="modal-content">@csrf<div class="modal-header"><h5 class="modal-title">Import BOM Produk</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><div class="alert alert-info small">Gunakan <a href="{{ route('mfg.product-boms.download-template') }}">template BOM</a>. Upload ulang dengan SKU, jenis, dan kode bahan yang sama akan memperbarui BOM, bukan membuat duplikat.</div><input type="file" name="file_excel" class="form-control" accept=".xlsx,.xls,.csv" required></div><div class="modal-footer"><button class="btn btn-primary">Mulai Import</button></div></form></div></div>
@endsection