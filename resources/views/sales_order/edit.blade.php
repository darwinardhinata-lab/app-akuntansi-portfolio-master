@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('so.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_sales') => '#', __('erp.bc_sales_orders') => route('so.index'), __('erp.bc_edit') => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1400px;">
    <form action="{{ route('so.update', $so->id) }}" method="POST" id="form-so">
        @csrf
        @method('PUT')
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.edit_sales_order') }}</h4>
            <div>
                <a href="{{ route('so.index') }}" class="btn btn-outline-secondary fw-bold px-3 me-2">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="fa-solid fa-save me-1"></i> {{ __('erp.update_order') }}</button>
            </div>
        </div>

        <div class="row g-3">
            {{-- KOLOM KIRI: Form Input --}}
            <div class="col-lg-8">
                
                {{-- 1. INFORMASI UTAMA --}}
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold m-0 text-primary"><i class="fa-solid fa-file-invoice me-2"></i>{{ __('erp.sales_transaction') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.order_no') }} <span class="text-danger">*</span></label>
                                <input type="text" name="so_number" class="form-control fw-bold text-primary" value="{{ $so->so_number }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d', strtotime($so->transaction_date)) }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.customer_label') }} <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="form-control" value="{{ $so->contact_name }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.ref_no_optional') }}</label>
                                <input type="text" name="ref_number" class="form-control" value="{{ $so->ref_number }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.salesman_label') }}</label>
                                <input type="text" name="salesman" class="form-control" value="{{ $so->salesman }}">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.source_label') }} <span class="text-danger">*</span></label>
                                <input type="text" name="source" class="form-control" value="{{ $so->source }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.store_label') }}</label>
                                <input type="text" name="store_name" class="form-control" value="{{ $so->store_name }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.warehouse_location') }}</label>
                                <input type="text" name="location_name" class="form-control" value="{{ $so->location_name }}">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.description') }}</label>
                                <textarea name="remarks" class="form-control" rows="2">{{ $so->remarks }}</textarea>
                            </div>
                            <div class="col-md-12 mt-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_tax_included" id="is_tax_included" value="1" {{ $so->is_tax_included ? 'checked' : '' }}>
                                    <label class="form-check-label fw-bold text-dark" for="is_tax_included">{{ __('erp.price_incl_tax') }}</label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. PENERIMA & PENGIRIMAN --}}
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between">
                        <h6 class="fw-bold m-0 text-success"><i class="fa-solid fa-truck-fast me-2"></i>{{ __('erp.recipient_shipping') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.recipient_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="receiver_name" class="form-control" value="{{ $so->receiver_name }}" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.phone_no') }}</label>
                                <input type="text" name="receiver_phone" class="form-control" value="{{ $so->receiver_phone }}">
                            </div>
                            <div class="col-md-12">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.full_address') }}</label>
                                <textarea name="receiver_address" class="form-control" rows="2">{{ $so->receiver_address }}</textarea>
                            </div>
                            <div class="col-12"><hr class="my-2"></div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.cod_method') }}</label>
                                <select name="is_cod" class="form-select">
                                    <option value="0" {{ $so->is_cod == 0 ? 'selected' : '' }}>{{ __('erp.no_word') }}</option>
                                    <option value="1" {{ $so->is_cod == 1 ? 'selected' : '' }}>{{ __('erp.yes_cod') }}</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.total_weight_gram') }}</label>
                                <div class="input-group">
                                    <input type="number" name="total_weight" class="form-control text-end" value="{{ $so->total_weight }}">
                                    <span class="input-group-text">g</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.expedition_courier') }}</label>
                                <input type="text" name="courier" class="form-control" value="{{ $so->courier }}">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.tracking_no') }}</label>
                                <input type="text" name="tracking_number" class="form-control" value="{{ $so->tracking_number }}">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 3. RINCIAN PRODUK --}}
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold m-0 text-warning"><i class="fa-solid fa-box-open me-2"></i>{{ __('erp.products_ordered') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle m-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-muted text-center">
                                    <tr>
                                        <th width="35%">{{ __('erp.product_item_code') }}</th>
                                        <th width="15%">{{ __('erp.price_rp') }}</th>
                                        <th width="10%">{{ __('erp.qty') }}</th>
                                        <th width="15%">{{ __('erp.discount_rp') }}</th>
                                        <th width="20%">{{ __('erp.total_rp') }}</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="baris-produk">
                                    @foreach($so->details as $index => $det)
                                    <tr>
                                        <td>
                                            <select name="details[{{ $index }}][item_code]" class="form-select select2-produk product-select" required>
                                                <option value="{{ $det->item_code }}">{{ $det->item_code }} - {{ $det->description }}</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->sku }}" data-price="{{ $p->sell_price }}" data-id="{{ $p->id }}" {{ $det->item_code == $p->sku ? 'selected' : '' }}>{{ $p->sku }} - {{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="details[{{ $index }}][product_id]" class="product-id" value="{{ $det->product_id }}">
                                            <input type="hidden" name="details[{{ $index }}][description]" class="product-desc" value="{{ $det->description }}">
                                        </td>
                                        <td><input type="number" name="details[{{ $index }}][price]" class="form-control form-control-sm text-end line-price" value="{{ (int)$det->price }}" required></td>
                                        <td><input type="number" name="details[{{ $index }}][qty]" class="form-control form-control-sm text-center line-qty" value="{{ $det->qty }}" required></td>
                                        <td><input type="number" name="details[{{ $index }}][disc_amount]" class="form-control form-control-sm text-end line-disc" value="{{ (int)$det->disc_amount }}"></td>
                                        <td><input type="text" class="form-control form-control-sm text-end line-total fw-bold text-primary bg-light" value="0" readonly></td>
                                        <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="this.closest('tr').remove(); kalkulasiTotal();"><i class="fa-solid fa-xmark"></i></button></td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-2">
                        <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100" onclick="tambahBarisProduk()">{{ __('erp.add_product_row') }}</button>
                    </div>
                </div>

            </div>

            {{-- KOLOM KANAN: Rincian Kalkulasi Total --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="border-radius: 12px; top: 80px;">
                    <div class="card-header bg-dark text-white border-bottom py-3">
                        <h6 class="fw-bold m-0"><i class="fa-solid fa-calculator me-2"></i>{{ __('erp.calculation_detail') }}</h6>
                    </div>
                    <div class="card-body">
                        
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-bold small">Subtotal (<span id="txt_total_qty">0</span> produk)</span>
                            <span class="fw-bold text-dark" id="txt_subtotal">{{ __('erp.rp_zero') }}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-2 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.product_discount') }}</span>
                            <span class="fw-bold text-danger" id="txt_disc_items">{{ __('erp.minus_rp_zero') }}</span>
                        </div>
                        
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.other_discount') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-danger">-</span>
                                <input type="number" name="other_discount" id="val_other_disc" class="form-control text-end calc-trigger" value="{{ (int)$so->other_discount }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.tax_paren') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-success">+</span>
                                <input type="number" name="tax_amount" id="val_tax" class="form-control text-end calc-trigger" value="{{ (int)$so->tax_amount }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.shipping_cost') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-success">+</span>
                                <input type="number" name="shipping_cost" id="val_ship_cost" class="form-control text-end calc-trigger" value="{{ (int)$so->shipping_cost }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.shipping_discount') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-danger">-</span>
                                <input type="number" name="shipping_discount" id="val_ship_disc" class="form-control text-end calc-trigger" value="{{ (int)$so->shipping_discount }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.other_costs') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-success">+</span>
                                <input type="number" name="other_cost" id="val_other_cost" class="form-control text-end calc-trigger" value="{{ (int)$so->other_cost }}">
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.remaining_return') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-danger">-</span>
                                <input type="number" name="return_remaining" id="val_return" class="form-control text-end calc-trigger" value="{{ (int)$so->return_remaining }}">
                            </div>
                        </div>

                        <hr class="my-3 border-dark">

                        <div class="d-flex justify-content-between align-items-end mb-3">
                            <span class="fw-bold text-uppercase" style="font-size: 0.9rem;">{{ __('erp.grand_total') }}</span>
                            <h3 class="fw-bold text-primary m-0" id="txt_grand_total">{{ __('erp.rp_zero') }}</h3>
                        </div>

                        <div class="form-check form-switch bg-light p-3 rounded border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="is_paid" id="is_paid" value="1" {{ $so->is_paid ? 'checked' : '' }}>
                            <label class="form-check-label fw-bold text-success" for="is_paid">{{ __('erp.mark_as_paid') }}</label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    let barisIndex = {{ count($so->details) + 1 }};

    function initSelect2() {
        $('.select2-produk').select2({ theme: 'bootstrap-5', width: '100%' });
    }

    function formatRp(angka) {
        return 'Rp ' + parseFloat(angka).toLocaleString('id-ID');
    }

    window.kalkulasiTotal = function() {
        let sumSubtotal = 0;
        let sumDiscItems = 0;
        let sumQty = 0;

        $('#baris-produk tr').each(function() {
            let price = parseFloat($(this).find('.line-price').val()) || 0;
            let qty = parseFloat($(this).find('.line-qty').val()) || 0;
            let disc = parseFloat($(this).find('.line-disc').val()) || 0;

            let rowSub = (price * qty);
            let rowTotal = rowSub - disc;

            $(this).find('.line-total').val(rowTotal.toLocaleString('id-ID'));

            sumSubtotal += rowSub;
            sumDiscItems += disc;
            sumQty += qty;
        });

        let otherDisc = parseFloat($('#val_other_disc').val()) || 0;
        let tax = parseFloat($('#val_tax').val()) || 0;
        let shipCost = parseFloat($('#val_ship_cost').val()) || 0;
        let shipDisc = parseFloat($('#val_ship_disc').val()) || 0;
        let otherCost = parseFloat($('#val_other_cost').val()) || 0;
        let returnVal = parseFloat($('#val_return').val()) || 0;

        let grandTotal = (sumSubtotal - sumDiscItems - otherDisc) 
                         + tax 
                         + (shipCost - shipDisc) 
                         + otherCost 
                         - returnVal;

        $('#txt_total_qty').text(sumQty);
        $('#txt_subtotal').text(formatRp(sumSubtotal));
        $('#txt_disc_items').text('- ' + formatRp(sumDiscItems));
        $('#txt_grand_total').text(formatRp(grandTotal));
    };

    window.tambahBarisProduk = function() {
        let template = `
        <tr>
            <td>
                <select name="details[${barisIndex}][item_code]" class="form-select select2-produk product-select" required>
                    <option value="">{{ __('erp.search_product_ph') }}</option>
                    @foreach($products as $p)
                        <option value="{{ $p->sku }}" data-price="{{ $p->sell_price }}" data-id="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="details[${barisIndex}][product_id]" class="product-id">
                <input type="hidden" name="details[${barisIndex}][description]" class="product-desc">
            </td>
            <td><input type="number" name="details[${barisIndex}][price]" class="form-control form-control-sm text-end line-price" value="0" required></td>
            <td><input type="number" name="details[${barisIndex}][qty]" class="form-control form-control-sm text-center line-qty" value="1" required></td>
            <td><input type="number" name="details[${barisIndex}][disc_amount]" class="form-control form-control-sm text-end line-disc" value="0"></td>
            <td><input type="text" class="form-control form-control-sm text-end line-total fw-bold text-primary bg-light" value="0" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="this.closest('tr').remove(); kalkulasiTotal();"><i class="fa-solid fa-xmark"></i></button></td>
        </tr>`;
        
        $('#baris-produk').append(template);
        initSelect2();
        barisIndex++;
        kalkulasiTotal();
    };

    $(document).ready(function() {
        initSelect2();
        
        $(document).on('change', '.product-select', function() {
            let selected = $(this).find(':selected');
            let price = selected.data('price') || 0;
            let id = selected.data('id') || '';
            let nameDesc = selected.text().split(' - ')[1] || '';

            let tr = $(this).closest('tr');
            tr.find('.line-price').val(price);
            tr.find('.product-id').val(id);
            tr.find('.product-desc').val(nameDesc);
            kalkulasiTotal();
        });

        $(document).on('input', '.line-price, .line-qty, .line-disc, .calc-trigger', function() {
            kalkulasiTotal();
        });

        kalkulasiTotal();
    });
</script>
@endsection
