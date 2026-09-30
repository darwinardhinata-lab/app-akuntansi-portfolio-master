@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', 'Master Bahan Penolong' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Master Bahan Penolong/Pembantu</h3>
            <p class="text-muted small mb-0">Persediaan 114004; stok dan HPP berubah melalui MRN/Issue — bukan dari form ini.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-outline-info btn-sm fw-bold" href="{{ route('mfg.material-ledger.index', ['item_type' => 'AUXILIARY']) }}"><i class="fa-solid fa-clipboard-list me-1"></i>Kartu Stok</a>
            <a class="btn btn-success btn-sm fw-bold" href="{{ route('mfg.auxiliary-materials.index', ['export' => 'excel']) }}"><i class="fa-solid fa-file-excel me-1"></i>Export</a>
            <button class="btn btn-info text-white btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#importMaterial"><i class="fa-solid fa-file-import me-1"></i>Import</button>
            <button class="btn btn-primary btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#createMaterial"><i class="fa-solid fa-plus me-1"></i>Tambah Bahan Penolong</button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="card shadow-sm border-0 mb-3"><div class="card-body py-2">
        <form method="GET" class="row g-2">
            <div class="col-md-4"><input class="form-control form-control-sm" name="search" value="{{ $search }}" placeholder="Cari kode atau nama bahan"></div>
            <div class="col-md-2"><button class="btn btn-sm btn-outline-primary w-100">Cari</button></div>
        </form>
    </div></div>

    <div class="card shadow-sm border-0 mb-4"><div class="card-body p-0"><div class="table-responsive">
        <table class="table table-bordered table-striped table-hover align-middle mb-0" style="font-size: 13px;">
            <thead class="bg-primary text-white text-center align-middle">
                <tr>
                    <th>HS Code</th><th>Kode</th><th>Description</th><th>Nama Bahan</th><th>Nama Inggris</th>
                    <th>Kategori</th><th>Warna</th><th>Spesifikasi</th><th>UOM</th><th>Meter/Gulung</th>
                    <th>Stok</th><th>HPP</th><th>Status</th><th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($materials as $material)
                    <tr>
                        <td>{{ $material->hs_code ?? '-' }}</td>
                        <td class="fw-bold">{{ $material->material_code }}</td>
                        <td>{{ $material->description ?? '-' }}</td>
                        <td>{{ $material->material_name }}</td>
                        <td>{{ $material->english_name ?? '-' }}</td>
                        <td>{{ $material->category ?? '-' }}</td>
                        <td>{{ $material->color ?? '-' }}</td>
                        <td>{{ Str::limit($material->specification ?? '-', 25) }}</td>
                        <td class="text-center">{{ $material->unit }}</td>
                        <td class="text-end">{{ $material->meters_per_roll !== null ? number_format($material->meters_per_roll, 2) : '-' }}</td>
                        <td class="text-end">{{ number_format($material->stock_quantity, 2) }}</td>
                        <td class="text-end">Rp {{ number_format($material->average_cost, 2) }}</td>
                        <td class="text-center"><span class="badge {{ $material->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $material->is_active ? 'AKTIF' : 'NONAKTIF' }}</span></td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="{{ route('mfg.material-ledger.index', ['item_type' => 'AUXILIARY', 'item_id' => $material->id]) }}" class="btn btn-sm btn-outline-info" title="Kartu Stok"><i class="fa-solid fa-clipboard-list"></i></a>
                                <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal" data-bs-target="#editMaterial{{ $material->id }}" title="Edit"><i class="fas fa-edit"></i></button>
                                @if($material->is_active)
                                    <form action="{{ route('mfg.auxiliary-materials.destroy', $material->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Nonaktifkan Bahan Penolong {{ addslashes($material->material_code) }}? Riwayat tetap terjaga.')">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-outline-danger" title="Nonaktifkan"><i class="fa-solid fa-ban"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>

                    {{-- Modal Edit per baris --}}
                    <div class="modal fade" id="editMaterial{{ $material->id }}" tabindex="-1">
                        <div class="modal-dialog">
                            <form method="POST" action="{{ route('mfg.auxiliary-materials.update', $material->id) }}">@csrf @method('PUT')
                                <div class="modal-content">
                                    <div class="modal-header"><h5 class="modal-title">Edit Bahan Penolong</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                    <div class="modal-body">
                                        <div class="mb-2"><label class="form-label">HS Code</label><input name="hs_code" class="form-control" value="{{ $material->hs_code }}"></div>
                                        <div class="mb-2"><label class="form-label">Kode Bahan <span class="text-danger">*</span></label><input name="material_code" class="form-control" value="{{ $material->material_code }}" required></div>
                                        <div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control" value="{{ $material->description }}"></div>
                                        <div class="mb-2"><label class="form-label">Nama Bahan <span class="text-danger">*</span></label><input name="material_name" class="form-control" value="{{ $material->material_name }}" required></div>
                                        <div class="mb-2"><label class="form-label">Nama Material Inggris</label><input name="english_name" class="form-control" value="{{ $material->english_name }}"></div>
                                        <div class="mb-2"><label class="form-label">Kategori</label><input name="category" class="form-control" value="{{ $material->category }}"></div>
                                        <div class="mb-2"><label class="form-label">Warna</label><input name="color" class="form-control" value="{{ $material->color }}"></div>
                                        <div class="mb-2"><label class="form-label">Spesifikasi/Deskripsi</label><textarea name="specification" class="form-control">{{ $material->specification }}</textarea></div>
                                        <div class="mb-2"><label class="form-label">Meter per Gulung</label><input type="number" step="0.01" min="0" name="meters_per_roll" class="form-control" value="{{ $material->meters_per_roll }}"></div>
                                        <div class="mb-2"><label class="form-label">UOM <span class="text-danger">*</span></label><input name="unit" class="form-control" value="{{ $material->unit }}" required></div>
                                        <div class="form-check mt-2"><input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $material->is_active ? 'checked' : '' }}><label class="form-check-label">Aktif</label></div>
                                    </div>
                                    <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
                                </div>
                            </form>
                        </div>
                    </div>

                @empty
                    <tr><td colspan="14" class="text-center py-5 text-muted">Belum ada bahan penolong. Tambahkan atau import menggunakan template CSV.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div></div></div>
    {{ $materials->links() }}
