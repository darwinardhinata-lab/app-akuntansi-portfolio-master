@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('jurnal.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.general_journal') => route('jurnal.index'), __('erp.bc_edit') => null]" />
@endsection

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .journal-wrapper { font-family: 'Inter', sans-serif; color: #334155; max-width: 1100px; margin: 0 auto; }
    .card-modern { background: #ffffff; border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 30px; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
    .input-header { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 15px; }
    .table-clean td { vertical-align: middle; padding: 10px 5px; border-bottom: 1px solid #f1f5f9; }
    .input-transparent { width: 100%; border: 1px solid transparent; background: transparent; padding: 8px 10px; font-size: 0.95rem; border-radius: 6px; }
    .input-transparent:focus { background-color: #fff; border: 1px solid #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    .summary-box { background: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; }
</style>

<div class="journal-wrapper mt-4 mb-5">
    <div class="mb-4">
        <h3 class="fw-bold mb-1">{{ __('erp.edit_general_journal') }}</h3>
        <p class="text-muted small">{{ __('erp.editing_transaction_colon') }} <strong>{{ $journal->journal_id }}</strong></p>
    </div>

    <form action="{{ route('jurnal.update', $journal->journal_id) }}" method="POST">
        @csrf
        @method('PUT')
        
        <div class="card-modern mb-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label">{{ __('erp.date') }}</label>
                    <input type="date" name="transaction_date" class="form-control input-header" value="{{ $journal->transaction_date }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">{{ __('erp.evidence_no') }}</label>
                    <input type="text" name="evidence_number" class="form-control input-header" value="{{ $journal->evidence_number }}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">{{ __('erp.description_label') }}</label>
                    <input type="text" name="description" class="form-control input-header" value="{{ $journal->description }}" required>
                </div>
            </div>
        </div>

        <div class="card-modern">
            <div class="table-responsive erp-journal-lines">
            <table class="table table-clean mb-0">
                <thead>
                    <tr class="text-muted small fw-bold">
                        <th width="35%">{{ __('erp.account_code_caps') }}</th>
                        <th width="25%">{{ __('erp.helper_code_caps') }}</th>
                        <th width="15%">{{ __('erp.position_caps') }}</th>
                        <th width="20%" class="text-end">{{ __('erp.amount_rp_caps') }}</th>
                        <th width="5%"></th>
                    </tr>
                </thead>
                <tbody id="baris-jurnal">
                    @foreach($journal->details as $index => $detail)
                    <tr>
                        <td data-label="Kode Akun">
                            <select name="details[{{ $index }}][account_code]" class="input-transparent select-account" required>
                                @foreach($accounts as $akun)
                                    <option value="{{ $akun->account_code }}" {{ $detail->account_code == $akun->account_code ? 'selected' : '' }}>
                                        {{ $akun->account_code }} - {{ $akun->account_name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td data-label="Kode Bantu">
                            <select name="details[{{ $index }}][helper_code]" class="input-transparent">
                                <option value="">{{ __('erp.empty_dash') }}</option>
                                @foreach($helpers as $helper)
                                    <option value="{{ $helper->helper_code }}" {{ $detail->helper_code == $helper->helper_code ? 'selected' : '' }}>
                                        {{ $helper->helper_code }} - {{ $helper->entity_name }}
                                    </option>
                                @endforeach
                            </select>
                        </td>
                        <td data-label="Posisi">
                            <select name="details[{{ $index }}][position]" class="input-transparent position-select" required>
                                <option value="DEBET" {{ $detail->position == 'DEBET' ? 'selected' : '' }}>{{ __('erp.debit_caps') }}</option>
                                <option value="KREDIT" {{ $detail->position == 'KREDIT' ? 'selected' : '' }}>{{ __('erp.credit_caps') }}</option>
                            </select>
                        </td>
                        <td data-label="Nominal (Rp)">
                            <input type="number" name="details[{{ $index }}][amount]" class="input-transparent amount-input text-end fw-bold" value="{{ $detail->amount }}" required>
                        </td>
                        <td class="text-center">
                            <button type="button" class="btn btn-sm text-danger btn-hapus" {{ $index < 2 ? 'disabled' : '' }}>✕</button>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            </div>

            <button type="button" class="btn btn-outline-primary btn-sm mt-3" id="btn-tambah">{{ __('erp.add_row') }}</button>

            <div class="summary-box mt-4 d-flex justify-content-between align-items-center">
                <div id="statusIndicator" class="badge rounded-pill p-2 px-3"></div>
                <div class="d-flex gap-4 align-items-center">
                    <div class="text-end">
                        <small class="fw-bold text-muted">{{ __('erp.total_debit_caps') }}</small>
                        <div id="textDebet" class="fw-bold text-success fs-5">0</div>
                    </div>
                    <div class="text-end">
                        <small class="fw-bold text-muted">{{ __('erp.total_credit_caps') }}</small>
                        <div id="textKredit" class="fw-bold fs-5">0</div>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold" id="btnSimpan">{{ __('erp.save_changes') }}</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        let rowCount = {{ count($journal->details) }};
        
        function initSelect2() {
            $('.select-account').select2({ theme: 'bootstrap-5', width: '100%' });
        }

        function hitungTotal() {
            let totalD = 0, totalK = 0;
            $('.amount-input').each(function() {
                let amt = parseFloat($(this).val()) || 0;
                let pos = $(this).closest('tr').find('.position-select').val();
                if (pos === 'DEBET') totalD += amt; else totalK += amt;
            });
            $('#textDebet').text(new Intl.NumberFormat('id-ID').format(totalD));
            $('#textKredit').text(new Intl.NumberFormat('id-ID').format(totalK));
            
            if (totalD === totalK && totalD > 0) {
                $('#statusIndicator').text('✓ BALANCE').addClass('bg-success text-white').removeClass('bg-danger');
                $('#btnSimpan').prop('disabled', false);
            } else {
                $('#statusIndicator').text('✕ TIDAK BALANCE').addClass('bg-danger text-white').removeClass('bg-success');
                $('#btnSimpan').prop('disabled', true);
            }
        }

        $('#btn-tambah').click(function() {
            let newRow = $('#baris-jurnal tr:first').clone();
            newRow.find('select, input').each(function() {
                let name = $(this).attr('name').replace(/\[\d+\]/, `[${rowCount}]`);
                $(this).attr('name', name).val(name.includes('amount') ? 0 : '');
            });
            newRow.find('.btn-hapus').prop('disabled', false).click(function() { $(this).closest('tr').remove(); hitungTotal(); });
            newRow.find('.select2-container').remove();
            $('#baris-jurnal').append(newRow);
            rowCount++;
            initSelect2();
            hitungTotal();
        });

        $(document).on('input change', '.amount-input, .position-select', hitungTotal);
        $('.btn-hapus').click(function() { $(this).closest('tr').remove(); hitungTotal(); });
        
        initSelect2();
        hitungTotal();
    });
</script>
@endsection
