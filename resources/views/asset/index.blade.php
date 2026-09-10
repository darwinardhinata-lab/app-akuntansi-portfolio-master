@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.fixed_asset') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
    <h3 class="fw-bold mb-1 text-dark">{{ __('erp.bc_asset_management') }}</h3>
    <p class="text-muted small mb-0">Kelola aset tetap, umur ekonomis, dan pantau penyusutan otomatis. Buat jurnal dengan kode akun 12000 (Aset Tetap) untuk menambahkan aset secara otomatis.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('aset.export', [
                'start_date' => request('start_date'),
                'end_date' => request('end_date'),
                'search' => request('search')
            ]) }}" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </a>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>
            <a href="{{ route('aset.template') }}" class="btn btn-outline-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-download me-1"></i> Template
            </a>
            <a href="{{ route('aset.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Input Manual
            </a>
        </div>
    </div>

    {{-- Import Modal --}}
    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('aset.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="importModalLabel">{{ __('erp.import_fixed_asset') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-3 small mb-4" style="background-color: #e0f2fe; color: #0369a1; border-color: #bae6fd; border-radius: 8px;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0"><i class="fa-solid fa-circle-info"></i> {{ __('erp.csv_excel_format_11col') }}</h6>
                                <a href="{{ route('aset.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-download me-1"></i> Download Template
                                </a>
                            </div>
                            <strong>{{ __('erp.column_order_header_required') }}</strong><br>
                            <code>No;Kode Aset;Nama Aset;Kategori;Qty;Tgl. Pemakaian;Nilai Perolehan;Akumulasi Penyusutan;Nilai Buku;Nilai Sisa;Status</code><br>
                            <div class="mt-2">
                                Kolom 1: No (opsional)<br>
                                Kolom 2: Kode Aset (wajib)<br>
                                Kolom 3: Nama Aset (wajib)<br>
                                Kolom 4: Kategori<br>
                                Kolom 5: Qty<br>
                                Kolom 6: Tgl. Pemakaian (wajib)<br>
                                Kolom 7: Nilai Perolehan (wajib)<br>
                                Kolom 8: Akumulasi Penyusutan<br>
                                Kolom 9: Nilai Buku<br>
                                Kolom 10: Nilai Sisa<br>
                                Kolom 11: Status (Aktif/Tidak Aktif)<br>
                            </div>
                            <div class="mt-3 text-danger fw-bold border-top border-info pt-2">
                                * Baris pertama di file CSV wajib berupa Header/Judul kolom.
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="file_excel" class="form-label fw-bold">{{ __('erp.choose_file_csv_excel') }}</label>
                            <input type="file" class="form-control" name="file_excel" id="file_excel" accept=".csv, .xls, .xlsx" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="fa-solid fa-upload me-1"></i> Proses Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    {{-- Notifications --}}
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            @foreach($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    {{-- Filter --}}
    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.search_filter') }}</div>
        <form action="{{ route('aset.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_short') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_short') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.row_label') }}</label>
                <select name="per_page" class="form-select form-select-sm">
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_asset_code_name') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari Kode Aset / Nama Aset..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> {{ __('erp.search_btn') }}</button>
                <a href="{{ route('aset.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    {{-- Asset Table --}}
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="table-responsive bg-white">
            <table class="table align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-uppercase text-muted" style="font-size: 0.75rem;">
                    <tr>
                        <th width="3%">{{ __('erp.no_abbr') }}</th>
                        <th width="8%">{{ __('erp.purchase_date_short') }}</th>
                        <th width="12%">{{ __('erp.asset_code') }}</th>
                        <th width="18%">{{ __('erp.asset_name') }}</th>
                        <th width="12%">{{ __('erp.purchase_price') }}</th>
                        <th width="10%" class="text-end">{{ __('erp.residual_value') }}</th>
                        <th width="10%" class="text-center">{{ __('erp.useful_life_months') }}</th>
                        <th width="12%">{{ __('erp.depreciation_per_month_v2') }}</th>
                        <th width="13%">{{ __('erp.accumulated_depreciation_v2') }}</th>
                        <th width="15%">{{ __('erp.ending_book_value') }}</th>
                        <th width="10%" class="text-center">{{ __('erp.status') }}</th>
                        <th width="10%" class="text-center">{{ __('erp.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($assets as $index => $asset)
                        <tr>
                            <td class="text-center">{{ $index + 1 + ($assets->currentPage() - 1) * $assets->perPage() }}</td>
                            <td class="text-center">{{ \Carbon\Carbon::parse($asset->purchase_date)->format('d/m/Y') }}</td>
                            <td class="text-center"><span class="badge bg-secondary bg-opacity-10 text-secondary">{{ $asset->asset_code }}</span></td>
                            <td>
                                <div class="fw-bold text-dark mb-1">{{ $asset->asset_name }}</div>
                                @if($asset->category)
                                    <div class="text-muted small mb-1">
                                        <span class="badge bg-info bg-opacity-10 text-info fw-medium">{{ $asset->category }}</span>
                                    </div>
                                @endif
                            </td>
                            <td class="text-end fw-bold text-dark">
                                Rp {{ number_format($asset->purchase_price, 2, ',', '.') }}
                            </td>
                            <td class="text-end text-muted">
                                Rp {{ number_format($asset->residual_value ?? 0, 2, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <form action="{{ route('aset.update', $asset->id) }}" method="POST" class="d-flex justify-content-center gap-1">
                                    @csrf
                                    <input type="number" name="useful_life_months" class="form-control form-control-sm text-center" value="{{ $asset->useful_life_months }}" min="0" style="max-width: 70px;" required>
                                    <button type="submit" class="btn btn-sm btn-primary py-0 px-2" style="border-radius: 6px;">✔</button>
                                </form>
                            </td>
                            <td class="text-end text-danger" style="font-weight: 500;">
                                Rp {{ number_format($asset->depreciation_per_month ?? 0, 2, ',', '.') }}
                            </td>
                            <td class="text-end text-warning fw-medium">
                                - Rp {{ number_format($asset->accumulated ?? 0, 2, ',', '.') }}
                            </td>
                            <td class="text-end text-success fw-bold">
                                Rp {{ number_format($asset->book_value ?? $asset->purchase_price, 2, ',', '.') }}
                            </td>
                            <td class="text-center">
                                <button type="button" onclick="toggleAssetStatus({{ $asset->id }}, this)"
                                    class="btn btn-sm shadow-sm {{ $asset->is_active ? 'btn-success' : 'btn-secondary' }}"
                                    title="Klik untuk toggle status">
                                    @if($asset->is_active)
                                        <i class="fa-solid fa-toggle-on me-1"></i> Aktif
                                    @else
                                        <i class="fa-solid fa-toggle-off me-1"></i> Tidak Aktif
                                    @endif
                                </button>
                            </td>
                            <td class="text-center">
                                <div class="btn-group" role="group">
                                    <button type="button" onclick="showEntityLog('{{ $asset->asset_code }}')" class="btn btn-sm btn-outline-info shadow-sm" title="Jejak Log Aktivitas">
                                        <i class="fa-solid fa-clock-rotate-left"></i>
                                    </button>
                                    <a href="{{ route('aset.create') }}" class="btn btn-sm btn-outline-primary shadow-sm" title="Duplikat/Duplicate">
                                        <i class="fa-solid fa-copy"></i>
                                    </a>
                                    <form id="delete-form-{{ $asset->id }}" action="{{ route('aset.destroy', $asset->id) }}" method="POST" style="display: none;">
                                        @csrf @method('DELETE')
                                    </form>
                                    <button type="button" onclick="if(confirm('Hapus aset ini? Aset yang sudah terhubung ke jurnal tidak akan memengaruhi jurnal.')) document.getElementById('delete-form-{{ $asset->id }}').submit();" class="btn btn-sm btn-outline-danger shadow-sm" title="Hapus Aset">
                                        <i class="fa-solid fa-trash-can"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="12" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-box-open mb-3" style="font-size: 2rem;"></i><br>
                                Belum ada data aset terdeteksi. Pastikan Anda memiliki Jurnal Pembelian Aset Tetap di Jurnal Umum, atau tambahkan aset secara manual.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if(isset($assets))
            <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">
                {{ $assets->links() }}
            </div>
        @endif
    </div>

    {{-- Quick Link to Depreciation List --}}
    <div class="mt-4 text-end">
        <a href="{{ route('aset.list') }}" class="btn btn-outline-warning btn-sm fw-bold shadow-sm text-dark">
            <i class="fa-solid fa-table-list me-1"></i> Lihat List Depresiasi (Matriks Nilai Buku)
        </a>
    </div>
</div>
@endsection

@push('scripts')
<script>
function toggleAssetStatus(assetId, btnElement) {
    if (!confirm('Ubah status aset ini?')) return;

    // Build URL dinamis menggunakan base URL aplikasi, hindari route() helper dengan parameter kosong
    const url = '{{ url("aset/toggle-status") }}/' + assetId;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

    // Disable button sementara untuk mencegah double-click
    btnElement.disabled = true;
    const originalContent = btnElement.innerHTML;

    fetch(url, {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': csrfToken,
            'Accept': 'application/json',
            'Content-Type': 'application/json'
        }
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Server error: ' + response.status);
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            // Update tampilan tombol sesuai status baru
            if (data.is_active) {
                btnElement.className = 'btn btn-sm shadow-sm btn-success';
                btnElement.innerHTML = '<i class="fa-solid fa-toggle-on me-1"></i> Aktif';
            } else {
                btnElement.className = 'btn btn-sm shadow-sm btn-secondary';
                btnElement.innerHTML = '<i class="fa-solid fa-toggle-off me-1"></i> Tidak Aktif';
            }
            // Tampilkan notifikasi sukses
            const alertHtml = '<div class="alert alert-success alert-dismissible fade show shadow-sm fw-bold" role="alert">' +
                '<i class="fa-solid fa-circle-check me-2"></i> ' + data.message +
                '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
            document.querySelector('.container-fluid.px-0').insertAdjacentHTML('afterbegin', alertHtml);
        } else {
            btnElement.innerHTML = originalContent;
        }
    })
    .catch(error => {
        console.error('Toggle status error:', error);
        btnElement.innerHTML = originalContent;
        alert('Gagal mengubah status aset. Silakan coba lagi. (' + error.message + ')');
    })
    .finally(() => {
        btnElement.disabled = false;
    });
}
</script>
@endpush
