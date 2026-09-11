@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('warehouse.inbound') }}" class="btn btn-sm btn-white border fw-bold text-secondary me-3"><i class="fa-solid fa-arrow-left"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_warehouse') => '#', __('erp.bc_goods_in') => route('warehouse.inbound'), __('erp.bc_input_receipt') => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1100px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0 text-dark">{{ __('erp.input_goods_receipt_inbound') }}</h4>
    </div>

    @if(session('success')) <div class="alert alert-success fw-bold shadow-sm"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}</div> @endif

    <ul class="nav nav-pills mb-4 bg-white p-2 rounded border shadow-sm flex-nowrap overflow-auto">
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'pembelian' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound.create', ['tab' => 'pembelian']) }}"><i class="fa-solid fa-truck-ramp-box me-1"></i> {{ __('erp.purchasing_po_label') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'transfer_masuk' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound.create', ['tab' => 'transfer_masuk']) }}"><i class="fa-solid fa-plus-circle me-1"></i> {{ __('erp.transfer_in') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'retur_penjualan' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound.create', ['tab' => 'retur_penjualan']) }}"><i class="fa-solid fa-arrow-rotate-left me-1"></i> {{ __('erp.sales_return') }}</a>
        </li>
    </ul>

    @if($tab == 'pembelian')
        <form action="#" method="POST" id="form-pembelian">
            @csrf
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3"><h6 class="fw-bold m-0 text-primary">{{ __('erp.receive_goods_from_po') }}</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.select_supplier_vendor_required') }}</label>
                        <select id="supplier_select" class="form-select select2" required>
                            <option value="">{{ __('erp.type_supplier_name_ph') }}</option>
                            @foreach($suppliers as $sup)
                                <option value="{{ $sup }}">{{ $sup }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.select_po_required') }}</label>
                        <select id="po_select" class="form-select select2" required disabled>
                            <option value="">{{ __('erp.select_supplier_first') }}</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.delivery_note_bill_no_required') }}</label>
                        <input type="text" name="bill_number" class="form-control fw-bold" placeholder="{{ __('erp.eg_delivery_note_inv') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.receive_in_date_required') }}</label>
                        <input type="date" name="receive_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.payment_due_date_optional') }}</label>
                        <input type="date" name="due_date" class="form-control">
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-light text-center">
                            <tr>
                                <th>{{ __('erp.item_code') }}</th>
                                <th>{{ __('erp.description_label') }}</th>
                                <th>{{ __('erp.total_ordered') }}</th>
                                <th>{{ __('erp.not_yet_received') }}</th>
                                <th width="20%">{{ __('erp.receive_now') }}</th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body">
                            <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('erp.select_po_to_load') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white border-top-0 pb-4">
                    <button type="submit" class="btn btn-success fw-bold px-4" id="btn-submit-pembelian" disabled><i class="fa-solid fa-save me-1"></i> {{ __('erp.process_receipt_journal') }}</button>
                </div>
            </div>
        </form>

    @elseif($tab == 'transfer_masuk')
        <form action="{{ route('warehouse.inbound.store') }}" method="POST">
            @csrf
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3"><h6 class="fw-bold m-0 text-success">{{ __('erp.manual_transfer_in') }}</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-3"><label class="form-label fw-bold small text-muted">{{ __('erp.ref_no_required') }}</label><input type="text" name="evidence_number" class="form-control fw-bold" value="{{ $autoNumberInbound }}" required></div>
                    <div class="col-md-3"><label class="form-label fw-bold small text-muted">{{ __('erp.tx_date_required') }}</label><input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                    <div class="col-md-6"><label class="form-label fw-bold small text-muted">{{ __('erp.notes_reason') }}</label><input type="text" name="description" class="form-control" placeholder="Contoh: Stok Opname / Pindah Gudang" required></div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.journal_balancing_account_credit_required') }}</label>
                        <select name="offset_account" class="form-select select2" required>
                            <option value="">{{ __('erp.select_account_placeholder') }}</option>
                            @foreach($accounts as $acc) <option value="{{ $acc->account_code }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option> @endforeach
                        </select>
                        <small class="text-info d-block mt-1"><i class="fa-solid fa-info-circle"></i> Nilai aset akan mendebet Persediaan (11200), pilih akun penyeimbangnya (Misal: Modal Awal / Pendapatan Penyesuaian Stok).</small>
                    </div>
                </div>
            </div>
            <div class="card border-0 shadow-sm">
                <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light text-center">
                        <tr><th width="50%">{{ __('erp.select_item') }}</th><th width="20%">{{ __('erp.qty_in') }}</th><th width="25%">{{ __('erp.new_cogs_per_unit_rp') }}</th><th width="5%"></th></tr>
                    </thead>
                    <tbody id="baris-inbound">
                        <tr>
                            <td>
                                <select name="items[0][product_id]" class="form-select select2" required>
                                    <option value="">{{ __('erp.select_product_ph') }}</option>
                                    @foreach($products as $p) <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option> @endforeach
                                </select>
                            </td>
                            <td><input type="number" name="items[0][qty]" class="form-control form-control-sm text-center" value="1" min="1" required></td>
                            <td><input type="number" name="items[0][unit_cost]" class="form-control form-control-sm text-end" value="0" min="0" required></td>
                            <td class="text-center"></td>
                        </tr>
                    </tbody>
                </table>
                <div class="card-footer bg-white p-3 d-flex justify-content-between align-items-center">
                    <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="tambahBaris('inbound')">{{ __('erp.add_item_btn') }}</button>
                    <button type="submit" class="btn btn-success fw-bold px-4"><i class="fa-solid fa-save me-1"></i> {{ __('erp.save') }}</button>
                </div>
            </div>
        </form>

    @elseif($tab == 'retur_penjualan')
        <form action="{{ route('sales-returns.store') }}" method="POST">
            @csrf
            <div class="card border-0 shadow-sm mb-3">
                <div class="card-header bg-white py-3"><h6 class="fw-bold m-0 text-danger">{{ __('erp.receive_return_from_customer') }}</h6></div>
                <div class="card-body row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.return_no') }} <span class="text-danger">*</span></label>
                        <input type="text" name="return_number" class="form-control fw-bold text-danger" value="{{ $autoNumberRetur }}" readonly required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.return_date') }} <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.original_sales_invoice') }} <span class="text-danger">*</span></label>
                        <select name="sales_invoice_id" id="select-invoice" class="form-select select2" required>
                            <option value="">{{ __('erp.select_invoice_placeholder') }}</option>
                            @foreach($invoices as $inv)
                                <option value="{{ $inv->id }}">{{ $inv->invoice_number }} - {{ $inv->contact_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.customer_label') }}</label>
                        <input type="text" name="contact_name" id="input-contact" class="form-control bg-light" readonly>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-dark text-uppercase">
                            <tr>
                                <th width="20%">{{ __('erp.product_code') }}</th>
                                <th width="35%">{{ __('erp.description_label') }}</th>
                                <th width="15%">{{ __('erp.sell_price') }}</th>
                                <th width="15%">{{ __('erp.qty_invoice') }}</th>
                                <th width="15%">{{ __('erp.qty_returned_lbl') }}</th>
                            </tr>
                        </thead>
                        <tbody id="items-body-retur">
                            <tr><td colspan="5" class="text-center py-4 text-muted">{{ __('erp.select_invoice_to_load') }}</td></tr>
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white p-3 text-end">
                    <button type="submit" class="btn btn-primary fw-bold px-4" id="btn-submit-retur" disabled><i class="fa-solid fa-save me-1"></i> {{ __('erp.create_return_document') }}</button>
                </div>
            </div>
        </form>
    @endif
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2').select2({ theme: 'bootstrap-5' });

        // LOGIC TAB PEMBELIAN (PO)
        $('#supplier_select').on('change', function() {
            let supplier = $(this).val();
            let poSelect = $('#po_select');
            poSelect.html('<option value="">{{ __('erp.loading_text') }}</option>').prop('disabled', true);
            
            if(supplier) {
                $.get("{{ route('warehouse.inbound.get_po') }}?supplier=" + encodeURIComponent(supplier), function(data) {
                    let options = '<option value="">{{ __('erp.select_po_placeholder') }}</option>';
                    data.forEach(po => {
                        options += `<option value="${po.id}">${po.po_number} - (Rp ${parseFloat(po.grand_total).toLocaleString('id-ID')})</option>`;
                    });
                    poSelect.html(options).prop('disabled', false);
                });
            } else {
                poSelect.html('<option value="">{{ __('erp.select_supplier_first') }}</option>').prop('disabled', true);
                $('#po-items-body').html('<tr><td colspan="5" class="text-center py-4 text-muted">{{ __('erp.select_po_to_load') }}</td></tr>');
                $('#btn-submit-pembelian').prop('disabled', true);
            }
        });

        $('#po_select').on('change', function() {
            let poId = $(this).val();
            let tbody = $('#po-items-body');
            let form = $('#form-pembelian');
            
            if(!poId) {
                tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">{{ __('erp.select_po_to_load') }}</td></tr>');
                $('#btn-submit-pembelian').prop('disabled', true);
                return;
            }

            form.attr('action', `/purchase-order/${poId}/receive`);
            tbody.html('<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> {{ __('erp.loading_items') }}</td></tr>');
            
            $.get(`/warehouse/inbound/get-po-details/${poId}`, function(res) {
                tbody.empty();
                let hasPending = false;
                res.items.forEach(item => {
                    let sisa = item.qty - item.qty_received;
                    if(sisa > 0) hasPending = true;
                    
                    let inputHtml = sisa > 0 ? 
                        `<input type="number" name="items[${item.id}]" class="form-control form-control-sm text-center fw-bold border-success text-success" value="${sisa}" min="0">` : 
                        `<span class="badge bg-success w-100 py-2"><i class="fa-solid fa-check"></i> {{ __('erp.status_complete') }}</span>`;
                    
                    let tr = `
                        <tr class="${sisa <= 0 ? 'bg-light text-muted' : ''}">
                            <td class="fw-bold ps-3">${item.item_code}</td>
                            <td>${item.description || '-'}</td>
                            <td class="text-center fw-bold">${item.qty}</td>
                            <td class="text-center fw-bold ${sisa > 0 ? 'text-danger' : 'text-success'}">${sisa}</td>
                            <td class="pe-3">${inputHtml}</td>
                        </tr>
                    `;
                    tbody.append(tr);
                });
                $('#btn-submit-pembelian').prop('disabled', !hasPending);
            });
        });

        // LOGIC TAB RETUR PENJUALAN
        $('#select-invoice').change(function() {
            let invId = $(this).val();
            let tbody = $('#items-body-retur');
            
            if (!invId) {
                tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">{{ __('erp.select_invoice_to_load') }}</td></tr>');
                $('#input-contact').val('');
                $('#btn-submit-retur').prop('disabled', true);
                return;
            }

            tbody.html('<tr><td colspan="5" class="text-center py-4"><div class="spinner-border spinner-border-sm text-primary"></div> {{ __('erp.loading_dots') }}</td></tr>');

            $.get('{{ url("sales-returns/get-invoice-items") }}/' + invId, function(res) {
                $('#input-contact').val(res.contact_name);
                tbody.empty();
                if (!res.items || res.items.length === 0) {
                    tbody.html('<tr><td colspan="5" class="text-center py-4 text-danger">{{ __('erp.no_items_on_invoice') }}</td></tr>');
                    $('#btn-submit-retur').prop('disabled', true);
                    return;
                }
                res.items.forEach(function(item, idx) {
                    let tr = `
                    <tr>
                        <td class="fw-bold ps-3">${item.item_code}
                            <input type="hidden" name="details[${idx}][item_code]" value="${item.item_code}">
                            <input type="hidden" name="details[${idx}][price]" value="${item.price}">
                            <input type="hidden" name="details[${idx}][description]" value="${item.description || ''}">
                        </td>
                        <td>${item.description || '-'}</td>
                        <td class="text-end font-monospace">Rp ${parseFloat(item.price).toLocaleString('id-ID')}</td>
                        <td class="text-center fw-bold">${item.qty}</td>
                        <td class="text-center pe-3">
                            <input type="number" name="details[${idx}][qty_returned]" class="form-control form-control-sm text-center fw-bold border-danger text-danger" value="0" min="0" max="${item.qty}">
                        </td>
                    </tr>`;
                    tbody.append(tr);
                });
                $('#btn-submit-retur').prop('disabled', false);
            });
        });
    });

    let bIdx = 1;
    function tambahBaris(type) {
        let tmpl = $('#baris-'+type+' tr:first').clone();
        tmpl.find('select').attr('name', `items[${bIdx}][product_id]`).val('');
        tmpl.find('input[type="number"]:first').attr('name', `items[${bIdx}][qty]`).val('1');
        if(type==='inbound') tmpl.find('input[type="number"]:last').attr('name', `items[${bIdx}][unit_cost]`).val('0');
        tmpl.find('td:last').html('<button type="button" class="btn btn-sm btn-outline-danger px-2 py-0" onclick="this.closest(\'tr\').remove()"><i class="fa-solid fa-xmark"></i></button>');
        tmpl.find('.select2-container').remove(); $('#baris-'+type).append(tmpl); $('.select2').select2({ theme: 'bootstrap-5' }); bIdx++;
    }
</script>
@endsection
