@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('account.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_account_list_coa') => route('account.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-primary text-white border-bottom p-4">
            <h4 class="fw-bold mb-0"><i class="fa-solid fa-plus me-2"></i> {{ __('erp.coa_master_add_title') }}</h4>
        </div>
        <form action="{{ route('account.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.account_code_required') }}</label>
                    <input type="text" name="account_code" class="form-control fw-bold" placeholder="Contoh: 11101" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.account_name_required') }}</label>
                    <input type="text" name="account_name" class="form-control" placeholder="Contoh: Kas Besar" required>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.account_type_required') }}</label>
                    <select name="coa_type" class="form-select fw-bold text-primary" required>
                        <option value="">{{ __('erp.select_account_category') }}</option>
                        <option value="Cash & Bank">{{ __('erp.cash_and_bank') }}</option>
                        <option value="Piutang Dagang">{{ __('erp.trade_receivables') }}</option>
                        <option value="Persediaan">{{ __('erp.inventory_label') }}</option>
                        <option value="Aset Lancar Lainnya">{{ __('erp.other_current_assets') }}</option>
                        <option value="Aset Tetap">{{ __('erp.fixed_asset') }}</option>
                        <option value="Investasi Jangka Panjang">{{ __('erp.long_term_investment') }}</option>
                        <option value="Hutang Dagang">{{ __('erp.trade_payables') }}</option>
                        <option value="Hutang Lainnya">{{ __('erp.other_payables') }}</option>
                        <option value="Hutang Jangka Panjang">{{ __('erp.long_term_liabilities') }}</option>
                        <option value="Modal">{{ __('erp.equity_label') }}</option>
                        <option value="Pendapatan">{{ __('erp.revenue_label') }}</option>
                        <option value="Pendapatan Lainnya">{{ __('erp.other_revenue') }}</option>
                        <option value="Harga Pokok Penjualan">{{ __('erp.cogs_label') }}</option>
                        <option value="Biaya">{{ __('erp.expense_label') }}</option>
                        <option value="Biaya Lainnya">{{ __('erp.other_costs') }}</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.normal_balance_position_required') }}</label>
                    <select name="normal_balance" class="form-select" required>
                        <option value="DEBET">{{ __('erp.debit_caps') }}</option>
                        <option value="KREDIT">{{ __('erp.credit_caps') }}</option>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small text-muted">{{ __('erp.report_position_required') }}</label>
                    <select name="report_pos" class="form-select" required>
                        <option value="NERACA">{{ __('erp.balance_sheet_caps') }}</option>
                        <option value="LABA RUGI">{{ __('erp.profit_loss_caps') }}</option>
                    </select>
                </div>
            </div>
            
            <hr class="my-4">
            
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('account.index') }}" class="btn btn-outline-secondary fw-bold px-4">{{ __('erp.cancel') }}</a>
                <button type="submit" class="btn btn-primary fw-bold px-4"><i class="fa-solid fa-save me-1"></i> {{ __('erp.save_account') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection
