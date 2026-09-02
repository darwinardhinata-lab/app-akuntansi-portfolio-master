@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Akuntansi' => '#', 'Master Kategori Payment' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Master Kategori Payment Plan</h3>
            <p class="text-muted small mb-0">Kelola kategori payment plan yang dapat ditambah, diedit, atau dihapus.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="fa-solid fa-plus me-1"></i> Tambah Kategori
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <strong><i class="fas fa-check-circle"></i> Berhasil!</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <strong><i class="fas fa-exclamation-triangle"></i> Terjadi Kesalahan!</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle sticky-top">
                        <tr>
                            <th>#</th>
                            <th>Nama Kategori</th>
                            <th>Slug</th>
                            <th>Deskripsi</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($categories as $index => $category)
                            <tr>
                                <td class="text-center py-2">{{ $index + 1 }}</td>
                                <td class="fw-bold py-2">{{ $category->name }}</td>
                                <td class="py-2">{{ $category->slug }}</td>
                                <td class="py-2">{{ $category->description ?? '-' }}</td>
                                <td class="text-center py-2">
                                    <span class="badge {{ $category->is_active ? 'bg-success' : 'bg-secondary' }} px-3 py-1">
                                        {{ $category->is_active ? 'AKTIF' : 'NONAKTIF' }}
                                    </span>
                                </td>
                                <td class="text-center py-2">
                                    <div class="btn-group" role="group">
                                        <button type="button" class="btn btn-sm btn-outline-warning" title="Edit" onclick="editCategory('{{ $category->id }}', '{{ $category->name }}', '{{ $category->slug }}', '{{ $category->description }}', {{ $category->is_active ? 'true' : 'false' }})" data-bs-toggle="modal" data-bs-target="#modalEdit">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <form action="{{ route('payment-category.destroy', $category->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus kategori ini? Data yang sudah menggunakan kategori ini tidak akan terhapus.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-light"></i><br>
                                    Belum ada kategori payment plan.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Create -->
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('payment-category.store') }}" method="POST" class="modal-content border-0 shadow-lg">
            @csrf
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Tambah Kategori Payment Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control" placeholder="PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Slug <span class="text-danger">*</span></label>
                    <input type="text" name="slug" class="form-control" placeholder="hutang" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Deskripsi</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Keterangan kategori..."></textarea>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="is_active_create" checked>
                    <label class="form-check-label" for="is_active_create">Aktif</label>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">Simpan</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit -->
<div class="modal fade" id="modalEdit" tabindex="-1">
    <div class="modal-dialog">
        <form action="" method="POST" id="formEdit" class="modal-content border-0 shadow-lg">
            @csrf
            @method('PUT')
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold">Edit Kategori Payment Plan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="mb-3">
                    <label class="form-label fw-bold">Nama Kategori <span class="text-danger">*</span></label>
                    <input type="text" name="name" id="edit_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Slug <span class="text-danger">*</span></label>
                    <input type="text" name="slug" id="edit_slug" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-bold">Deskripsi</label>
                    <textarea name="description" id="edit_description" class="form-control" rows="2"></textarea>
                </div>
                <div class="form-check">
                    <input type="checkbox" name="is_active" class="form-check-input" id="edit_is_active">
                    <label class="form-check-label" for="edit_is_active">Aktif</label>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">Update</button>
            </div>
        </form>
    </div>
</div>

<script>
function editCategory(id, name, slug, description, is_active) {
    document.getElementById('formEdit').action = '/payment-category/' + id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_slug').value = slug;
    document.getElementById('edit_description').value = description || '';
    document.getElementById('edit_is_active').checked = is_active;
}
</script>
@endsection