</div>

{{-- Modal Create --}}
<div class="modal fade" id="createMaterial" tabindex="-1"><div class="modal-dialog"><form method="POST" action="{{ route('mfg.auxiliary-materials.store') }}">@csrf
    <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Tambah Bahan Penolong</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="mb-2"><label class="form-label">HS Code</label><input name="hs_code" class="form-control" placeholder="HS Code"></div>
            <div class="mb-2"><label class="form-label">Kode Bahan <span class="text-danger">*</span></label><input name="material_code" class="form-control" placeholder="AUX-LABEL-01" required></div>
            <div class="mb-2"><label class="form-label">Description</label><input name="description" class="form-control" placeholder="Woven Label"></div>
            <div class="mb-2"><label class="form-label">Nama Bahan <span class="text-danger">*</span></label><input name="material_name" class="form-control" placeholder="Label Tenun" required></div>
            <div class="mb-2"><label class="form-label">Nama Material Inggris</label><input name="english_name" class="form-control" placeholder="Woven Label"></div>
            <div class="mb-2"><label class="form-label">Kategori</label><input name="category" class="form-control" placeholder="Bahan Penolong"></div>
            <div class="mb-2"><label class="form-label">Warna</label><input name="color" class="form-control" placeholder="Putih"></div>
            <div class="mb-2"><label class="form-label">Spesifikasi/Deskripsi</label><textarea name="specification" class="form-control" placeholder="50x20 mm"></textarea></div>
            <div class="mb-2"><label class="form-label">Meter per Gulung</label><input type="number" step="0.01" min="0" name="meters_per_roll" class="form-control" placeholder="0"></div>
            <div class="mb-2"><label class="form-label">UOM <span class="text-danger">*</span></label><input name="unit" class="form-control" value="PCS" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Simpan</button></div>
    </div>
</form></div></div>

{{-- Modal Import --}}
<div class="modal fade" id="importMaterial" tabindex="-1"><div class="modal-dialog"><form method="POST" enctype="multipart/form-data" action="{{ route('mfg.auxiliary-materials.import') }}">@csrf
    <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Import Bahan Penolong</h5><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="alert alert-info small"><i class="fa-solid fa-info-circle me-1"></i>Gunakan susunan kolom template. <a href="{{ route('mfg.auxiliary-materials.download-template') }}" class="fw-bold">Download template CSV</a></div>
            <input class="form-control" type="file" name="file_excel" accept=".xlsx,.xls,.csv" required>
            <div class="form-text mt-1">Import hanya metadata; stok dan HPP tidak berubah.</div>
        </div>
        <div class="modal-footer"><button class="btn btn-primary">Import</button></div>
    </div>
</form></div></div>
@endsection