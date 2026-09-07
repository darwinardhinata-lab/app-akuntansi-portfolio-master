@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Manufaktur' => '#', 'Master Fabric (Kain)' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Master Fabric (Kain)</h3>
            <p class="text-muted small mb-0">Data induk kain GREY & FINISHED beserta stok & HPP moving average.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('mfg.fabrics.index', ['export' => 'excel']) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fa-solid fa-file-import me-1"></i> Import
            </button>
            <button type="button" class="btn btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="fa-solid fa-plus me-1"></i> Tambah Fabric
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari Kode/Jenis Fabric" value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100">Cari</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        <tr>
                            <th>Kode</th><th>Jenis</th><th>State</th><th>GSM</th><th>Warna</th>
                            <th>Satuan</th><th>Stok</th><th>HPP Rata-rata</th><th>Status</th><th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($fabrics as $fabric)
                            <tr>
                                <td class="fw-bold py-2">{{ $fabric->fabric_code }}</td>
                                <td class="py-2">{{ $fabric->fabric_type }} @if($fabric->subtype)<br><small class="text-muted">{{ $fabric->subtype }}</small>@endif</td>
                                <td class="text-center py-2"><span class="badge {{ $fabric->state === 'GREY' ? 'bg-secondary' : 'bg-info text-dark' }}">{{ $fabric->state }}</span></td>
                                <td class="text-center py-2">{{ $fabric->gsm ?? '-' }}</td>
                                <td class="py-2">{{ $fabric->color ?? '-' }}</td>
                                <td class="text-center py-2">{{ $fabric->unit }}</td>
                                <td class="text-end py-2">{{ number_format($fabric->stock_quantity, 2) }}</td>
                                <td class="text-end py-2">Rp {{ number_format($fabric->average_cost, 2) }}</td>
                                <td class="text-center py-2">
                                    <span class="badge {{ $fabric->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $fabric->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="text-center py-2">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal"
                                            data-bs-target="#modalEdit{{ $fabric->id }}"><i class="fas fa-edit"></i></button>
                                        <form action="{{ route('mfg.fabrics.destroy', $fabric->id) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Yakin hapus fabric ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="modalEdit{{ $fabric->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <form action="{{ route('mfg.fabrics.update', $fabric->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">Edit Fabric</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <div class="mb-2"><label class="form-label">Kode Fabric</label>
                                                    <input type="text" name="fabric_code" class="form-control" value="{{ $fabric->fabric_code }}" required></div>
                                                <div class="mb-2"><label class="form-label">Jenis</label>
                                                    <input type="text" name="fabric_type" class="form-control" value="{{ $fabric->fabric_type }}" required></div>
                                                <div class="mb-2"><label class="form-label">Subtype</label>
                                                    <input type="text" name="subtype" class="form-control" value="{{ $fabric->subtype }}"></div>
                                                <div class="mb-2"><label class="form-label">State</label>
                                                    <select name="state" class="form-select" required>
                                                        <option value="GREY" {{ $fabric->state === 'GREY' ? 'selected' : '' }}>GREY</option>
                                                        <option value="FINISHED" {{ $fabric->state === 'FINISHED' ? 'selected' : '' }}>FINISHED</option>
                                                    </select></div>
                                                <div class="mb-2"><label class="form-label">GSM</label>
                                                    <input type="number" name="gsm" class="form-control" value="{{ $fabric->gsm }}"></div>
                                                <div class="mb-2"><label class="form-label">Komposisi</label>
                                                    <input type="text" name="composition" class="form-control" value="{{ $fabric->composition }}"></div>
                                                <div class="mb-2"><label class="form-label">Lebar (width)</label>
                                                    <input type="number" step="0.01" name="width" class="form-control" value="{{ $fabric->width }}"></div>
                                                <div class="mb-2"><label class="form-label">Warna</label>
                                                    <input type="text" name="color" class="form-control" value="{{ $fabric->color }}"></div>
                                                <div class="mb-2"><label class="form-label">Satuan</label>
                                                    <input type="text" name="unit" class="form-control" value="{{ $fabric->unit }}" required></div>
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $fabric->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label">Aktif</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="10" class="text-center py-5 text-muted">Belum ada data fabric.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $fabrics->links() }}
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.fabrics.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Tambah Fabric</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">Kode Fabric</label>
                        <input type="text" name="fabric_code" class="form-control" required placeholder="FB-GREY-180"></div>
                    <div class="mb-2"><label class="form-label">Jenis</label>
                        <input type="text" name="fabric_type" class="form-control" required placeholder="Single Jersey"></div>
                    <div class="mb-2"><label class="form-label">Subtype</label>
                        <input type="text" name="subtype" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">State</label>
                        <select name="state" class="form-select" required>
                            <option value="GREY">GREY</option>
                            <option value="FINISHED">FINISHED</option>
                        </select></div>
                    <div class="mb-2"><label class="form-label">GSM</label>
                        <input type="number" name="gsm" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Komposisi</label>
                        <input type="text" name="composition" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Lebar (width)</label>
                        <input type="number" step="0.01" name="width" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Warna</label>
                        <input type="text" name="color" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">Satuan</label>
                        <input type="text" name="unit" class="form-control" value="KGS" required></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">Simpan</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.fabrics.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">Import Master Fabric</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fa-solid fa-info-circle me-1"></i> Gunakan susunan kolom template.
                        <a href="{{ route('mfg.fabrics.download-template') }}" class="fw-bold">Download Template</a>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">Pilih File (.xlsx/.xls/.csv)</label>
                        <input type="file" name="file_excel" class="form-control" required accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="form-text">Stok & HPP tidak ikut diimport — hanya berubah lewat transaksi MRN.</div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary fw-bold">Mulai Import</button></div>
            </div>
        </form>
    </div>
</div>
@endsection
