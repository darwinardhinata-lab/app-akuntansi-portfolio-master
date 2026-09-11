@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_purchase_orders') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.purchase_orders_module') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.purchase_order_list_hint') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importPO">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>

            <a href="{{ route('po.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
            
            <form action="{{ route('po.sync_temp') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" onclick="return confirm('Mulai sinkronisasi PO dari Dashboard? Proses ini berjalan di background.')">
                    <i class="fa-solid fa-sync me-1"></i> Sync Dashboard
                </button>
            </form>
        </div>
    </div>

    @if(session('success')) 
        <div class="alert alert-success fw-bold shadow-sm"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}</div> 
    @endif
    
    @if(session('error')) 
        <div class="alert alert-danger fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}</div> 
    @endif

    <div class="modal fade" id="importPO" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('po.import') }}" method="POST" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">{{ __('erp.import_csv_purchasing') }}</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-info-circle me-1"></i> {{ __('erp.use_standard_csv_format') }}</span>
                            <a href="{{ route('po.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <label class="fw-bold">{{ __('erp.csv_po_details_file') }}</label>
                    <input type="file" name="file_csv" accept=".csv" class="form-control mt-2" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary fw-bold w-100">{{ __('erp.upload_sync') }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('po.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_short') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_short') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.status_category') }}</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">{{ __('erp.all_status') }}</option>
                    <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>{{ __('erp.approved_pending') }}</option>
                    <option value="PARTIAL" {{ request('status') == 'PARTIAL' ? 'selected' : '' }}>{{ __('erp.partial_paren') }}</option>
                    <option value="RECEIVED" {{ request('status') == 'RECEIVED' ? 'selected' : '' }}>{{ __('erp.received_complete') }}</option>
                </select>
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
                <a href="{{ route('po.index') }}" class="btn btn-sm btn-danger fw-bold" title="{{ __('erp.reset_filter') }}"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">{{ __('erp.po_date') }}</th>
                        <th class="py-3">{{ __('erp.purchase_no') }}</th>
                        <th class="py-3">{{ __('erp.supplier_vendor') }}</th>
                        <th class="text-center py-3">{{ __('erp.total_item') }}</th>
                        <th class="text-end py-3">{{ __('erp.grand_total_rp') }}</th>
                        <th class="text-center py-3">{{ __('erp.status') }}</th>
                        <th class="text-end pe-4 py-3">{{ __('erp.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    @php
                        // FIX N+1: cek via array yang sudah di-preload di Controller
                        $noTransaksiPP = str_replace('PO-', '', $o->po_number);
                        $isUangMuka = isset($uangMukaSet[$noTransaksiPP]);
                        $kreditAkun = $isUangMuka ? 'Uang Muka Pembelian (11305)' : 'Hutang Dagang (22000)';
                    @endphp
                    <tr>
                        <td class="ps-4 fw-medium">{{ date('d M Y', strtotime($o->transaction_date)) }}</td>
                        <td class="fw-bold text-primary">
                            {{ $o->po_number }}
                            @if($isUangMuka)
                                <i class="fa-solid fa-money-check-dollar text-success ms-1" title="{{ __('erp.connected_payment_plan') }}"></i>
                            @endif
                        </td>
                        <td class="fw-bold text-dark">
                            {{ $o->contact_name }} <br>
                            <span class="badge bg-info text-dark mt-1">{{ $o->location_name }}</span>
                        </td>
                        <td class="text-center fw-bold">{{ $o->details->count() }} Jenis</td>
                        <td class="text-end fw-bold text-success">Rp {{ number_format($o->grand_total, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($o->status == 'RECEIVED')
                                <span class="badge bg-success px-2 py-1">{{ __('erp.status_received') }}</span>
                            @elseif($o->status == 'PARTIAL')
                                <span class="badge bg-info text-dark px-2 py-1">{{ __('erp.status_partial') }}</span>
                            @else
                                <span class="badge bg-warning text-dark px-2 py-1">{{ __('erp.status_approved') }}</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end align-items-center gap-1">
                                @if($o->status != 'RECEIVED')
                                    <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#receiveModal{{ $o->id }}">
                                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Terima / Tagihan
                                    </button>
                                @endif

                                <button type="button" onclick="showEntityLog('{{ $o->po_number }}')" class="btn btn-sm btn-outline-info shadow-sm" title="{{ __('erp.activity_log') }}"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                
                                <a href="{{ route('po.edit', $o->id) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="{{ __('erp.edit_po') }}"><i class="fa-solid fa-pen"></i></a>
                                <form action="{{ route('po.destroy', $o->id) }}" method="POST" class="m-0 d-inline" onsubmit="return confirm('Batalkan dan Hapus PO ini secara permanen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="{{ __('erp.delete_po') }}"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>

                            @if($o->status != 'RECEIVED')
                            <div class="modal fade text-start" id="receiveModal{{ $o->id }}" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <form action="{{ route('po.receive', $o->id) }}" method="POST" class="modal-content border-0 shadow-lg">
                                        @csrf
                                        <div class="modal-header bg-success text-white py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i> {{ __('erp.create_bill_receive_goods') }}</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body bg-light p-4">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <small class="text-muted fw-bold text-uppercase d-block">{{ __('erp.purchase_number') }}</small>
                                                    <span class="fs-6 fw-bold text-primary">{{ $o->po_number }}</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted fw-bold text-uppercase d-block">{{ __('erp.supplier_bill_no_required') }}</small>
                                                    <input type="text" name="bill_number" class="form-control form-control-sm fw-bold" placeholder="{{ __('erp.eg_supplier_inv') }}" required>
                                                </div>
                                                <div class="col-md-3 text-md-end">
                                                    <small class="text-muted fw-bold text-uppercase d-block">{{ __('erp.bill_date_due_date') }}</small>
                                                    <div class="d-flex gap-1 justify-content-end">
                                                        <input type="date" name="receive_date" class="form-control form-control-sm fw-bold w-auto" value="{{ date('Y-m-d') }}" title="{{ __('erp.bill_date') }}" required>
                                                        <input type="date" name="due_date" class="form-control form-control-sm fw-bold w-auto" title="{{ __('erp.due_date_optional') }}">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-info py-2 small mb-3 border-info">
                                                <i class="fa-solid fa-info-circle me-1"></i> {{ __('erp.system_detects_as_transaction') }} <strong>{{ $isUangMuka ? 'UANG MUKA' : 'HUTANG DAGANG' }}</strong>{{ __('erp.journal_auto_created_colon') }}<br>
                                                <span class="text-success fw-bold">{{ __('erp.debit_colon') }}</span> {{ __('erp.inventory_goods_11200') }}<br>
                                                <span class="text-danger fw-bold">{{ __('erp.credit_colon') }}</span> {{ $kreditAkun }}
                                            </div>

                                            <div class="alert alert-warning py-2 small mb-3 border-warning text-dark">
                                                <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> <strong>{{ __('erp.important_colon') }}</strong> {{ __('erp.fill_column') }} <b>{{ __('erp.receive_now_short') }}</b> sesuai dengan barang fisik yang lolos QC/kondisi baik. Barang reject/cacat jangan dimasukkan agar Jurnal Aset tetap akurat. Jika ada barang datang lebih dari pesanan (Over-Receipt), Anda bisa mengisi melebihi angka pesanan.
                                            </div>

                                            <div class="table-responsive bg-white border rounded">
                                                <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                                    <thead class="table-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                                        <tr>
                                                            <th class="ps-3 py-2">{{ __('erp.item_code_sku') }}</th>
                                                            <th>{{ __('erp.item_description') }}</th>
                                                            <th class="text-end">{{ __('erp.unit_price_rp_short') }}</th>
                                                            <th class="text-center">{{ __('erp.total_ordered') }}</th>
                                                            <th class="text-center">{{ __('erp.remaining_not_arrived') }}</th>
                                                            <th class="text-center pe-3" width="15%">{{ __('erp.receive_now_actual') }}</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($o->details as $det)
                                                            @php $sisaQty = $det->qty - $det->qty_received; @endphp
                                                            <tr class="{{ $sisaQty <= 0 ? 'bg-light text-muted' : '' }}">
                                                                <td class="ps-3 fw-bold">{{ $det->item_code }}</td>
                                                                <td class="text-wrap" style="min-width: 200px;">{{ $det->description }}</td>
                                                                <td class="text-end font-monospace">{{ number_format($det->price, 0, ',', '.') }}</td>
                                                                <td class="text-center fw-bold">{{ $det->qty }}</td>
                                                                <td class="text-center fw-bold {{ $sisaQty > 0 ? 'text-danger' : 'text-success' }}">{{ $sisaQty }}</td>
                                                                <td class="pe-3 py-2">
                                                                    @if($sisaQty > 0)
                                                                        <input type="number" name="items[{{ $det->id }}]" class="form-control form-control-sm text-center fw-bold border-success text-success" value="{{ $sisaQty }}" min="0">
                                                                    @else
                                                                        <span class="badge bg-success w-100 py-2"><i class="fa-solid fa-check"></i> {{ __('erp.status_complete') }}</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-white py-3">
                                            <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                                            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                                                <i class="fa-solid fa-boxes-packing me-1"></i> Simpan & Generate Jurnal Stok
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endif

                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">{{ __('erp.no_purchase_tx_yet') }}</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $orders->links() }}</div>
    </div>
</div>
@endsection
