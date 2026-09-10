@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_tag_report') => null]" />
@endsection

@section('content')
<div class="container-fluid px-4">
    <h4 class="fw-bold mb-4">{{ __('erp.profit_loss_by_tag_report') }}</h4>
    
    <form method="GET" class="mb-4 d-flex gap-2 w-50">
        <input type="text" name="tag" class="form-control" placeholder="{{ __('erp.search_tag') }}" value="{{ request('tag') }}" required>
        <button type="submit" class="btn btn-primary fw-bold">{{ __('erp.search_tag') }}</button>
    </form>

    @if($tag)
    <div class="card shadow-sm border-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 text-nowrap">
                <thead class="table-dark">
                    <tr>
                        <th>{{ __('erp.date') }}</th>
                        <th>{{ __('erp.evidence_no') }}</th>
                        <th>{{ __('erp.account') }}</th>
                        <th>{{ __('erp.description_tag') }}</th>
                        <th class="text-end text-success">{{ __('erp.debit_rp_en') }}</th>
                        <th class="text-end text-danger">{{ __('erp.credit_rp') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @php $totDebit = 0; $totKredit = 0; @endphp
                    @foreach($transactions as $t)
                        @php $totDebit += $t->debit; $totKredit += $t->credit; @endphp
                        <tr>
                            <td>{{ \Carbon\Carbon::parse($t->transaction_date)->format('d/m/Y') }}</td>
                            <td>{{ $t->evidence_number }}</td>
                            <td class="fw-bold">{{ $t->account_name }}</td>
                            <td>
                                {{ $t->header_desc }} 
                                {!! $t->tags ? '<br><span class="badge bg-info text-dark mt-1">Tag: ' . $t->tags . '</span>' : '' !!}
                            </td>
                            <td class="text-end">{{ number_format($t->debit, 0, ',', '.') }}</td>
                            <td class="text-end">{{ number_format($t->credit, 0, ',', '.') }}</td>
                        </tr>
                    @endforeach
                    <tr class="table-light fw-bold text-end">
                        <td colspan="4">{{ __('erp.total_caps') }}</td>
                        <td class="text-success">{{ number_format($totDebit, 0, ',', '.') }}</td>
                        <td class="text-danger">{{ number_format($totKredit, 0, ',', '.') }}</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection
