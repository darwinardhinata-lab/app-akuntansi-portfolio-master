@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('po.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_purchase_orders') => route('po.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1400px;">
    <form action="{{ route('po.store') }}" method="POST" id="form-po">
        @csrf
        
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.create_po') }}</h4>
            <div>
                <a href="{{ route('po.index') }}" class="btn btn-outline-secondary fw-bold px-3 me-2">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4 shadow-sm">
                    <i class="fa-solid fa-save me-1"></i> Simpan PO
                </button>
            </div>
        </div>

        <div class="row g-3">
            {{-- KOLOM KIRI: Form Input Utama --}}
            <div class="col-lg-8">
                
                {{-- 1. INFORMASI UTAMA --}}
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold m-0 text-primary"><i class="fa-solid fa-file-invoice me-2"></i>{{ __('erp.purchase_information') }}</h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.po_number') }} <span class="text-danger">*</span></label>
                                <input type="text" name="po_number" class="form-control fw-bold" placeholder="PO-XXXX" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.transaction_date') }} <span class="text-danger">*</span></label>
                                <input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.supplier_vendor_name') }} <span class="text-danger">*</span></label>
                                <input type="text" name="contact_name" class="form-control" placeholder="Masukkan nama vendor..." required>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- 2. RINCIAN PRODUK --}}
                <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                    <div class="card-header bg-white border-bottom py-3">
                        <h6 class="fw-bold m-0 text-warning"><i class="fa-solid fa-box-open me-2"></i>{{ __('erp.products_ordered') }}</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle m-0" style="font-size: 0.85rem;">
                                <thead class="table-light text-muted text-center">
                                    <tr>
                                        <th width="30%">{{ __('erp.sku_item_code') }}</th>
                                        <th width="30%">{{ __('erp.description_optional') }}</th>
                                        <th width="15%">{{ __('erp.unit_price') }}</th>
                                        <th width="10%">{{ __('erp.qty_caps') }}</th>
                                        <th width="15%">{{ __('erp.total_rp') }}</th>
                                        <th width="5%"></th>
                                    </tr>
                                </thead>
                                <tbody id="baris-po">
                                    <tr>
                                        <td><input type="text" name="details[0][item_code]" class="form-control form-control-sm" placeholder="Ketik SKU..." required></td>
                                        <td><input type="text" name="details[0][description]" class="form-control form-control-sm"></td>
                                        <td><input type="number" name="details[0][price]" class="form-control form-control-sm text-end price-input" value="0" min="0" required></td>
                                        <td><input type="number" name="details[0][qty]" class="form-control form-control-sm text-center qty-input" value="1" min="1" required></td>
                                        <td><input type="text" class="form-control form-control-sm text-end line-total fw-bold text-primary bg-light" value="0" readonly></td>
                                        <td class="text-center"></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white py-2">
                        <button type="button" class="btn btn-outline-primary btn-sm fw-bold w-100" onclick="tambahBaris()">{{ __('erp.add_item_row') }}</button>
                    </div>
                </div>

            </div>

            {{-- KOLOM KANAN: Rincian Kalkulasi & Pajak --}}
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm sticky-top" style="border-radius: 12px; top: 80px;">
                    <div class="card-header bg-dark text-white border-bottom py-3">
                        <h6 class="fw-bold m-0"><i class="fa-solid fa-calculator me-2"></i>{{ __('erp.calculation_and_tax') }}</h6>
                    </div>
                    <div class="card-body">
                        
                        {{-- Pengaturan Pajak --}}
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.price_status') }}</label>
                            <select name="is_include_ppn" id="is_include_ppn" class="form-select form-select-sm fw-bold">
                                <option value="0">{{ __('erp.exclude_vat') }}</option>
                                <option value="1">{{ __('erp.include_vat') }}</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.addition_tax_vat') }}</label>
                            <select name="tax_addition_id" id="tax_addition_id" class="form-select form-select-sm">
                                <option value="" data-rate="0">{{ __('erp.none_option_caps') }}</option>
                                @foreach($taxesAddition as $tax)
                                    <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}">{{ $tax->tax_name }} ({{ number_format($tax->rate, 0) }}%)</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mb-4">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.deduction_tax_wht') }}</label>
                            <select name="tax_deduction_id" id="tax_deduction_id" class="form-select form-select-sm">
                                <option value="" data-rate="0">{{ __('erp.none_option_caps') }}</option>
                                @foreach($taxesDeduction as $tax)
                                    <option value="{{ $tax->id }}" data-rate="{{ $tax->rate }}">{{ $tax->tax_name }} ({{ number_format($tax->rate, 2) }}%)</option>
                                @endforeach
                            </select>
                        </div>

                        <hr class="my-3 border-dark">

                        {{-- Ringkasan --}}
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-bold small">Subtotal (<span id="txt_total_qty">0</span> item)</span>
                            <span class="fw-bold text-dark" id="lbl_subtotal">{{ __('erp.rp_zero') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-bold small">{{ __('erp.tax_base_dpp') }}</span>
                            <span class="fw-bold text-danger" id="lbl_dpp">{{ __('erp.rp_zero') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-bold small">{{ __('erp.addition_tax_plus') }}</span>
                            <span class="fw-bold text-success" id="lbl_addition">{{ __('erp.rp_zero') }}</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span class="text-muted fw-bold small">{{ __('erp.deduction_tax_minus') }}</span>
                            <span class="fw-bold text-danger" id="lbl_deduction">{{ __('erp.rp_zero') }}</span>
                        </div>

                        <hr class="my-3 border-dark">

                        <div class="d-flex justify-content-between align-items-end mb-2">
                            <span class="fw-bold text-uppercase" style="font-size: 0.9rem;">{{ __('erp.total_payment') }}</span>
                            <h3 class="fw-bold text-primary m-0" id="lbl_grandtotal">{{ __('erp.rp_zero') }}</h3>
                        </div>

                        {{-- HIDDEN INPUT UNTUK CONTROLLER --}}
                        <input type="hidden" name="dpp" id="val_dpp" value="0">
                        <input type="hidden" name="tax_addition_amount" id="val_addition" value="0">
                        <input type="hidden" name="tax_deduction_amount" id="val_deduction" value="0">
                        <input type="hidden" name="grand_total" id="val_grandtotal" value="0">
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    let barisCount = 1;
    function tambahBaris() {
        const tbody = document.getElementById('baris-po');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="details[${barisCount}][item_code]" class="form-control form-control-sm" placeholder="Ketik SKU..." required></td>
            <td><input type="text" name="details[${barisCount}][description]" class="form-control form-control-sm"></td>
            <td><input type="number" name="details[${barisCount}][price]" class="form-control form-control-sm text-end price-input" value="0" min="0" required></td>
            <td><input type="number" name="details[${barisCount}][qty]" class="form-control form-control-sm text-center qty-input" value="1" min="1" required></td>
            <td><input type="text" class="form-control form-control-sm text-end line-total fw-bold text-primary bg-light" value="0" readonly></td>
            <td class="text-center"><button type="button" class="btn btn-sm btn-outline-danger py-0 px-2" onclick="this.closest('tr').remove(); window.calculateGrandTotal();"><i class="fa-solid fa-xmark"></i></button></td>
        `;
        tbody.appendChild(tr);
        barisCount++;
    }

    window.calculateGrandTotal = function() {
        let subtotal = 0;
        let sumQty = 0;

        $('#baris-po tr').each(function() {
            let price = parseFloat($(this).find('.price-input').val()) || 0;
            let qty = parseFloat($(this).find('.qty-input').val()) || 0;
            let rowSub = price * qty;
            
            $(this).find('.line-total').val(rowSub.toLocaleString('id-ID'));
            
            subtotal += rowSub;
            sumQty += qty;
        });
        
        let isIncludePPN = $('#is_include_ppn').val() == '1';
        let rateAddition = parseFloat($('#tax_addition_id option:selected').attr('data-rate')) || 0;
        let rateDeduction = parseFloat($('#tax_deduction_id option:selected').attr('data-rate')) || 0;

        let dpp = subtotal;
        if (isIncludePPN && rateAddition > 0) {
            dpp = subtotal / (1 + (rateAddition / 100));
        }

        let additionAmount = dpp * (rateAddition / 100);
        let deductionAmount = dpp * (rateDeduction / 100);
        let grandTotal = isIncludePPN ? (subtotal - deductionAmount) : (subtotal + additionAmount - deductionAmount);

        $('#txt_total_qty').text(sumQty);
        $('#lbl_subtotal').text(subtotal.toLocaleString('id-ID'));
        $('#lbl_dpp').text(dpp.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
        $('#lbl_addition').text(additionAmount.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
        $('#lbl_deduction').text(deductionAmount.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0}));
        $('#lbl_grandtotal').text('Rp ' + grandTotal.toLocaleString('id-ID', {minimumFractionDigits: 0, maximumFractionDigits: 0}));

        $('#val_dpp').val(dpp);
        $('#val_addition').val(additionAmount);
        $('#val_deduction').val(deductionAmount);
        $('#val_grandtotal').val(grandTotal);
    };

    $(document).ready(function() {
        $(document).on('input', '.price-input, .qty-input', function() {
            window.calculateGrandTotal();
        });
        $('#is_include_ppn, #tax_addition_id, #tax_deduction_id').on('change', function() {
            window.calculateGrandTotal();
        });
        window.calculateGrandTotal();
    });
</script>
@endsection
