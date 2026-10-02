<div class="mb-2">
    <label class="form-label">{{ __('erp.maklun_liability') }}</label>
    <select name="liability_account_code" class="form-select" required>
        <option value="">{{ __('erp.sales_semantic_choose') }}</option>
        @foreach(\App\Models\Account::whereIn('account_code', ['212001'])->orderBy('account_code')->get() as $account)
            <option value="{{ $account->account_code }}">{{ $account->account_code }} — {{ $account->account_name }}</option>
        @endforeach
    </select>
    <label class="form-check mt-2"><input type="checkbox" name="full_completion" value="1" class="form-check-input" required><span class="form-check-label">{{ __('erp.maklun_full_completion') }}</span></label>
</div>