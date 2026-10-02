@php
    $categoryAccounts = \App\Models\Account::whereIn('account_code', array_keys(config('asset_coa.categories')))->orderBy('account_code')->get();
    $expenseAccounts = \App\Models\Account::whereIn('account_code', config('asset_coa.expenses'))->orderBy('account_code')->get();
@endphp
<label class="form-label">{{ __('erp.asset_category_coa') }}</label>
<select name="category" class="form-select form-select-sm" required>
    <option value="">{{ __('erp.sales_semantic_choose') }}</option>
    @foreach($categoryAccounts as $account)
        <option value="{{ $account->account_code }}" @selected((string) $asset->category === (string) $account->account_code)>{{ $account->account_code }} — {{ $account->account_name }}</option>
    @endforeach
</select>
<label class="form-label mt-1">{{ __('erp.asset_expense_coa') }}</label>
<select name="depreciation_expense_code" class="form-select form-select-sm">
    <option value="">{{ __('erp.asset_non_depreciable') }}</option>
    @foreach($expenseAccounts as $account)
        <option value="{{ $account->account_code }}" @selected((string) $asset->depreciation_expense_code === (string) $account->account_code)>{{ $account->account_code }} — {{ $account->account_name }}</option>
    @endforeach
</select>