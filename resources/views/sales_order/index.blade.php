@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_sales') => '#', __('erp.bc_sales_orders') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.sales_orders_module') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.sales_db_accounting_automation_hint') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> {{ __('erp.export_excel_btn') }}
            </button>
            <button type="button" class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0 text-dark" id="btnSyncSOTemp">
                <i class="fa-solid fa-rotate me-2"></i> {{ __('erp.btn_sync_temp') }}
            </button>
            
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importSO">
                <i class="fa-solid fa-file-import me-1"></i> {{ __('erp.import_csv_btn') }}
            </button>

            <a href="{{ route('so.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> {{ __('erp.create_manual_btn') }}
            </a>
        </div>
    </div>

    @if(session('success')) 
        <div class="alert alert-success fw-bold shadow-sm"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}</div> 
    @endif
    @if(session('error')) 
        <div class="alert alert-danger fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}</div> 
    @endif

    <div class="modal fade" id="importSO" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('so.import') }}" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">{{ __('erp.import_csv_sales') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light p-4">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-info-circle me-1"></i> {{ __('erp.use_standard_csv_format') }}</span>
                            <a href="{{ route('so.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> {{ __('erp.download_template_btn') }}
                            </a>
                        </div>
                    </div>

                    <label class="fw-bold text-dark">{{ __('erp.choose_csv_sales') }}</label>
                    <input type="file" name="file_csv" accept=".csv" class="form-control mt-2" required>
                </div>
                <div class="modal-footer bg-white">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                    <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">{{ __('erp.start_sync') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('so.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_short') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_short') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-12 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_number_desc') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="{{ __('erp.search_placeholder') }}" value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> {{ __('erp.search_btn') }}</button>
                <a href="{{ route('so.index') }}" class="btn btn-sm btn-danger fw-bold" title="{{ __('erp.reset_filter') }}"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>


    <ul class="nav nav-pills mb-3 bg-white p-2 rounded border shadow-sm flex-nowrap overflow-auto" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'analisa') ? 'active bg-info text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'analisa']) }}">{{ __('erp.pivot_analysis') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (!isset($tab) || $tab == 'semua') ? 'active' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'semua']) }}">{{ __('erp.all_menu') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'belum_dibayar') ? 'active bg-warning text-dark' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'belum_dibayar']) }}">{{ __('erp.not_yet_paid') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'gagal_download') ? 'active bg-danger text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'gagal_download']) }}">{{ __('erp.download_failed') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'siap_proses') ? 'active bg-info text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'siap_proses']) }}">{{ __('erp.ready_to_process') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'stok_kosong') ? 'active bg-danger text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'stok_kosong']) }}">{{ __('erp.out_of_stock') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'gagal_picking') ? 'active bg-danger text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'gagal_picking']) }}">{{ __('erp.picking_failed') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'request_batal') ? 'active bg-secondary text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'request_batal']) }}">{{ __('erp.cancel_requested') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'batal') ? 'active bg-secondary text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'batal']) }}">{{ __('erp.cancel') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ (isset($tab) && $tab == 'diretur') ? 'active bg-dark text-white' : 'text-muted' }}" href="{{ route('so.index', ['tab' => 'diretur']) }}">{{ __('erp.returned_label') }}</a>
        </li>
    </ul>

    @if(!isset($tab) || $tab !== 'analisa')
    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                <tr>
                    <th class="ps-4 py-3">{{ __('erp.so_date') }}</th>
                    <th class="py-3">{{ __('erp.sales_order_no') }}</th>
                    <th class="py-3">{{ __('erp.invoice_no_short') }}</th>
                    <th class="py-3">{{ __('erp.customer_slash') }}</th>
                    <th class="text-center py-3">{{ __('erp.items_ordered') }}</th>
                    <th class="text-end py-3">{{ __('erp.estimated_total') }}</th>
                    <th class="text-center py-3">{{ __('erp.status') }}</th>
                    <th class="text-end pe-4 py-3">{{ __('erp.action') }}</th>
                </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    <tr>
                        <td class="ps-4 fw-medium">{{ date('d M Y', strtotime($o->transaction_date)) }}</td>
                        <td class="fw-bold text-primary">{{ $o->so_number }}</td>
                        <td class="fw-bold text-success">
                            @if($o->invoice_no)
                                @php
                                    $targetInvId = $o->salesInvoice->id ?? $o->invoice_id;
                                @endphp
                                @if($targetInvId)
                                    <a href="{{ route('invoice.show', $targetInvId) }}" class="text-success text-decoration-none">{{ $o->invoice_no }}</a>
                                @else
                                    <span class="text-success">{{ $o->invoice_no }}</span>
                                @endif
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </td>
                        <td class="fw-bold text-dark">
                            {{ $o->contact_name }} <br>
                            <span class="badge bg-light text-secondary border mt-1"><i class="fa-solid fa-warehouse me-1"></i>{{ $o->location_name ?? 'Pusat' }}</span>
                        </td>
                        <td class="text-center fw-bold">{{ $o->details->count() }} Jenis</td>
                        <td class="text-end fw-bold text-muted">Rp {{ number_format($o->grand_total, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @php
                                // FIX: Jika sudah ada No. Invoice, berarti sudah dikirim (SHIPPED/Faktur)
                                $displayStatus = $o->invoice_no ? 'SHIPPED' : ($o->status ?? 'UNKNOWN');
                                $stColor = match($displayStatus) {
                                    'PAID' => 'bg-info text-dark',
                                    'PROCESSING' => 'bg-primary',
                                    'COMPLETED', 'SHIPPED' => 'bg-success',
                                    'CANCELED', 'REQUEST_CANCEL' => 'bg-danger',
                                    'RETURNED' => 'bg-dark',
                                    'PENDING' => 'bg-warning text-dark',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            <span class="badge {{ $stColor }} px-2 py-1">{{ $displayStatus }}</span>
                        </td>
                        <td class="text-center">
                            @php
                                $wmsColor = match($o->wms_status) {
                                    'CANCELED', 'CANCEL_PICK', 'CANCEL_PACK', 'CANCEL_SHIP', 'REQUEST_CANCEL', 'EMPTY_STOCK' => 'bg-danger',
                                    'COMPLETED', 'SHIPPED' => 'bg-success',
                                    'RETURNED' => 'bg-dark',
                                    'FINISH_PACK', 'READY_TO_SHIP', 'PAID', 'FINISH_PICK' => 'bg-info text-dark',
                                    'PACK', 'PICK', 'PROCESS' => 'bg-primary',
                                    'PENDING', 'READY_TO_PROCESS' => 'bg-warning text-dark',
                                    default => 'bg-secondary'
                                };
                            @endphp
                            @if($o->wms_status)
                                <span class="badge {{ $wmsColor }} px-2 py-1">{{ $o->wms_status }}</span>
                            @else
                                <span class="badge bg-warning text-dark px-2 py-1">{{ $o->wms_status ?: 'APPROVED' }}</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end align-items-center gap-1">
                                @if(!$o->invoice_no)
                                    <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#shipModal{{ $o->id }}">
                                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Faktur
                                    </button>
                                @else
                                    <form action="{{ route('so.rollback', $o->id) }}" method="POST" class="m-0 me-2" onsubmit="return confirm('PERINGATAN: Membatalkan faktur ini akan MENGHAPUS jurnal akuntansi dan MENGEMBALIKAN stok barang ke gudang. Lanjutkan?')">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-danger fw-bold shadow-sm" title="{{ __('erp.void_invoice_btn') }}">
                                            <i class="fa-solid fa-rotate-left me-1"></i> Batal / Void
                                        </button>
                                    </form>
                                @endif

                                <button type="button" onclick="showEntityLog('{{ $o->so_number }}')" class="btn btn-sm btn-outline-info shadow-sm" title="{{ __('erp.activity_log') }}"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                
                                @if(!$o->invoice_no)
                                    <a href="{{ route('so.edit', $o->id) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="{{ __('erp.edit_btn') }}"><i class="fa-solid fa-pen"></i></a>
                                    <form action="{{ route('so.destroy', $o->id) }}" method="POST" class="m-0 d-inline" onsubmit="return confirm('Batalkan dan Hapus SO ini secara permanen?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="{{ __('erp.delete_btn') }}"><i class="fa-solid fa-trash-can"></i></button>
                                    </form>
                                @endif
                            </div>

                            <div class="modal fade text-start" id="shipModal{{ $o->id }}" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <form action="{{ route('so.ship', $o->id) }}" method="POST" class="modal-content border-0 shadow-lg">
                                        @csrf
                                        <div class="modal-header bg-success text-white py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-boxes-packing me-2"></i> {{ __('erp.issue_sales_invoice_actual_ship') }}</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body bg-light p-4">
                                            
                                            <div class="row mb-3 bg-white p-3 rounded border">
                                                <div class="col-md-4">
                                                    <small class="text-muted fw-bold d-block">{{ __('erp.ref_order_so_caps') }}</small>
                                                    <span class="fs-6 fw-bold text-primary">{{ $o->so_number }}</span>
                                                </div>
                                                <div class="col-md-4">
                                                    <small class="text-muted fw-bold d-block">{{ __('erp.customer_caps') }}</small>
                                                    <span class="fs-6 fw-bold text-dark">{{ $o->contact_name }}</span>
                                                </div>
                                                <div class="col-md-4 text-md-end">
                                                    <small class="text-muted fw-bold d-block mb-1">{{ __('erp.actual_shipping_date_caps') }}</small>
                                                    <input type="date" name="ship_date" class="form-control form-control-sm fw-bold d-inline-block w-auto" value="{{ date('Y-m-d') }}" required>
                                                </div>
                                            </div>

                                            <div class="alert alert-warning py-2 small mb-3 border-warning text-dark">
                                                <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> <strong>{{ __('erp.important_colon') }}</strong> Sistem akan menjurnal & memotong stok berdasarkan angka yang Anda input di tabel ini, <strong>{{ __('erp.not_label_caps') }}</strong> berdasarkan pesanan awal. Silakan ubah Qty jika ada selisih, atau tambah barang pengganti jika ada substitusi.
                                            </div>

                                            <div class="table-responsive bg-white border rounded mb-3">
                                                <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;" id="tblFaktur{{ $o->id }}">
                                                    <thead class="table-light text-muted">
                                                        <tr>
                                                            <th class="ps-3 py-2" width="30%">{{ __('erp.product_item_code') }}</th>
                                                            <th class="text-center" width="15%">{{ __('erp.unit_price') }}</th>
                                                            <th class="text-center" width="10%">{{ __('erp.qty_order') }}</th>
                                                            <th class="text-center" width="15%">{{ __('erp.qty_actual') }}</th>
                                                            <th class="text-end pe-3" width="25%">{{ __('erp.subtotal_actual') }}</th>
                                                            <th width="5%"></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="faktur-body">
                                                        @foreach($o->details as $idx => $det)
                                                        <tr class="faktur-row">
                                                            <td class="ps-3">
                                                                <div class="fw-bold">{{ $det->item_code }}</div>
                                                                <small class="text-muted">{{ $det->description }}</small>
                                                                <input type="hidden" name="items[{{ $idx }}][item_code]" value="{{ $det->item_code }}">
                                                                <input type="hidden" name="items[{{ $idx }}][is_substitution]" value="0">
                                                            </td>
                                                            <td class="text-center">
                                                                <input type="number" name="items[{{ $idx }}][price]" class="form-control form-control-sm text-center fw-bold val-price" value="{{ (int)$det->price }}" readonly>
                                                            </td>
                                                            <td class="text-center"><span class="badge bg-secondary">{{ $det->qty }}</span></td>
                                                            <td>
                                                                <input type="number" name="items[{{ $idx }}][qty]" class="form-control form-control-sm text-center fw-bold text-success border-success val-qty trigger-calc" value="{{ $det->qty }}" min="0">
                                                            </td>
                                                            <td class="pe-3">
                                                                <input type="text" class="form-control form-control-sm text-end fw-bold text-primary bg-light val-subtotal" value="0" readonly>
                                                            </td>
                                                            <td></td>
                                                        </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                                <div class="p-2 border-top bg-light">
                                                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="tambahSubstitusi('{{ $o->id }}')">
                                                        <i class="fa-solid fa-plus"></i> Tambah Barang Substitusi
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="row justify-content-end">
                                                <div class="col-md-5 bg-white p-3 rounded border">
                                                    <div class="d-flex justify-content-between mb-2">
                                                        <span class="small fw-bold text-muted">{{ __('erp.subtotal_goods') }}</span>
                                                        <input type="hidden" name="sub_total" class="inp-subtotal" value="0">
                                                        <span class="fw-bold text-dark txt-subtotal">{{ __('erp.rp_zero') }}</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="small fw-bold text-muted">{{ __('erp.discount_combined') }}</span>
                                                        <input type="number" name="disc_amount" class="form-control form-control-sm w-50 text-end trigger-calc inp-disc" value="{{ (int)$o->disc_amount + (int)$o->other_discount }}">
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <span class="small fw-bold text-muted">{{ __('erp.tax_vat_slash') }}</span>
                                                        <input type="number" name="tax_amount" class="form-control form-control-sm w-50 text-end trigger-calc inp-tax" value="{{ (int)$o->tax_amount }}">
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="small fw-bold text-muted">{{ __('erp.shipping_cost') }}</span>
                                                        <input type="number" name="shipping_cost" class="form-control form-control-sm w-50 text-end trigger-calc inp-ship" value="{{ (int)$o->shipping_cost }}">
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="small fw-bold text-muted">{{ __('erp.shipping_discount') }}</span>
                                                        <input type="number" name="shipping_discount" class="form-control form-control-sm w-50 text-end trigger-calc inp-ship-disc" value="{{ (int)$o->shipping_discount }}">
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="small fw-bold text-muted">{{ __('erp.other_costs') }}</span>
                                                        <input type="number" name="other_cost" class="form-control form-control-sm w-50 text-end trigger-calc inp-other-cost" value="{{ (int)$o->other_cost }}">
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                                        <span class="small fw-bold text-muted">{{ __('erp.remaining_return') }}</span>
                                                        <input type="number" name="return_remaining" class="form-control form-control-sm w-50 text-end trigger-calc inp-return" value="{{ (int)$o->return_remaining }}">
                                                    </div>
                                                    <hr class="my-2">
                                                    <div class="d-flex justify-content-between align-items-center">
                                                        <span class="fw-bold text-uppercase text-dark">{{ __('erp.grand_total_actual_caps') }}</span>
                                                        <input type="hidden" name="grand_total" class="inp-grand" value="0">
                                                        <h5 class="fw-bold text-success mb-0 txt-grand">{{ __('erp.rp_zero') }}</h5>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-white">
                                            <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                                            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fa-solid fa-file-invoice me-1"></i> {{ __('erp.issue_invoice_journal') }}</button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="9" class="text-center py-5 text-muted">{{ __('erp.no_sales_tx_detected') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">{{ $orders->links() }}</div>
    </div>
    @endif

    @if(isset($tab) && $tab == 'analisa')
    <div class="card border-0 shadow-sm rounded-3 mb-3">
        <div class="card-header bg-light border-bottom p-3">
            <div class="row g-2 align-items-center">
                <div class="col-md-3">
                    <small class="fw-bold text-muted d-block">{{ __('erp.row_dimension') }}</small>
                    <select id="pivotRow" class="form-select form-select-sm border-primary fw-bold" onchange="renderPivotMatrix()">
                        <option value="lokasi">{{ __('erp.store_origin_warehouse') }}</option>
                        <option value="sku">{{ __('erp.product_code_sku') }}</option>
                        <option value="pelanggan">{{ __('erp.customer_name_label') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <small class="fw-bold text-muted d-block">{{ __('erp.column_dimension') }}</small>
                    <select id="pivotCol" class="form-select form-select-sm border-primary fw-bold" onchange="renderPivotMatrix()">
                        <option value="bulan">{{ __('erp.time_period_month') }}</option>
                        <option value="lokasi">{{ __('erp.store_origin_warehouse') }}</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <small class="fw-bold text-muted d-block">{{ __('erp.value_metric') }}</small>
                    <select id="pivotVal" class="form-select form-select-sm border-primary fw-bold" onchange="renderPivotMatrix()">
                        <option value="total_omset">{{ __('erp.total_sales_revenue_rp') }}</option>
                        <option value="total_qty">{{ __('erp.qty_sold_pcs') }}</option>
                    </select>
                </div>
                <div class="col-md-3 text-md-end pt-3">
                    <span class="badge bg-info text-dark border p-2 small"><i class="fa-solid fa-bolt"></i> {{ __('erp.realtime_aggregated_grid') }}</span>
                </div>
            </div>
        </div>
        
        <div class="card-body bg-white p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle mb-0" id="pivotResultTable" style="font-size: 0.85rem;">
                </table>
            </div>
        </div>
    </div>
    @endif
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let newRowIndex = 900; // Index aman agar tidak nabrak item pesanan asli

    // Database Produk diubah ke format JSON agar bisa dipakai oleh JS (Sangat Cepat)
    const productList = @json($products->map(function($p) {
        return ['sku' => $p->sku, 'name' => $p->name, 'price' => (int)$p->sell_price];
    }));

    function tambahSubstitusi(soId) {
        let optionsHtml = '<option value="">{{ __('erp.select_replacement_product') }}</option>';
        productList.forEach(p => {
            optionsHtml += `<option value="${p.sku}" data-price="${p.price}">${p.sku} - ${p.name}</option>`;
        });

        let rowHtml = `
            <tr class="faktur-row bg-warning bg-opacity-10">
                <td class="ps-3">
                    <select name="items[${newRowIndex}][item_code]" class="form-select select2-subs product-picker" required>
                        ${optionsHtml}
                    </select>
                    <span class="badge bg-warning text-dark mt-1" style="font-size:0.6rem;">{{ __('erp.replacement_item') }}</span>
                    <input type="hidden" name="items[${newRowIndex}][is_substitution]" value="1">
                </td>
                <td class="text-center">
                    <input type="number" name="items[${newRowIndex}][price]" class="form-control form-control-sm text-center fw-bold val-price trigger-calc" value="0" required>
                </td>
                <td class="text-center">-</td>
                <td>
                    <input type="number" name="items[${newRowIndex}][qty]" class="form-control form-control-sm text-center fw-bold text-success border-success val-qty trigger-calc" value="1" min="1" required>
                </td>
                <td class="pe-3">
                    <input type="text" class="form-control form-control-sm text-end fw-bold text-primary bg-light val-subtotal" value="0" readonly>
                </td>
                <td class="text-center">
                    <button type="button" class="btn btn-sm text-danger" onclick="hapusBaris(this, '${soId}')"><i class="fa-solid fa-trash"></i></button>
                </td>
            </tr>
        `;
        
        $(`#tblFaktur${soId} .faktur-body`).append(rowHtml);
        
        // Inisialisasi Ulang Select2 di dalam Modal agar Dropdownnya tidak sembunyi di belakang
        $(`#tblFaktur${soId} .select2-subs`).select2({
            theme: 'bootstrap-5',
            dropdownParent: $(`#shipModal${soId}`)
        });

        newRowIndex++;
        kalkulasiFaktur(soId);
    }

    function hapusBaris(btn, soId) {
        $(btn).closest('tr').remove();
        kalkulasiFaktur(soId);
    }

    function kalkulasiFaktur(soId) {
        let form = $(`#shipModal${soId}`);
        let subtotalBarang = 0;

        // Loop Baris Tabel
        form.find('.faktur-row').each(function() {
            let p = parseFloat($(this).find('.val-price').val()) || 0;
            let q = parseFloat($(this).find('.val-qty').val()) || 0;
            let st = p * q;
            
            $(this).find('.val-subtotal').val(st.toLocaleString('id-ID'));
            subtotalBarang += st;
        });

        let disc = parseFloat(form.find('.inp-disc').val()) || 0;
        let tax = parseFloat(form.find('.inp-tax').val()) || 0;
        let ship = parseFloat(form.find('.inp-ship').val()) || 0;
        let shipDisc = parseFloat(form.find('.inp-ship-disc').val()) || 0;
        let otherCost = parseFloat(form.find('.inp-other-cost').val()) || 0;
        let returnRem = parseFloat(form.find('.inp-return').val()) || 0;

        let grandTotal = (subtotalBarang - disc) + tax + (ship - shipDisc) + otherCost - returnRem;

        // Update Teks Layar
        form.find('.txt-subtotal').text('Rp ' + subtotalBarang.toLocaleString('id-ID'));
        form.find('.txt-grand').text('Rp ' + grandTotal.toLocaleString('id-ID'));

        // Update Hidden Value Controller
        form.find('.inp-subtotal').val(subtotalBarang);
        form.find('.inp-grand').val(grandTotal);
    }

    $(document).ready(function() {
        // Auto-Isi Harga saat barang substitusi dipilih
        $(document).on('change', '.product-picker', function() {
            let harga = $(this).find(':selected').data('price') || 0;
            $(this).closest('tr').find('.val-price').val(harga);
            let soId = $(this).closest('form').attr('action').split('/').pop();
            kalkulasiFaktur(soId);
        });

        // Trigger Kalkulasi jika angka diketik
        $(document).on('input', '.trigger-calc', function() {
            let soId = $(this).closest('form').attr('action').split('/').pop();
            kalkulasiFaktur(soId);
        });

        // Hitung awal untuk semua Modal saat dibuka
        $('.modal').on('shown.bs.modal', function () {
            let soId = $(this).find('form').attr('action').split('/').pop();
            kalkulasiFaktur(soId);
        });

        // ==================== PIVOT ANALYTICS ====================
        const rawData = @json($analyticData ?? []);

        function renderPivotMatrix() {
            const rowDim = document.getElementById('pivotRow').value; 
            const colDim = document.getElementById('pivotCol').value; 
            const valDim = document.getElementById('pivotVal').value; 

            if (rowDim === colDim) {
                alert("Dimensi Baris dan Kolom tidak boleh sama!");
                return;
            }

            let uniqueRows = [...new Set(rawData.map(item => item[rowDim]))].sort();
            let uniqueCols = [...new Set(rawData.map(item => item[colDim]))].sort();

            let table = document.getElementById('pivotResultTable');
            table.innerHTML = ""; 

            let headerHtml = `<thead class="table-secondary text-uppercase fw-bold"><tr>`;
            headerHtml += `<th class="ps-3 py-3" style="width: 25%;">${rowDim.replace('_', ' ')}</th>`;
            
            uniqueCols.forEach(col => {
                headerHtml += `<th class="text-end py-3">${formatColumnHeader(col, colDim)}</th>`;
            });
            headerHtml += `<th class="text-end pe-3 py-3 bg-dark text-white">{{ __('erp.grand_total_caps') }}</th>`;
            headerHtml += `</tr></thead>`;

            let bodyHtml = `<tbody>`;
            let colTotals = {}; 
            uniqueCols.forEach(c => colTotals[c] = 0);
            let absoluteGrandTotal = 0;

            uniqueRows.forEach(rowKey => {
                bodyHtml += `<tr>`;
                bodyHtml += `<td class="ps-3 fw-bold text-dark">${rowKey}</td>`;
                
                let rowGrandTotal = 0;

                uniqueCols.forEach(colKey => {
                    let matchingItems = rawData.filter(item => item[rowDim] === rowKey && item[colDim] === colKey);
                    let value = matchingItems.reduce((sum, item) => sum + parseFloat(item[valDim] || 0), 0);
                    
                    rowGrandTotal += value;
                    colTotals[colKey] += value;

                    bodyHtml += `<td class="text-end fw-medium">${formatValue(value, valDim)}</td>`;
                });

                absoluteGrandTotal += rowGrandTotal;
                bodyHtml += `<td class="text-end fw-bold bg-secondary bg-opacity-10 text-primary">${formatValue(rowGrandTotal, valDim)}</td>`;
                bodyHtml += `</tr>`;
            });

            bodyHtml += `<tr class="table-dark fw-bold border-top border-dark">`;
            bodyHtml += `<td class="ps-3">{{ __('erp.grand_total_overall_caps') }}</td>`;
            uniqueCols.forEach(colKey => {
                bodyHtml += `<td class="text-end">${formatValue(colTotals[colKey], valDim)}</td>`;
            });
            bodyHtml += `<td class="text-end pe-3 text-warning">${formatValue(absoluteGrandTotal, valDim)}</td>`;
            bodyHtml += `</tr>`;
            
            bodyHtml += `</tbody>`;
            
            table.innerHTML = headerHtml + bodyHtml;
        }

        function formatValue(num, type) {
            if (num === 0) return '-';
            if (type === 'total_omset') {
                return 'Rp ' + num.toLocaleString('id-ID');
            }
            return num.toLocaleString('id-ID') + ' Pcs';
        }

        function formatColumnHeader(colVal, colType) {
            if (colType === 'bulan') {
                let split = colVal.split('-');
                if (split.length === 2) {
                    let months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
                    let mIdx = parseInt(split[1]) - 1;
                    return months[mIdx] + ' ' + split[0];
                }
            }
            return colVal;
        }

        // Render otomatis saat tab analisa diklik
        document.addEventListener("DOMContentLoaded", function() {
            const analisaTab = document.querySelector('a[href*="tab=analisa"]');
            if (analisaTab) {
                analisaTab.addEventListener('click', function(e) {
                    e.preventDefault();
                    const url = new URL(this.href);
                    url.searchParams.set('tab', 'analisa');
                    window.location.href = url.toString();
                });
            }
            
            // Render awal jika sudah di tab analisa
            if (document.getElementById('pivotResultTable') && rawData.length > 0) {
                renderPivotMatrix();
            }
        });
        // ==================== END PIVOT ANALYTICS ====================

        $('#btnSyncSOTemp').click(function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> {{ __('erp.syncing') }}');
            
            $.ajax({
                url: "{{ route('so.sync_temp') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: __('erp.alert_sync_started'),
                        text: 'Sinkronisasi SO sedang berjalan di latar belakang server. Anda bisa menutup halaman ini atau lanjut bekerja.',
                        confirmButtonColor: '#3085d6'
                    });
                    btn.prop('disabled', false).html('<i class="fa-solid fa-rotate me-2"></i> {{ __('erp.btn_sync_temp') }} (Background)');
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Terjadi kesalahan saat memulai sinkronisasi SO.',
                        confirmButtonColor: '#d33'
                    });
                    btn.prop('disabled', false).html('<i class="fa-solid fa-rotate me-2"></i> {{ __('erp.btn_sync_temp') }} (Background)');
                }
            });
        });
    });
</script>
@endsection
