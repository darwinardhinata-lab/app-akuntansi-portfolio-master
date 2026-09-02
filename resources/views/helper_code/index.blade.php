@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Master Data' => '#', 'Helper Code' => null]" />
@endsection

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .helper-wrapper { font-family: 'Inter', sans-serif; color: #334155; max-width: 1200px; margin: 0 auto; }
    .card-modern { background: #ffffff; border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 25px; }
    .table-clean th { border-bottom: 2px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; padding: 15px 10px; }
    .table-clean td { vertical-align: middle; padding: 15px 10px; border-bottom: 1px solid #f8fafc; font-size: 0.9rem; }
    .badge-balance { padding: 4px 10px; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .bg-debet { background: #dcfce7; color: #166534; }
    .bg-kredit { background: #fef9c3; color: #854d0e; }
    
    .btn-import { background: #10b981; color: white; border-radius: 8px; font-weight: 600; padding: 8px 16px; border: none; font-size: 0.85rem;}
    .btn-bulk-del { background: #ef4444; color: white; border-radius: 8px; font-weight: 600; padding: 8px 16px; border: none; display: none; font-size: 0.85rem;}
    
    .btn-action { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 600; transition: 0.2s; border: 1px solid #e2e8f0; background: white; text-decoration: none; color: #475569; display: inline-block; }
    .btn-action:hover { background: #f8fafc; color: #0f172a; border-color: #cbd5e1; }
</style>

<div class="helper-wrapper mt-4 mb-5">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Master Kode Bantu (Relasi)</h3>
            <p class="text-muted small mb-0">Kelola daftar entitas untuk otomatisasi laporan</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-danger fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" id="btnTriggerDelete" style="display:none;">Hapus Terpilih</button>

            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importHelper">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>

            <a href="{{ route('helper.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
        </div>
    </div>
    <div class="modal fade" id="importHelper" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('helper.import') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Import CSV Kode Bantu (Relasi)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-4">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-info-circle me-1"></i> Gunakan susunan kolom template CSV.</span>
                            <a href="{{ route('helper.download-template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <label class="fw-bold text-dark">Pilih File CSV Kode Bantu</label>
                    <input type="file" name="file_excel" accept=".csv, .xls, .xlsx" class="form-control mt-2" required>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">Mulai Import</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success border-0 shadow-sm mb-4">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger border-0 shadow-sm mb-4">{{ session('error') }}</div> @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> Filter Analitik Pencarian</div>
        <form action="{{ route('helper.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-12 col-md-8">
                <label class="form-label small fw-bold text-muted mb-1">Pencarian Nomor / Keterangan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik Kode atau Nama Entitas / Kategori..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('helper.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card-modern">
        <form action="{{ route('helper.bulk-delete') }}" method="POST" id="mainFormDelete">
            @csrf
            <div class="table-responsive">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th width="5%"><input type="checkbox" class="form-check-input" id="masterCheckbox"></th>
                            <th width="15%">Kode Bantu</th>
                            <th width="35%">Nama Entitas</th>
                            <th width="15%">Kategori</th>
                            <th width="15%">Pos Saldo</th>
                            <th width="15%" class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($helpers as $helper)
                            <tr>
                                <td><input type="checkbox" name="ids[]" value="{{ $helper->helper_code }}" class="form-check-input row-checkbox"></td>
                                <td class="fw-bold text-dark">{{ $helper->helper_code }}</td>
                                <td class="fw-medium">{{ $helper->entity_name }}</td>
                                <td><span class="text-muted">{{ $helper->marketing_name ?? '-' }}</span></td>
                                <td>
                                    <span class="badge-balance {{ ($helper->normal_balance ?? 'DEBET') == 'DEBET' ? 'bg-debet' : 'bg-kredit' }}">
                                        {{ $helper->normal_balance ?? 'DEBET' }}
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex gap-2 justify-content-center">
                                        <a href="#" class="btn-action" style="color: #3b82f6; border-color: #dbeafe;">Edit</a>
                                        
                                        <button type="button" class="btn-action" style="color: #ef4444; border-color: #fee2e2;" onclick="deleteItem('{{ $helper->helper_code }}', '{{ $helper->entity_name }}')">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center py-5">Data masih kosong atau tidak ditemukan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-end mt-4 pt-3 border-top">
                {{ $helpers->links() }}
            </div>

        </form>
    </div>
</div>

<form id="singleDeleteForm" method="POST" style="display:none;"> @csrf @method('DELETE') </form>

<script>
    const masterCheckbox = document.getElementById('masterCheckbox');
    const rowCheckboxes = document.querySelectorAll('.row-checkbox');
    const btnTriggerDelete = document.getElementById('btnTriggerDelete');
    const mainFormDelete = document.getElementById('mainFormDelete');

    masterCheckbox.addEventListener('change', function() {
        rowCheckboxes.forEach(cb => cb.checked = this.checked);
        updateButtonVisibility();
    });

    rowCheckboxes.forEach(cb => cb.addEventListener('change', updateButtonVisibility));

    function updateButtonVisibility() {
        const checkedCount = document.querySelectorAll('.row-checkbox:checked').length;
        btnTriggerDelete.style.display = checkedCount > 0 ? 'block' : 'none';
        btnTriggerDelete.innerText = `Hapus (${checkedCount})`;
    }

    btnTriggerDelete.addEventListener('click', function() {
        if (confirm('Hapus semua data yang dipilih secara permanen?')) {
            mainFormDelete.submit();
        }
    });

    function deleteItem(id, name) {
        if (confirm(`Hapus data [${id}] ${name}?`)) {
            const form = document.getElementById('singleDeleteForm');
            form.action = `/kode-bantu/${id}`;
            form.submit();
        }
    });
</script>
@endsection