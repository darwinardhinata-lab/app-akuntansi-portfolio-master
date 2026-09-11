@extends('layouts.app')
@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_warehouse') => '#', __('erp.bc_goods_in') => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-arrow-right-to-bracket text-success me-2"></i>{{ __('erp.goods_in_inbound') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.goods_receipt_history_hint') }}</p>
        </div>
        <div>
            <a href="{{ route('warehouse.inbound.create') }}" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> {{ __('erp.create_receipt_btn') }}
            </a>
        </div>
    </div>

    {{-- SUB-SUB-MENU / NAV PILLS --}}
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded border shadow-sm flex-nowrap overflow-auto" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'penerimaan_barang' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'penerimaan_barang']) }}">{{ __('erp.goods_receipt_bil') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'pesanan_pembelian' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'pesanan_pembelian']) }}">{{ __('erp.purchase_order_po_label') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'retur_online' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'retur_online']) }}">{{ __('erp.online_channel_return') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'transfer_masuk' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'transfer_masuk']) }}">{{ __('erp.transfer_in') }}</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-3 {{ $tab == 'penempatan_barang' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.inbound', ['tab' => 'penempatan_barang']) }}">{{ __('erp.goods_placement') }}</a>
        </li>
    </ul>

    @if(session('success')) <div class="alert alert-success fw-bold shadow-sm">{{ session('success') }}</div> @endif
    @if(session('error')) <div class="alert alert-danger fw-bold shadow-sm">{{ session('error') }}</div> @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">{{ __('erp.date') }}</th>
                        <th class="py-3">{{ __('erp.ref_no') }}</th>
                        <th class="py-3">{{ __('erp.product_sku') }}</th>
                        <th class="text-center py-3">{{ __('erp.qty_in') }}</th>
                        <th class="text-end pe-4 py-3">{{ __('erp.cogs_per_unit_rp') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgers as $l)
                    <tr>
                        <td class="ps-4 fw-medium">{{ \Carbon\Carbon::parse($l->transaction_date)->format('d M Y') }}</td>
                        <td class="fw-bold text-primary">{{ $l->evidence_number }}</td>
                        <td class="fw-bold text-dark">{{ $l->product->sku ?? '-' }} <br><span class="text-muted small fw-normal">{{ $l->product->name ?? '-' }}</span></td>
                        <td class="text-center fw-bold text-success">+{{ $l->qty }}</td>
                        <td class="text-end pe-4 font-monospace">{{ number_format($l->unit_cost, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="text-center py-5 text-muted">{{ __('erp.no_goods_in_history') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">{{ $ledgers->links() }}</div>
    </div>
</div>
@endsection
