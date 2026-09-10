@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.master_data') => '#', __('erp.bc_product') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.product_master_module') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.sku_cogs_db_automation_hint') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('inventory.ledger') }}" class="btn btn-outline-info fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-clipboard-list me-2"></i> Kartu Stok
            </a>

            <form action="{{ route('product.sync_dashboard') }}" method="POST" class="d-inline" onsubmit="return confirm('Sinkronkan data produk dari Dashboard?')">
                @csrf
                <button type="submit" class="btn btn-warning text-dark fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" title="Sinkronkan data produk dari Dashboard">
                    <i class="fa-solid fa-rotate me-1"></i> Sync Dashboard
                </button>
            </form>

            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importProduct">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>

            <a href="{{ route('product.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
        </div>
    </div>

    @if(session('success')) 
        <div class="alert alert-success fw-bold shadow-sm"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}</div> 
    @endif
    @if(session('error')) 
        <div class="alert alert-danger fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}</div> 
    @endif

    <div class="modal fade" id="importProduct" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('product.import') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">{{ __('erp.import_csv_product_master') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-4">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <span><i class="fa-solid fa-info-circle me-1"></i> {{ __('erp.use_jubelio_csv_format') }}</span>
                            <a href="{{ route('product.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> Download Template
                            </a>
                        </div>
                        <span class="text-muted"><i class="fa-solid fa-bolt text-warning"></i> {{ __('erp.import_timeout_tip') }}</span>
                    </div>

                    <label class="fw-bold text-dark">{{ __('erp.choose_csv_product_master') }}</label>
                    <input type="file" name="file_csv" accept=".csv" class="form-control mt-2" required>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">{{ __('erp.start_import') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('product.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.status_category') }}</label>
                <select name="stock_status" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_stock') }}</option>
                    <option value="tersedia" {{ request('stock_status') == 'tersedia' ? 'selected' : '' }}>Stok Tersedia (> 0)</option>
                    <option value="kosong" {{ request('stock_status') == 'kosong' ? 'selected' : '' }}>{{ __('erp.out_of_stock_empty') }}</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-6">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_number_desc') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik SKU, Nama Barang, atau Kategori..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> {{ __('erp.search_btn') }}</button>
                <a href="{{ route('product.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-light text-muted text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">{{ __('erp.sku_item_code') }}</th>
                        <th class="py-3">{{ __('erp.product_name_variant') }}</th>
                        <th class="text-center py-3">{{ __('erp.category') }}</th>
                        <th class="text-end py-3">{{ __('erp.selling_price_rp') }}</th>
                        <th class="text-center pe-4 py-3">{{ __('erp.ending_stock') }}</th>
                        <th class="text-center pe-4 py-3">{{ __('erp.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($products as $p)
                    <tr>
                        <td class="ps-4 fw-bold text-primary">{{ $p->sku }}</td>
                        <td class="fw-medium text-dark text-wrap" style="min-width: 250px;">
                            {{ $p->name }} <br> 
                            <small class="text-muted">{{ $p->variation }}</small>
                        </td>
                        <td class="text-center"><span class="badge bg-secondary">{{ $p->category_name ?? '-' }}</span></td>
                        <td class="text-end fw-bold font-monospace">Rp {{ number_format($p->sell_price, 0, ',', '.') }}</td>
                        <td class="text-center pe-4 fw-bold {{ $p->stock_quantity <= 0 ? 'text-danger' : 'text-success' }}">
                            {{ number_format($p->stock_quantity, 0, ',', '.') }} {{ $p->unit }}
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group">
                                <button type="button" onclick="showEntityLog('{{ $p->sku }}')" class="btn btn-sm btn-outline-info shadow-sm" title="Jejak Log Aktivitas"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                <a href="{{ route('product.edit', $p->id) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                <form action="{{ route('product.destroy', $p->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Hapus barang ini secara permanen?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" style="border-top-left-radius: 0; border-bottom-left-radius: 0;" title="Hapus"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="6" class="text-center py-5 text-muted">
                            <i class="fa-solid fa-box-open fa-2x mb-2 d-block text-secondary"></i>
                            Belum ada data barang. Silakan import CSV Jubelio atau Tambah Manual.
                        </td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $products->links() }}</div>
    </div>
</div>
@endsection
