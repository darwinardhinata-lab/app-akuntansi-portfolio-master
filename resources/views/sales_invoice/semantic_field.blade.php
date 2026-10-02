<div class="mb-3">
    <label class="form-label fw-bold">{{ __('erp.sales_semantic_label') }}</label>
    <select name="sales_semantic" class="form-select" required>
        <option value="">{{ __('erp.sales_semantic_choose') }}</option>
        <option value="LOCAL" @selected(old('sales_semantic') === 'LOCAL')>{{ __('erp.sales_semantic_local') }}</option>
        <option value="EXPORT" @selected(old('sales_semantic') === 'EXPORT')>{{ __('erp.sales_semantic_export') }}</option>
    </select>
    <small class="text-muted">{{ __('erp.sales_semantic_notice') }}</small>
</div>