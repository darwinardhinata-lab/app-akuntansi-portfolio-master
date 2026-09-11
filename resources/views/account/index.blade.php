@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_account_list_coa') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.bc_account_list_coa') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.master_coa_hint') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" formaction="{{ route('account.export') }}" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>

            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importModalCOA">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>

            <a href="{{ route('account.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
            
            <a href="{{ route('account.opening_balance') }}" class="btn btn-outline-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-scale-balanced me-2"></i> Setup Saldo Awal
            </a>
        </div>
    </div>

    <div class="modal fade" id="importModalCOA" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('account.import') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">{{ __('erp.import_excel_csv_coa') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-4">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-info-circle me-1"></i> {{ __('erp.column_arrangement_must_follow_template') }}</span>
                            <a href="{{ route('account.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <label class="fw-bold text-dark">{{ __('erp.choose_excel_file') }}</label>
                    <input type="file" name="file_excel" accept=".csv, .xls, .xlsx" class="form-control mt-2" required>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">{{ __('erp.start_import') }}</button>
                </div>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('account.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.report_position') }}</label>
                <select name="report_pos" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_reports') }}</option>
                    <option value="NERACA" {{ request('report_pos') == 'NERACA' ? 'selected' : '' }}>{{ __('erp.balance_sheet') }}</option>
                    <option value="LABA RUGI" {{ request('report_pos') == 'LABA RUGI' ? 'selected' : '' }}>{{ __('erp.profit_loss') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.coa_type') }}</label>
                <select name="coa_type" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_types') }}</option>
                    <option value="Cash & Bank" {{ request('coa_type') == 'Cash & Bank' ? 'selected' : '' }}>{{ __('erp.cash_and_bank') }}</option>
                    <option value="Piutang Dagang" {{ request('coa_type') == 'Piutang Dagang' ? 'selected' : '' }}>{{ __('erp.trade_receivables') }}</option>
                    <option value="Persediaan" {{ request('coa_type') == 'Persediaan' ? 'selected' : '' }}>{{ __('erp.inventory_label') }}</option>
                    <option value="Aset Tetap" {{ request('coa_type') == 'Aset Tetap' ? 'selected' : '' }}>{{ __('erp.fixed_asset') }}</option>
                    <option value="Hutang Dagang" {{ request('coa_type') == 'Hutang Dagang' ? 'selected' : '' }}>{{ __('erp.trade_payables') }}</option>
                    <option value="Modal" {{ request('coa_type') == 'Modal' ? 'selected' : '' }}>{{ __('erp.equity_label') }}</option>
                    <option value="Pendapatan" {{ request('coa_type') == 'Pendapatan' ? 'selected' : '' }}>{{ __('erp.revenue_label') }}</option>
                    <option value="Harga Pokok Penjualan" {{ request('coa_type') == 'Harga Pokok Penjualan' ? 'selected' : '' }}>{{ __('erp.cogs_label') }}</option>
                    <option value="Biaya" {{ request('coa_type') == 'Biaya' ? 'selected' : '' }}>{{ __('erp.expense_label') }}</option>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_account') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('erp.search_account') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> {{ __('erp.search_btn') }}</button>
                <a href="{{ route('account.index') }}" class="btn btn-sm btn-danger fw-bold" title="{{ __('erp.reset_filter') }}"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="table-responsive bg-white">
            <table class="table align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-uppercase text-muted" style="font-size: 0.75rem; letter-spacing: 0.5px;">
                    <tr>
                        <th width="12%" class="ps-4">{{ __('erp.account_code') }}</th>
                        <th width="35%">{{ __('erp.account') }}</th>
                        <th width="20%">{{ __('erp.coa_type') }}</th>
                        <th width="13%" class="text-center">{{ __('erp.balance_position') }}</th>
                        <th width="12%" class="text-center">{{ __('erp.report_position') }}</th>
                        <th width="8%" class="text-center pe-4">{{ __('erp.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @if(isset($accounts))
                        @forelse($accounts as $acc)
                            <tr>
                                <td class="ps-4 fw-bold text-primary">{{ $acc->account_code }}</td>
                                <td class="fw-medium text-dark">{{ $acc->account_name }}</td>
                                <td>
                                    <span class="badge bg-light text-secondary border px-2 py-1">{{ $acc->coa_type }}</span>
                                </td>
                                <td class="text-center">
                                    <span class="badge {{ $acc->normal_balance == 'DEBET' ? 'bg-success' : 'bg-danger' }} px-2 py-1">
                                        {{ $acc->normal_balance }}
                                    </span>
                                </td>
                                <td class="text-center fw-bold text-muted small">
                                    {{ $acc->report_pos }}
                                </td>
                                <td class="text-center pe-4">
                                    <div class="btn-group flex-nowrap">
                                        <a href="{{ route('account.edit', $acc->account_code ?? $acc->id) }}" class="btn btn-sm btn-outline-primary" title="{{ __('erp.edit_btn') }}">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form action="{{ route('account.destroy', $acc->account_code ?? $acc->id) }}" method="POST" onsubmit="return confirm('Hapus akun ini secara permanen?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('erp.delete_btn') }}">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    Tidak ada master data akun yang ditemukan.
                                </td>
                            </tr>
                        @endforelse
                    @endif
                </tbody>
            </table>
        </div>
        
        @if(isset($accounts))
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">
            {{ $accounts->links() }}
        </div>
        @endif
    </div>

</div>
@endsection
