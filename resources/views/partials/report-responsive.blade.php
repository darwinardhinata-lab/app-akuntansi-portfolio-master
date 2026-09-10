@once
@push('styles')
<style>
@media (max-width: 768px) {
    .report-wrapper > .d-flex.justify-content-between {
        flex-direction: column;
        align-items: stretch !important;
    }
    .report-wrapper .no-print .btn {
        flex: 1 1 auto;
    }
    .report-wrapper .table-container {
        margin: 0 -4px;
    }
    .report-wrapper .table-container::after {
        content: '{{ __('erp.scroll_table_hint') }}';
        display: block;
        text-align: center;
        font-size: 0.72rem;
        color: #64748b;
        padding: 8px 4px 4px;
    }
}
</style>
@endpush
@endonce
