@extends('layouts.app')
@section('top_bar_left')
    <a href="{{ route('warehouse.outbound') }}" class="btn btn-sm btn-white border fw-bold text-secondary me-3"><i class="fa-solid fa-arrow-left"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_warehouse') => '#', __('erp.bc_goods_out') => route('warehouse.outbound'), __('erp.bc_manual') => null]" />
@endsection
@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1000px;">
    @if(session('error')) <div class="alert alert-danger fw-bold shadow-sm">{{ session('error') }}</div> @endif
    <form action="{{ route('warehouse.outbound.store') }}" method="POST">
        @csrf
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-bold mb-0 text-dark">{{ __('erp.manual_goods_issue') }}</h4>
            <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fa-solid fa-save me-1"></i> {{ __('erp.save') }}</button>
        </div>
        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body row g-3">
                <div class="col-md-3"><label class="form-label fw-bold small text-muted">{{ __('erp.ref_no_required') }}</label><input type="text" name="evidence_number" class="form-control fw-bold text-danger" value="{{ $autoNumber }}" required></div>
                <div class="col-md-3"><label class="form-label fw-bold small text-muted">{{ __('erp.tx_date_required') }}</label><input type="date" name="transaction_date" class="form-control" value="{{ date('Y-m-d') }}" required></div>
                <div class="col-md-6"><label class="form-label fw-bold small text-muted">{{ __('erp.notes_reason') }}</label><input type="text" name="description" class="form-control" placeholder="Contoh: Barang Rusak / Kadaluarsa" required></div>
                <div class="col-md-12">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.journal_balancing_account_debit_required') }}</label>
                    <select name="offset_account" class="form-select select2" required>
                        <option value="">{{ __('erp.select_account_placeholder') }}</option>
                        @foreach($accounts as $acc) <option value="{{ $acc->account_code }}">{{ $acc->account_code }} - {{ $acc->account_name }}</option> @endforeach
                    </select>
                    <small class="text-danger d-block mt-1"><i class="fa-solid fa-info-circle"></i> Nilai aset akan mengkredit Persediaan (11200), pilih akun penyeimbangnya (Misal: Beban Kerusakan / Penyesuaian Stok).</small>
                </div>
            </div>
        </div>
        <div class="card border-0 shadow-sm">
            <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                <thead class="table-light text-center">
                    <tr><th width="70%">{{ __('erp.select_available_item') }}</th><th width="25%">{{ __('erp.qty_out') }}</th><th width="5%"></th></tr>
                </thead>
                <tbody id="baris-outbound">
                    <tr>
                        <td>
                            <select name="items[0][product_id]" class="form-select select2" required>
                                <option value="">{{ __('erp.select_product_ph') }}</option>
                                @foreach($products as $p) <option value="{{ $p->id }}">{{ $p->sku }} - {{ $p->name }} (Stok: {{ $p->stock_quantity }})</option> @endforeach
                            </select>
                        </td>
                        <td><input type="number" name="items[0][qty]" class="form-control form-control-sm text-center text-danger fw-bold" value="1" min="1" required></td>
                        <td class="text-center"></td>
                    </tr>
                </tbody>
            </table>
            <div class="card-footer bg-white p-2">
                <button type="button" class="btn btn-sm btn-outline-danger fw-bold" onclick="tambahBaris('outbound')">{{ __('erp.add_item_btn') }}</button>
            </div>
        </div>
    </form>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script><script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    let bIdx = 1;
    function initSelect2() { $('.select2').select2({ theme: 'bootstrap-5' }); }
    function tambahBaris(type) {
        let tmpl = $('#baris-'+type+' tr:first').clone();
        tmpl.find('select').attr('name', `items[${bIdx}][product_id]`).val('');
        tmpl.find('input[type="number"]:first').attr('name', `items[${bIdx}][qty]`).val('1');
        tmpl.find('td:last').html('<button type="button" class="btn btn-sm btn-outline-danger px-2 py-0" onclick="this.closest(\'tr\').remove()"><i class="fa-solid fa-xmark"></i></button>');
        tmpl.find('.select2-container').remove(); $('#baris-'+type).append(tmpl); initSelect2(); bIdx++;
    }
    $(document).ready(function() { initSelect2(); });
</script>
@endsection
