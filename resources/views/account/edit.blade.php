@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('account.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_account_list_coa') => route('account.index'), __('erp.bc_edit') => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-white border-bottom p-4">
            <h4 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-pen-to-square me-2"></i> {{ __('erp.edit_coa') }}</h4>
        </div>
        <form action="{{ route('account.update', $account->account_code) }}" method="POST" class="card-body p-4">
            @csrf
            @method('PUT')
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.account_code_id') }}</label>
                    <input type="text" class="form-control fw-bold bg-light" value="{{ $account->account_code }}" disabled>
                    <small class="text-muted" style="font-size: 0.7rem;">{{ __('erp.account_code_immutable_hint') }}</small>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.account_name_required') }}</label>
                    <input type="text" name="account_name" class="form-control" value="{{ $account->account_name }}" required>
                </div>
                
                @php 
                    $type = $account->coa_type; 
                @endphp
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.account_type_required') }}</label>
                    <select name="coa_type" class="form-select fw-bold text-primary" required>
                        <option value="">{{ __('erp.select_account_category') }}</option>
                        <option value="Cash & Bank" {{ $type == 'Cash & Bank' ? 'selected' : '' }}>{{ __('erp.cash_and_bank') }}</option>
                        <option value="Piutang Dagang" {{ $type == 'Piutang Dagang' ? 'selected' : '' }}>{{ __('erp.trade_receivables') }}</option>
                        <option value="Persediaan" {{ $type == 'Persediaan' ? 'selected' : '' }}>{{ __('erp.inventory_label') }}</option>
                        <option value="Aset Lancar Lainnya" {{ $type == 'Aset Lancar Lainnya' ? 'selected' : '' }}>{{ __('erp.other_current_assets') }}</option>
                        <option value="Aset Tetap" {{ $type == 'Aset Tetap' ? 'selected' : '' }}>{{ __('erp.fixed_asset') }}</option>
                        <option value="Investasi Jangka Panjang" {{ $type == 'Investasi Jangka Panjang' ? 'selected' : '' }}>{{ __('erp.long_term_investment') }}</option>
                        <option value="Hutang Dagang" {{ $type == 'Hutang Dagang' ? 'selected' : '' }}>{{ __('erp.trade_payables') }}</option>
                        <option value="Hutang Lainnya" {{ $type == 'Hutang Lainnya' ? 'selected' : '' }}>{{ __('erp.other_payables') }}</option>
                        <option value="Hutang Jangka Panjang" {{ $type == 'Hutang Jangka Panjang' ? 'selected' : '' }}>{{ __('erp.long_term_liabilities') }}</option>
                        <option value="Modal" {{ $type == 'Modal' ? 'selected' : '' }}>{{ __('erp.equity_label') }}</option>
                        <option value="Pendapatan" {{ $type == 'Pendapatan' ? 'selected' : '' }}>{{ __('erp.revenue_label') }}</option>
                        <option value="Pendapatan Lainnya" {{ $type == 'Pendapatan Lainnya' ? 'selected' : '' }}>{{ __('erp.other_revenue') }}</option>
                        <option value="Harga Pokok Penjualan" {{ $type == 'Harga Pokok Penjualan' ? 'selected' : '' }}>{{ __('erp.cogs_label') }}</option>
                        <option value="Biaya" {{ $type == 'Biaya' ? 'selected' : '' }}>{{ __('erp.expense_label') }}</option>
                        <option value="Biaya Lainnya" {{ $type == 'Biaya Lainnya' ? 'selected' : '' }}>{{ __('erp.other_costs') }}</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.normal_balance_position_required') }}</label>
                    <select name="normal_balance" class="form-select" required>
                        <option value="DEBET" {{ $account->normal_balance == 'DEBET' ? 'selected' : '' }}>{{ __('erp.debit_caps') }}</option>
                        <option value="KREDIT" {{ $account->normal_balance == 'KREDIT' ? 'selected' : '' }}>{{ __('erp.credit_caps') }}</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.report_position_required') }}</label>
                    <select name="report_pos" class="form-select" required>
                        <option value="NERACA" {{ $account->report_pos == 'NERACA' ? 'selected' : '' }}>{{ __('erp.balance_sheet_caps') }}</option>
                        <option value="LABA RUGI" {{ $account->report_pos == 'LABA RUGI' ? 'selected' : '' }}>{{ __('erp.profit_loss_caps') }}</option>
                    </select>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('account.index') }}" class="btn btn-outline-secondary fw-bold px-4">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> {{ __('erp.save_changes') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
