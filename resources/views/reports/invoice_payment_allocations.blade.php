@extends('layouts.app')

@section('content')
<div class="container-fluid px-4 mt-4">
    <h4>{{ __('erp.audit_ar_allocation_title') }}</h4>
    <p class="text-muted">{{ __('erp.audit_ar_allocation_help') }}</p>
    @if(session('success'))
        <div class="alert alert-success" role="status">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="alert alert-danger" role="alert"><ul class="mb-0">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul></div>
    @endif
    <form method="GET" action="{{ route('invoice-payment-allocations.index') }}" class="card card-body mb-3">
        <label for="q" class="form-label">{{ __('erp.audit_ar_search') }}</label>
        <input id="q" name="q" maxlength="100" class="form-control mb-2" value="{{ request('q') }}">
        <button type="submit" class="btn btn-outline-primary">{{ __('erp.audit_ar_search') }}</button>
    </form>
    <ul class="list-group mb-3">
        @forelse($invoices as $invoice)
            <li class="list-group-item"><a href="{{ route('invoice-payment-allocations.index', ['invoice' => $invoice->id, 'q' => request('q')]) }}">{{ $invoice->invoice_number }} — {{ $invoice->contact_name }}</a></li>
        @empty
            <li class="list-group-item">{{ __('erp.ar_sub_no_data') }}</li>
        @endforelse
    </ul>
    {{ $invoices->links() }}
    @if($selectedInvoice)
    <form method="GET" action="{{ route('invoice-payment-allocations.index') }}" class="card card-body mb-3">
        <input type="hidden" name="invoice" value="{{ $selectedInvoice->id }}">
        <input type="hidden" name="q" value="{{ request('q') }}">
        <label for="journal_q" class="form-label">{{ __('erp.audit_ar_journal_id') }}</label>
        <input id="journal_q" name="journal_q" maxlength="100" class="form-control mb-2" value="{{ request('journal_q') }}">
        <button type="submit" class="btn btn-outline-primary">{{ __('erp.search') }}</button>
    </form>
    <form action="{{ route('invoice-payment-allocations.store') }}" method="POST" class="card card-body mb-4">
        @csrf
        <div class="mb-3">
            <label for="sales_invoice_id" class="form-label">{{ __('erp.audit_ar_invoice_id') }}</label>
            <input id="sales_invoice_id" name="sales_invoice_id" type="hidden" value="{{ $selectedInvoice->id }}">
            <p>{{ $selectedInvoice->invoice_number }} — {{ $selectedInvoice->contact_name }}</p>
        </div>
        <div class="mb-3">
            <label for="journal_id" class="form-label">{{ __('erp.audit_ar_journal_id') }}</label>
            <select id="journal_id" name="journal_id" required class="form-select">
                <option value="">{{ __('erp.audit_ar_journal_id') }}</option>
                @foreach($journals as $journal)
                    <option value="{{ $journal->getKey() }}" @selected(old('journal_id') === $journal->getKey())>{{ $journal->getKey() }} — {{ $journal->transaction_date }} — {{ $journal->evidence_number }}</option>
                @endforeach
            </select>
            <small class="text-muted">{{ __('erp.audit_ar_candidates') }}</small>
        </div>
        <button class="btn btn-primary" type="submit">{{ __('erp.audit_ar_allocate') }}</button>
    </form>
    {{ $journals->links() }}
    @endif
    <h5>{{ __('erp.audit_ar_history') }}</h5>
    <div class="table-responsive"><table class="table table-striped">
        <thead><tr><th>{{ __('erp.invoice_no') }}</th><th>{{ __('erp.audit_ar_journal_id') }}</th><th>{{ __('erp.transaction_amount') }}</th><th>{{ __('erp.audit_ar_operator') }}</th><th>{{ __('erp.ar_sub_date') }}</th></tr></thead>
        <tbody>
        @forelse($allocations as $allocation)
            <tr><td>{{ $allocation->invoice_number }}</td><td>{{ $allocation->journal_id }}</td><td>{{ number_format($allocation->amount, 2, ',', '.') }}</td><td>{{ $allocation->operator_name ?? $allocation->allocated_by }}</td><td>{{ $allocation->created_at }}
                @if($allocation->reversed_at)
                    <div>{{ $allocation->reversal_journal_id }} — {{ $allocation->reversed_at }} — {{ $allocation->reversed_by }}</div>
                    <div>{{ $allocation->reversal_reason }}</div>
                @elseif(auth()->user()->role === 'FINANCE' && in_array((string) auth()->id(), array_map('strval', config('platform.payment_reverse_user_ids', [])), true))
                    <form method="POST" action="{{ route('invoice-payment-allocations.reverse', $allocation->id) }}" class="mt-2">
                        @csrf
                        <label for="reason-{{ $allocation->id }}">{{ __('erp.audit_ar_reason') }}</label>
                        <input id="reason-{{ $allocation->id }}" name="reason" required minlength="10" maxlength="1000" class="form-control">
                        <button type="submit" class="btn btn-sm btn-outline-danger mt-1">{{ __('erp.audit_ar_reverse') }}</button>
                    </form>
                @endif
            </td></tr>
        @empty
            <tr><td colspan="5">{{ __('erp.ar_sub_no_data') }}</td></tr>
        @endforelse
        </tbody>
    </table></div>
    {{ $allocations->links() }}
</div>
@endsection