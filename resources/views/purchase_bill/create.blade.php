@extends('layouts.app')
@section('top_bar_left')
    <a href="{{ route('purchase-bills.index') }}" class="btn btn-sm btn-white border fw-bold text-secondary me-3"><i class="fa-solid fa-arrow-left"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_bills') => route('purchase-bills.index'), __('erp.bc_create_new') => null]" />
@endsection
@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1200px;">
    <form action="{{ route('purchase-bills.store') }}" method="POST">
        @csrf
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>{{ __('erp.create_direct_bill') }}</h4>
            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm"><i class="fa-solid fa-save me-1"></i> {{ __('erp.save_bill_journal') }}</button>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-white py-3"><h6 class="fw-bold m-0 text-primary">{{ __('erp.bill_information') }}</h6></div>
            <div class="card-body row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.bill_no_required') }}</label>
                    <input type="text" name="bill_number" class="form-control fw-bold" value="{{ $autoNumber }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.tx_date_required') }}</label>
                    <input type="date" name="bill_date" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.supplier_store_required') }}</label>
                    <input type="text" name="vendor_name" class="form-control" placeholder="{{ __('erp.store_supplier_name') }}" required>
                </div>
                <div class="col-md-8">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.additional_notes') }}</label>
                    <input type="text" name="notes" class="form-control" placeholder="{{ __('erp.eg_buy_atk') }}">
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.payment_source_credit_account_required') }}</label>
                    <select name="credit_account" class="form-select fw-bold border-danger text-danger select2" required>
                        @foreach($creditAccounts as $acc)
                            <option value="{{ $acc->account_code }}" {{ $acc->account_code == '22000' ? 'selected' : '' }}>{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white py-3"><h6 class="fw-bold m-0 text-warning">{{ __('erp.cost_purchase_allocation') }}</h6></div>
            <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-center">
                    <tr>
                        <th width="40%">{{ __('erp.account_code_debit') }}</th>
                        <th width="35%">{{ __('erp.cost_description') }}</th>
                        <th width="20%">{{ __('erp.amount_rp') }}</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody id="baris-biaya">
                    <tr>
                        <td>
                            <select name="details[0][account_code]" class="form-select select2" required>
                                <option value="">{{ __('erp.select_expense_account_dots') }}</option>
                                @foreach($accounts as $acc)
                                    <option value="{{ $acc->account_code }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td><input type="text" name="details[0][description]" class="form-control form-control-sm"></td>
                        <td><input type="number" name="details[0][amount]" class="form-control form-control-sm text-end val-amount" value="0" min="0" required></td>
                        <td class="text-center"></td>
                    </tr>
                </tbody>
            </table>
            <div class="card-footer bg-white p-2">
                <button type="button" class="btn btn-sm btn-outline-primary fw-bold" onclick="tambahBaris()">{{ __('erp.add_cost_row') }}</button>
            </div>
        </div>

        <div class="row justify-content-end mt-3">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm p-3">
                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-muted fw-bold">{{ __('erp.subtotal_label') }}</span>
                        <span class="fw-bold" id="lbl_subtotal">{{ __('erp.rp_zero') }}</span>
                    </div>
                    <div class="d-flex justify-content-between mb-3 align-items-center">
                        <span class="text-muted fw-bold">{{ __('erp.tax_vat_paren') }}</span>
                        <input type="number" name="tax_amount" class="form-control form-control-sm w-50 text-end val-tax" value="0">
                    </div>
                    <hr class="my-2">
                    <div class="d-flex justify-content-between align-items-end">
                        <span class="fw-bold text-uppercase">{{ __('erp.total_bill') }}</span>
                        <h4 class="fw-bold text-danger m-0" id="lbl_grandtotal">{{ __('erp.rp_zero') }}</h4>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    let barisIdx = 1;
    function initSelect2() { $('.select2').select2({ theme: 'bootstrap-5' }); }

    function kalkulasiTotal() {
        let subtotal = 0;
        $('.val-amount').each(function() { subtotal += parseFloat($(this).val()) || 0; });
        let tax = parseFloat($('.val-tax').val()) || 0;
        let grand = subtotal + tax;

        $('#lbl_subtotal').text('Rp ' + subtotal.toLocaleString('id-ID'));
        $('#lbl_grandtotal').text('Rp ' + grand.toLocaleString('id-ID'));
    }

    function tambahBaris() {
        let tmpl = $('#baris-biaya tr:first').clone();
        tmpl.find('select').attr('name', `details[${barisIdx}][account_code]`).val('');
        tmpl.find('input[type="text"]').attr('name', `details[${barisIdx}][description]`).val('');
        tmpl.find('.val-amount').attr('name', `details[${barisIdx}][amount]`).val('0');
        tmpl.find('td:last').html('<button type="button" class="btn btn-sm btn-outline-danger px-2 py-0" onclick="this.closest(\'tr\').remove(); kalkulasiTotal();"><i class="fa-solid fa-xmark"></i></button>');
        
        tmpl.find('.select2-container').remove();
        $('#baris-biaya').append(tmpl);
        initSelect2();
        barisIdx++;
        kalkulasiTotal();
    }

    $(document).ready(function() {
        initSelect2();
        $(document).on('input', '.val-amount, .val-tax', kalkulasiTotal);
        kalkulasiTotal();
    });
</script>
@endsection
