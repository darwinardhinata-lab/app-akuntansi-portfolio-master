<div class="mb-2">
    <label class="form-label">{{ __('erp.fabric_coa_label') }}</label>
    <select name="inventory_account_code" class="form-select" required>
        <option value="">{{ __('erp.sales_semantic_choose') }}</option>
        @foreach(\App\Models\Account::whereIn('account_code', \App\Support\FabricInventoryAccount::CODES)->orderBy('account_code')->get() as $account)
            <option value="{{ $account->account_code }}" @selected((string) ($selectedFabricAccount ?? '') === (string) $account->account_code)>{{ $account->account_code }} — {{ $account->account_name }}</option>
        @endforeach
    </select>
    <small>{{ __('erp.fabric_coa_notice') }}</small>
</div>