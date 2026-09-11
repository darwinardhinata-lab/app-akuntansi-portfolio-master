@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.payment_plan') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.payment_plan_data') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.manage_payment_submission_journal') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <div class="btn-group flex-grow-1 flex-md-grow-0">
                <button type="button" class="btn btn-success fw-bold px-3 shadow-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fa-solid fa-file-export me-1"></i> Export
                </button>
                <ul class="dropdown-menu shadow-sm">
                    <li><h6 class="dropdown-header">{{ __('erp.quick_submitted_only') }}</h6></li>
                    <li><a class="dropdown-item" href="{{ route('payment.export.kasbank') }}"><i class="fa-solid fa-building-columns me-1 text-primary"></i> {{ __('erp.kas_bank_export') }}</a></li>
                    <li><a class="dropdown-item" href="{{ route('payment.export.worklist') }}"><i class="fa-solid fa-list-check me-1 text-warning"></i> {{ __('erp.worklist_manual_ap_dp_deposit') }}</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><h6 class="dropdown-header">{{ __('erp.custom_all_data_filter') }}</h6></li>
                    <li><a class="dropdown-item" href="#" data-target="{{ route('payment.export.kasbank') }}" onclick="openExportModal(event, this)"><i class="fa-solid fa-sliders me-1 text-primary"></i> {{ __('erp.cash_bank_custom_filter') }}</a></li>
                    <li><a class="dropdown-item" href="#" data-target="{{ route('payment.export.worklist') }}" onclick="openExportModal(event, this)"><i class="fa-solid fa-sliders me-1 text-warning"></i> {{ __('erp.worklist_manual_custom_filter') }}</a></li>
                </ul>
            </div>
            <form action="{{ route('payment.post_journal') }}" method="POST" id="form-posting" class="m-0 flex-grow-1 flex-md-grow-0">
                @csrf
                <input type="hidden" name="selected_ids" id="selected_ids">
                <button type="button" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" style="background-color: #198754;" onclick="submitPosting()">
                    <i class="fa-solid fa-book me-1"></i> Posting Jurnal
                </button>
            </form>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>
            <a href="{{ route('payment.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
            <a href="{{ route('payment.public_form') }}" target="_blank" class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-users me-1"></i> Form Karyawan
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
            <strong><i class="fas fa-check-circle"></i> {{ __('erp.success_excl') }}</strong> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
            <strong><i class="fas fa-exclamation-triangle"></i> {{ __('erp.error_occurred_excl') }}</strong> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    <!-- Summary Stat Cards (Ringkasan Total Bulan Berjalan) -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-primary border-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">{{ __('erp.total_submission_this_month') }}</div>
                        <div class="fs-4 fw-bolder text-primary mt-1">Rp {{ number_format($stats['total_nominal_bulan'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="bg-primary bg-opacity-10 text-primary p-3 rounded-circle">
                        <i class="fa-solid fa-calculator fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-success border-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">{{ __('erp.approved_paid_off') }}</div>
                        <div class="fs-4 fw-bolder text-success mt-1">Rp {{ number_format($stats['total_approved'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="bg-success bg-opacity-10 text-success p-3 rounded-circle">
                        <i class="fa-solid fa-circle-check fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-warning border-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">{{ __('erp.pending_approval') }}</div>
                        <div class="fs-4 fw-bolder text-warning mt-1">Rp {{ number_format($stats['total_pending'] ?? 0, 0, ',', '.') }}</div>
                    </div>
                    <div class="bg-warning bg-opacity-10 text-warning p-3 rounded-circle">
                        <i class="fa-solid fa-clock fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 bg-white h-100 border-start border-info border-4 p-3">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-bold text-uppercase">{{ __('erp.total_submission') }}</div>
                        <div class="fs-4 fw-bolder text-info mt-1">{{ number_format($stats['total_count'] ?? 0, 0, ',', '.') }} Transaksi</div>
                    </div>
                    <div class="bg-info bg-opacity-10 text-info p-3 rounded-circle">
                        <i class="fa-solid fa-receipt fa-xl"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('payment.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_short') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_short') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.division_label') }}</label>
                <select name="id_divisi" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_divisions') }}</option>
                    @foreach($master_divisi ?? [] as $div)
                        <option value="{{ $div->id_divisi }}" {{ request('id_divisi') == $div->id_divisi ? 'selected' : '' }}>{{ $div->nama_divisi }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.category') }}</label>
                <select name="kategori_payment" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_categories') }}</option>
                    @foreach($payment_categories as $cat)
                        <option value="{{ $cat->name }}" {{ request('kategori_payment') == $cat->name ? 'selected' : '' }}>{{ $cat->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-sm-6 col-md-1">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.status') }}</label>
                <select name="status_payment" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_menu') }}</option>
                    <option value="PENGAJUAN" {{ request('status_payment') == 'PENGAJUAN' ? 'selected' : '' }}>{{ __('erp.status_submitted') }}</option>
                    <option value="APPROVED" {{ request('status_payment') == 'APPROVED' ? 'selected' : '' }}>{{ __('erp.status_approved') }}</option>
                    <option value="REJECTED" {{ request('status_payment') == 'REJECTED' ? 'selected' : '' }}>{{ __('erp.status_rejected') }}</option>
                    <option value="PAID" {{ request('status_payment') == 'PAID' ? 'selected' : '' }}>{{ __('erp.status_paid') }}</option>
                    <option value="POSTED" {{ request('status_payment') == 'POSTED' ? 'selected' : '' }}>{{ __('erp.status_posted') }}</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_label') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('erp.search_vendor') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-1 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i></button>
                <a href="{{ route('payment.index') }}" class="btn btn-sm btn-danger fw-bold" title="{{ __('erp.reset_filter') }}"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle sticky-top">
                        <tr>
                            <th width="3%">
                                <input type="checkbox" id="checkAll" style="transform: scale(1.1); cursor: pointer;">
                            </th>
                            <th width="3%"></th>
                            <th>{{ __('erp.no_pp_caps') }}</th>
                            <th>{{ __('erp.date') }}</th>
                            <th>{{ __('erp.payment_category') }}</th>
                            <th>{{ __('erp.ops_account') }}</th>
                            <th style="min-width: 140px;">{{ __('erp.payment_status_label') }}</th>
                            <th>{{ __('erp.name_pic') }}</th>
                            <th>{{ __('erp.division_label') }}</th>
                            <th>{{ __('erp.supplier_store') }}</th>
                            <th>{{ __('erp.store_name_link') }}</th>
                            <th style="min-width: 220px; white-space: normal;">{{ __('erp.description') }}</th>
                            <th>{{ __('erp.va_account_payment_code') }}</th>
                            <th style="min-width: 200px;">{{ __('erp.account_detail') }}</th>
                            <th>{{ __('erp.item_label') }}</th>
                            <th class="text-end">{{ __('erp.submission_rp') }}</th>
                            <th class="text-end">{{ __('erp.actual_rp') }}</th>
                            <th class="text-end">{{ __('erp.total_rp') }}</th>
                            <th>{{ __('erp.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($payment_plans))
                            @forelse ($payment_plans as $item)
                            @php $details = $item->details ?? collect(); @endphp
                            <tr>
                                <td class="text-center py-2">
                                    <input type="checkbox" class="checkItem" value="{{ $item->no_transaksi }}" style="transform: scale(1.1); cursor: pointer;">
                                </td>
                                <td class="text-center py-2">
                                    @if($details->count() > 0)
                                    <button type="button" class="btn btn-sm btn-outline-secondary p-1" style="width: 26px; height: 26px;" data-bs-toggle="collapse" data-bs-target="#detail-{{ $item->id_payment }}" aria-expanded="false" title="{{ __('erp.view_item_details') }}">
                                        <i class="fa-solid fa-chevron-down"></i>
                                    </button>
                                    @endif
                                </td>
                                <td class="text-center fw-bold text-primary py-2">{{ $item->no_transaksi }}</td>
                                <td class="text-center py-2">{{ date('d/m/Y', strtotime($item->tgl_pengajuan)) }}</td>
                                <td class="text-center py-2"><span class="badge bg-secondary text-white w-100 py-1">{{ $item->kategori_payment }}</span></td>
                                <td class="py-2 text-center">
                                    <form action="{{ route('payment.set_rekening', $item->id_payment) }}" method="POST" class="m-0">
                                        @csrf
                                        <select name="jenis_transaksi" style="font-size: 12.5px; min-width: 140px;" class="form-select form-select-sm {{ $item->jenis_transaksi != 'PENDING' ? 'border-info text-primary fw-bold' : 'border-danger animate-pulse' }}" onchange="this.form.submit()" required>
                                            <option value="PENDING" {{ $item->jenis_transaksi == 'PENDING' ? 'selected' : '' }}>{{ __('erp.finance_not_set') }}</option>
                                            <option value="BCA BBW OPS" {{ $item->jenis_transaksi == 'BCA BBW OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_bbw_ops') }}</option>
                                            <option value="BCA BBB OPS" {{ $item->jenis_transaksi == 'BCA BBB OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_bbb_ops') }}</option>
                                            <option value="BCA KOI OPS" {{ $item->jenis_transaksi == 'BCA KOI OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_koi_ops') }}</option>
                                            <option value="BCA GBB OPS" {{ $item->jenis_transaksi == 'BCA GBB OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_gbb_ops') }}</option>
                                            <option value="MANDIRI BBW" {{ $item->jenis_transaksi == 'MANDIRI BBW' ? 'selected' : '' }}>{{ __('erp.bank_mandiri_bbw') }}</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="py-2 text-center">
                                    <form action="{{ route('payment.update_status', $item->id_payment) }}" method="POST" class="m-0">
                                        @csrf
                                        <select name="status_payment" style="font-size: 12.5px;" class="form-select form-select-sm fw-bold text-center {{ $item->status_payment == 'POSTED' ? 'bg-info text-white' : ($item->status_payment == 'PAID' ? 'bg-success text-white' : ($item->status_payment == 'APPROVED' ? 'bg-primary text-white' : ($item->status_payment == 'REJECTED' ? 'bg-danger text-white' : 'bg-warning text-dark'))) }}" onchange="this.form.submit()">
                                            <option value="PENGAJUAN" {{ $item->status_payment == 'PENGAJUAN' ? 'selected' : '' }}>{{ __('erp.status_submitted') }}</option>
                                            <option value="APPROVED" {{ $item->status_payment == 'APPROVED' ? 'selected' : '' }}>{{ __('erp.status_approved') }}</option>
                                            <option value="REJECTED" {{ $item->status_payment == 'REJECTED' ? 'selected' : '' }}>{{ __('erp.status_rejected') }}</option>
                                            <option value="PAID" {{ $item->status_payment == 'PAID' ? 'selected' : '' }}>{{ __('erp.status_paid') }}</option>
                                            <option value="POSTED" {{ $item->status_payment == 'POSTED' ? 'selected' : '' }}>{{ __('erp.status_posted') }}</option>
                                        </select>
                                    </form>
                                </td>
                                <td class="py-2">{{ $item->penerima_pj }}</td>
                                <td class="text-center py-2">{{ $item->divisi->nama_divisi ?? '-' }}</td>
                                <td class="py-2 fw-bold text-dark">{{ $item->vendor_toko }}</td>
                                <td class="py-2">{{ $item->nama_toko_link ?? '-' }}</td>
                                <td class="py-2 text-wrap" style="white-space: normal;">@linkify($item->keterangan)</td>
                                <td class="py-2">{{ $item->rekening_va ?? '-' }}</td>
                                <td class="py-2 text-center">
                                    <form action="{{ route('payment.set_coa', $item->id_payment) }}" method="POST" class="m-0 coa-searchable-form">
                                        @csrf
                                        <div class="coa-searchable-dropdown position-relative">
                                            <input type="hidden" name="id_akun" value="{{ $item->id_akun ?? '' }}">
                                            @php
                                                $displayValue = '';
                                                if ($item->id_akun) {
                                                    if ($item->account) {
                                                        $displayValue = $item->account->account_code . ' - ' . $item->account->account_name;
                                                    } else {
                                                        $displayValue = $item->id_akun;
                                                    }
                                                }
                                            @endphp
                                            <input type="text" class="form-control form-control-sm coa-search-input {{ $item->id_akun ? 'border-success text-success fw-bold' : 'border-danger' }}" style="font-size: 12.5px;" placeholder="{{ __('erp.search_coa') }}" value="{{ $displayValue }}" {{ $item->status_payment == 'POSTED' ? 'disabled' : '' }} autocomplete="off">
                                            <div class="coa-dropdown-menu position-absolute w-100 bg-white border rounded shadow-sm text-start" style="top: 100%; left: 0; z-index: 1050; max-height: 220px; overflow-y: auto; display: none; margin-top: 2px;">
                                                <div class="coa-options-container"></div>
                                            </div>
                                        </div>
                                    </form>
                                </td>
                                <td class="text-center py-2">
                                    @if($details->count() > 0)
                                        <span class="badge bg-primary-subtle text-primary border border-primary fw-bold">{{ $details->count() }} item</span>
                                    @else
                                        <span class="badge bg-light text-muted border">-</span>
                                    @endif
                                </td>
                                <td class="text-end fw-bold py-2">{{ number_format($item->nominal, 0, ',', '.') }}</td>
                                <td class="text-end fw-bold py-2 {{ $item->nominal_aktual !== null && (float) $item->nominal_aktual != (float) $item->nominal ? 'text-danger' : 'text-muted' }}">
                                    {{ $item->nominal_aktual !== null ? number_format($item->nominal_aktual, 0, ',', '.') : '-' }}
                                </td>
                                <td class="text-end fw-bold text-success py-2">{{ number_format($item->nominal_aktual_efektif, 0, ',', '.') }}</td>
                                <td class="text-center py-2">
                                    <div class="btn-group" role="group">
                                        <button type="button" onclick="showEntityLog('{{ $item->no_transaksi }}')" class="btn btn-sm btn-outline-secondary" title="{{ __('erp.activity_log') }}">
                                            <i class="fa-solid fa-clock-rotate-left"></i>
                                        </button>
                                        @if(!empty($item->bukti_file))
                                            <a href="{{ $item->bukti_file }}" target="_blank" class="btn btn-sm btn-outline-info text-dark" title="{{ __('erp.view_receipt_proof') }}">
                                                <i class="fas fa-paperclip"></i>
                                            </a>
                                        @endif
                                        <a href="{{ route('payment.edit', $item->id_payment) }}" class="btn btn-sm btn-outline-warning" title="{{ __('erp.edit_data') }}">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form action="{{ route('payment.destroy', $item->id_payment) }}" method="POST" class="d-inline" onsubmit="return confirm('Yakin ingin menghapus pengajuan Payment Plan ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('erp.delete_data') }}">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @if($details->count() > 0)
                            <tr class="collapse" id="detail-{{ $item->id_payment }}">
                                <td colspan="19" class="p-0 border-0">
                                    <div class="bg-light-subtle p-3 border-top border-bottom">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-bordered bg-white mb-0" style="font-size: 12.5px;">
                                                <thead class="table-light text-center text-muted">
                                                    <tr>
                                                        <th style="min-width: 160px;">{{ __('erp.item_name') }}</th>
                                                        <th style="min-width: 220px; white-space: normal;">{{ __('erp.description') }}</th>
                                                        <th>{{ __('erp.qty') }}</th>
                                                        <th>{{ __('erp.unit') }}</th>
                                                        <th class="text-end">{{ __('erp.unit_price_rp') }}</th>
                                                        <th class="text-end">{{ __('erp.amount_rp') }}</th>
                                                        <th class="text-end">{{ __('erp.actual_amount_rp') }}</th>
                                                        <th>{{ __('erp.proof_label') }}</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    @foreach($details as $d)
                                                    <tr>
                                                        <td class="fw-bold">{{ $d->nama_item ?? '-' }}</td>
                                                        <td class="text-wrap" style="white-space: normal;">{{ $d->keterangan }}</td>
                                                        <td class="text-center">{{ rtrim(rtrim(number_format($d->qty ?? 1, 2, ',', '.'), '0'), ',') }}</td>
                                                        <td class="text-center">{{ $d->satuan ?? 'Pcs' }}</td>
                                                        <td class="text-end">{{ $d->harga_satuan !== null ? number_format($d->harga_satuan, 0, ',', '.') : '-' }}</td>
                                                        <td class="text-end fw-bold">{{ number_format($d->nominal, 0, ',', '.') }}</td>
                                                        <td class="text-end {{ $d->nominal_aktual !== null && (float) $d->nominal_aktual != (float) $d->nominal ? 'text-danger fw-bold' : 'text-muted' }}">
                                                            {{ $d->nominal_aktual !== null ? number_format($d->nominal_aktual, 0, ',', '.') : '-' }}
                                                        </td>
                                                        <td class="text-center">
                                                            @if($d->bukti_file)
                                                                <a href="{{ $d->bukti_file }}" target="_blank" title="{{ __('erp.view_item_proof') }}">
                                                                    <i class="fa-solid fa-paperclip text-primary"></i>
                                                                </a>
                                                            @else
                                                                -
                                                            @endif
                                                        </td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr class="table-light">
                                                        <td colspan="5" class="text-end fw-bold">{{ __('erp.total_caps') }}</td>
                                                        <td class="text-end fw-bold text-primary">{{ number_format($details->sum('nominal'), 0, ',', '.') }}</td>
                                                        <td class="text-end fw-bold text-success">{{ number_format($details->sum(fn($d) => $d->nominal_aktual ?? $d->nominal), 0, ',', '.') }}</td>
                                                        <td></td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            @endif
                            @empty
                            <tr>
                                <td colspan="19" class="text-center py-5 text-muted">
                                    <i class="fas fa-folder-open fa-3x mb-3 text-light"></i><br>
                                    Belum ada data pengajuan Payment Plan.
                                </td>
                            </tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
        </div>
        
        @if(isset($payment_plans) && $payment_plans->hasPages())
        <div class="card-footer bg-white d-flex justify-content-center p-3 border-top-0">
            {{ $payment_plans->links('pagination::bootstrap-4') }}
        </div>
        @endif
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('payment.import') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">{{ __('erp.import_csv_payment_plan') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-4">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-info-circle me-1"></i> {{ __('erp.column_structure_must_match_template') }}</span>
                            <a href="{{ route('payment.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <label class="fw-bold text-dark">{{ __('erp.choose_csv_payment_plan') }}</label>
                    <input type="file" name="file_csv" accept=".csv" class="form-control mt-2" required>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">{{ __('erp.start_import') }}</button>
                </div>
            </form>
        </div>
    </div>

<div class="modal fade" id="modalExportCustom" tabindex="-1">
    <div class="modal-dialog">
        <form id="formExportCustom" method="GET" class="modal-content border-0 shadow-lg" target="_blank">
            <input type="hidden" name="all" value="1">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">{{ __('erp.export_custom_all_filter') }}</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body bg-light p-4">
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label small fw-bold">{{ __('erp.start_date_short') }}</label>
                        <input type="date" name="start_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">{{ __('erp.end_date_short') }}</label>
                        <input type="date" name="end_date" class="form-control form-control-sm">
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">{{ __('erp.division_label') }}</label>
                        <select name="id_divisi" class="form-select form-select-sm">
                            <option value="">{{ __('erp.all_divisions') }}</option>
                            @foreach($master_divisi ?? [] as $div)
                                <option value="{{ $div->id_divisi }}">{{ $div->nama_divisi }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-6">
                        <label class="form-label small fw-bold">{{ __('erp.status') }}</label>
                        <select name="status_payment" class="form-select form-select-sm">
                            <option value="">{{ __('erp.all_status') }}</option>
                            <option value="PENGAJUAN">{{ __('erp.status_submitted') }}</option>
                            <option value="APPROVED">{{ __('erp.status_approved') }}</option>
                            <option value="REJECTED">{{ __('erp.status_rejected') }}</option>
                            <option value="PAID">{{ __('erp.status_paid') }}</option>
                            <option value="POSTED">{{ __('erp.status_posted') }}</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label small fw-bold">{{ __('erp.payment_category') }}</label>
                        <select name="kategori_payment" class="form-select form-select-sm">
                            <option value="">{{ __('erp.all_categories') }}</option>
                            @foreach($payment_categories as $cat)
                                <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-white">
                <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">{{ __('erp.export_btn') }}</button>
            </div>
        </form>
    </div>
</div>

<style>
    .coa-searchable-dropdown .coa-option:hover, .coa-searchable-dropdown .coa-option.active {
        background-color: #0d6efd;
        color: #ffffff;
    }
    .coa-searchable-dropdown .coa-option {
        border-bottom: 1px solid #f1f5f9;
    }
    .coa-searchable-dropdown .coa-option:last-child {
        border-bottom: none;
    }
    .coa-searchable-dropdown .coa-search-highlight {
        font-weight: 700;
        text-decoration: underline;
    }
    #detail-row-icon-rotate[aria-expanded="true"] i {
        transform: rotate(180deg);
    }
    tr[data-bs-toggle="collapse"] i.fa-chevron-down,
    button[data-bs-toggle="collapse"] i.fa-chevron-down {
        transition: transform .2s ease;
    }
    button[data-bs-toggle="collapse"][aria-expanded="true"] i.fa-chevron-down {
        transform: rotate(180deg);
    }
</style>

<script>
    document.getElementById('checkAll').addEventListener('change', function(e) {
        let checkboxes = document.querySelectorAll('.checkItem');
        checkboxes.forEach(function(checkbox) { checkbox.checked = e.target.checked; });
    });

    function openExportModal(e, el) {
        e.preventDefault();
        document.getElementById('formExportCustom').action = el.getAttribute('data-target');
        new bootstrap.Modal(document.getElementById('modalExportCustom')).show();
    }

    function submitPosting() {
        let checked = [];
        let checkboxes = document.querySelectorAll('.checkItem:checked');
        checkboxes.forEach(function(el) { checked.push(el.value); });

        if (checked.length === 0) {
            alert('Silakan centang minimal satu data Payment Plan untuk diposting!');
            return;
        }

        if (confirm('Yakin ingin memposting ' + checked.length + ' data ke Jurnal Umum Akuntansi?')) {
            document.getElementById('selected_ids').value = checked.join(',');
            document.getElementById('form-posting').submit();
        }
    }

    // COA Searchable Dropdown
    document.addEventListener('DOMContentLoaded', function() {
        const forms = document.querySelectorAll('.coa-searchable-form');
        let debounceTimer = null;

        forms.forEach(function(form) {
            const searchInput = form.querySelector('.coa-search-input');
            const dropdownMenu = form.querySelector('.coa-dropdown-menu');
            const optionsContainer = form.querySelector('.coa-options-container');
            const hiddenInput = form.querySelector('input[name="id_akun"]');
            
            if (!searchInput || !dropdownMenu || !hiddenInput || !optionsContainer) return;

            let currentOptions = [];
            let isLoading = false;

            function openDropdown() {
                dropdownMenu.style.display = 'block';
                if (currentOptions.length === 0 && !hiddenInput.value) {
                    fetchAccounts('');
                }
            }

            function closeDropdown() {
                dropdownMenu.style.display = 'none';
            }

            function fetchAccounts(query) {
                if (isLoading) return;
                isLoading = true;
                
                fetch("{{ route('payment.api_accounts') }}" + "?q=" + encodeURIComponent(query))
                    .then(response => response.json())
                    .then(data => {
                        isLoading = false;
                        renderOptions(data);
                    })
                    .catch(() => {
                        isLoading = false;
                        optionsContainer.innerHTML = '<div class="px-2 py-1 small text-muted">{{ __('erp.failed_load_data') }}</div>';
                    });
            }

            function renderOptions(accounts) {
                currentOptions = accounts;
                optionsContainer.innerHTML = '';
                
                if (accounts.length === 0 && !hiddenInput.value) {
                    optionsContainer.innerHTML = '<div class="px-2 py-1 small text-muted">{{ __('erp.coa_not_found') }}</div>';
                    return;
                }
                
                accounts.forEach(function(akun) {
                    const option = document.createElement('div');
                    option.className = 'coa-option px-2 py-1.5 small';
                    option.style.cursor = 'pointer';
                    const text = akun.account_code + ' - ' + akun.account_name;
                    option.setAttribute('data-value', akun.account_code);
                    option.setAttribute('data-text', text);
                    option.innerHTML = '<span class="fw-bold">' + akun.account_code + '</span> - ' + akun.account_name;
                    optionsContainer.appendChild(option);
                });

                // Re-attach click handlers
                optionsContainer.querySelectorAll('.coa-option').forEach(function(option) {
                    option.addEventListener('click', function(e) {
                        e.preventDefault();
                        e.stopPropagation();
                        const value = this.getAttribute('data-value');
                        const text = this.getAttribute('data-text');
                        
                        searchInput.value = text;
                        hiddenInput.value = value;
                        
                        searchInput.classList.remove('border-danger');
                        searchInput.classList.add('border-success', 'text-success', 'fw-bold');
                        
                        closeDropdown();
                        form.submit();
                    });
                });
            }

            searchInput.addEventListener('focus', function() {
                openDropdown();
            });

            searchInput.addEventListener('input', function() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(function() {
                    openDropdown();
                    fetchAccounts(searchInput.value);
                }, 300);
            });

            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });

            document.addEventListener('click', function(e) {
                if (!searchInput.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    closeDropdown();
                }
            });

            if (hiddenInput.value && searchInput.value) {
                searchInput.classList.remove('border-danger');
                searchInput.classList.add('border-success', 'text-success', 'fw-bold');
            }
        });
    });
</script>
@endsection
