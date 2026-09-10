@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('invoice.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_sales') => '#', __('erp.sales_invoice') => route('invoice.index'), __('erp.bc_create_manual') => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1400px;">
    @if(session('error'))
        <div class="alert alert-danger fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}</div>
    @endif

    <form action="{{ route('invoice.store') }}" method="POST" id="form-invoice">
        @csrf
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>{{ __('erp.create_sales_invoice_direct') }}</h4>
            <div>
                <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fa-solid fa-save me-1"></i> {{ __('erp.issue_invoice') }}</button>
            </div>
        </div>

        <div class="row g-3">
            {{-- KOLOM KIRI --}}
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold m-0 text-primary">{{ __('erp.invoice_information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.invoice_no_required') }}</label>
                                <input type="text" name="invoice_number" class="form-control fw-bold text-success" value="{{ $autoNumber }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.date_required') }}</label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.customer_required') }}</label>
                                <input type="text" name="contact_name" class="form-control" placeholder="Nama Customer" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold m-0 text-warning">{{ __('erp.goods_out_detail') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle m-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-center">
                                    <tr>
                                        <th width="35%">{{ __('erp.product_item_code') }}</th>
                                        <th width="15%">{{ __('erp.price_rp') }}</th>
                                        <th width="10%">{{ __('erp.qty_out') }}</th>
                                        <th width="15%">{{ __('erp.discount_rp') }}</th>
                                        <th width="20%">{{ __('erp.total_rp') }}</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="baris-produk">
                                    <tr>
                                        <td>
                                            <select name="details[0][item_code]" class="form-select select2-produk product-select" required>
                                                <option value="">{{ __('erp.search_product_ph') }}</option>
                                                @foreach($products as $p)
                                                    <option value="{{ $p->sku }}" data-price="{{ $p->sell_price }}" data-id="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }}</option>
                                                @endforeach
                                            </select>
                                            <input type="hidden" name="details[0][description]" class="product-desc">
                                        </td>
                                        <td><input type="number" name="details[0][price]" class="form-control form-control-sm text-end line-price" value="0" required></td>
                                        <td><input type="number" name="details[0][qty]" class="form-control form-control-sm text-center line-qty" value="1" required></td>
                                        <td><input type="number" name="details[0][disc_amount]" class="form-control form-control-sm text-end line-disc" value="0"></td>
                                        <td><input type="text" class="form-control form-control-sm text-end line-total fw-bold text-primary bg-light" value="0" readonly></td>
                                        <td class="text-center"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-2">
                        <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100" onclick="tambahBarisProduk()">{{ __('erp.add_product_row') }}</button>
                    </div>
                </div>
            </div>

            {{-- KOLOM KANAN --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="border-radius: 12px; top: 80px;">
                    <div class="card-header bg-dark text-white border-bottom py-3">
                        <h6 class="fw-bold m-0"><i class="fa-solid fa-calculator me-2"></i>{{ __('erp.final_calculation') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-bold small">Subtotal (<span id="txt_total_qty">0</span> produk)</span>
                            <span class="fw-bold text-dark" id="txt_subtotal">{{ __('erp.rp_zero') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.other_discount') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-danger">-</span>
                                <input type="number" name="other_discount" id="val_other_disc" class="form-control text-end calc-trigger" value="0">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.tax_paren') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-success">+</span>
                                <input type="number" name="tax_amount" id="val_tax" class="form-control text-end calc-trigger" value="0">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.net_shipping_cost') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-success">+</span>
                                <input type="number" name="shipping_cost" id="val_ship_cost" class="form-control text-end calc-trigger" value="0">
                            </div>
                        </div>
                        <div class="d-flex justify-content-between mb-3 align-items-center">
                            <span class="text-muted fw-bold small">{{ __('erp.other_costs') }}</span>
                            <div class="input-group input-group-sm w-50">
                                <span class="input-group-text bg-light text-success">+</span>
                                <input type="number" name="other_cost" id="val_other_cost" class="form-control text-end calc-trigger" value="0">
                            </div>
                        </div>

                        <hr class="my-3 border-dark">
                        <div class="d-flex justify-content-between align-items-end mb-3">
                            <span class="fw-bold text-uppercase" style="font-size: 0.9rem;">{{ __('erp.grand_total') }}</span>
                            <h3 class="fw-bold text-primary m-0" id="txt_grand_total">{{ __('erp.rp_zero') }}</h3>
                        </div>

                        <div class="form-check form-switch bg-light p-3 rounded border">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="is_paid" id="is_paid" value="1">
                            <label class="form-check-label fw-bold text-success" for="is_paid">{{ __('erp.mark_paid_immediately_cash') }}</label>
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
    let barisIndex = 1;

    function initSelect2() {
        $('.select2-produk').select2({ theme: 'bootstrap-5', width: '100%' });
    }

    window.kalkulasiTotal = function() {
        let sumSubtotal = 0; let sumDiscItems = 0; let sumQty = 0;

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
        let otherCost = parseFloat($('#val_other_cost').val()) || 0;

        let grandTotal = (sumSubtotal - sumDiscItems - otherDisc) + tax + shipCost + otherCost;

        $('#txt_total_qty').text(sumQty);
        $('#txt_subtotal').text('Rp ' + sumSubtotal.toLocaleString('id-ID'));
        $('#txt_grand_total').text('Rp ' + grandTotal.toLocaleString('id-ID'));
    };

    window.tambahBarisProduk = function() {
        let template = `
        <tr>
            <td>
                <select name="details[${barisIndex}][item_code]" class="form-select select2-produk product-select" required>
                    <option value="">{{ __('erp.search_product_ph') }}</option>
                    @foreach($products as $p)
                        <option value="{{ $p->sku }}" data-price="{{ $p->sell_price }}">{{ $p->sku }} - {{ $p->name }}</option>
                    @endforeach
                </select>
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
            let nameDesc = selected.text().split(' - ')[1] || '';

            let tr = $(this).closest('tr');
            tr.find('.line-price').val(price);
            tr.find('.product-desc').val(nameDesc);
            kalkulasiTotal();
        });
        $(document).on('input', '.line-price, .line-qty, .line-disc, .calc-trigger', kalkulasiTotal);
        kalkulasiTotal();
    });
</script>
@endsection
