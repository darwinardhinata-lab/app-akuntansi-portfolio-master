@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.bc_ap_dp_report') => null]" />
@endsection

@section('content')
<div class="container-fluid px-4">
    <h4 class="fw-bold mb-4">{{ __('erp.ap_dp_report_title') }}</h4>
    <div class="row g-4">
        {{-- Hutang --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 border-top border-danger border-4">
                <div class="card-header bg-white"><h6 class="fw-bold m-0 text-danger">{{ __('erp.ap_recap') }}</h6></div>
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>{{ __('erp.account_code') }}</th><th>{{ __('erp.account') }}</th><th class="text-end">{{ __('erp.ending_balance') }}</th></tr></thead>
                    <tbody>
                        @foreach($hutang as $h)
                        <tr>
                            <td>{{ $h->account_code }}</td>
                            <td>
                                <a href="{{ route('reports.ap_subledger', ['account_code' => $h->account_code]) }}" class="fw-bold text-decoration-none text-danger">
                                    {{ $h->account_name }} <i class="fa-solid fa-arrow-up-right-from-square small ms-1" style="font-size: 0.7rem;"></i>
                                </a>
                            </td>
                            <td class="text-end fw-bold">Rp {{ number_format($h->balance,0,',','.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        {{-- Uang Muka Pembelian --}}
        <div class="col-md-6">
            <div class="card shadow-sm border-0 border-top border-info border-4">
                <div class="card-header bg-white"><h6 class="fw-bold m-0 text-info">{{ __('erp.supplier_dp_recap') }}</h6></div>
                <table class="table mb-0">
                    <thead class="table-light"><tr><th>{{ __('erp.account_code') }}</th><th>{{ __('erp.account') }}</th><th class="text-end">{{ __('erp.ending_balance') }}</th></tr></thead>
                    <tbody>
                        @foreach($uangMuka as $um)
                        <tr>
                            <td>{{ $um->account_code }}</td>
                            <td>
                                <a href="{{ route('reports.ap_subledger', ['account_code' => $um->account_code]) }}" class="fw-bold text-decoration-none text-info">
                                    {{ $um->account_name }} <i class="fa-solid fa-arrow-up-right-from-square small ms-1" style="font-size: 0.7rem;"></i>
                                </a>
                            </td>
                            <td class="text-end fw-bold">Rp {{ number_format($um->balance,0,',','.') }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
