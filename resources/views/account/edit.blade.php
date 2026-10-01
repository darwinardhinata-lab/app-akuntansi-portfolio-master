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
                    <input type="text" name="coa_type" list="coaTypes" class="form-control fw-bold text-primary" value="{{ $account->coa_type }}" placeholder="{{ __('erp.select_account_category') }}" required autocomplete="off">
                    <datalist id="coaTypes">
                        @foreach($coaTypes as $typeOption)
                            <option value="{{ $typeOption }}"></option>
                        @endforeach
                    </datalist>
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
