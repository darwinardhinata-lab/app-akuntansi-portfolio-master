@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.master_data') => '#', __('erp.bc_product_ledger') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="mb-4">
        <h3 class="fw-bold mb-1 text-dark">{{ __('erp.stock_card_inventory_ledger') }}</h3>
        <p class="text-muted small mb-0">{{ __('erp.monitor_stock_movement_cogs_hint') }}</p>
    </div>

    <form action="{{ route('inventory.ledger') }}" method="GET" class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="row g-2 align-items-end">
            <div class="col-md-5">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.select_item_sku') }}</label>
                <select name="product_id" class="form-select form-select-sm" required>
                    @foreach($products as $p)
                        <option value="{{ $p->id }}" {{ $productId == $p->id ? 'selected' : '' }}>
                            [{{ $p->sku }}] - {{ $p->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_full') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>
            <div class="col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_full') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>
            <div class="col-md-1">
                <button type="submit" class="btn btn-sm btn-primary w-100 fw-bold"><i class="fa-solid fa-filter"></i></button>
            </div>
        </div>
    </form>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="card-header bg-white border-bottom p-3 d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0 fw-bold text-primary">{{ $selectedProduct->name ?? '-' }}</h6>
                <small class="text-muted">SKU: {{ $selectedProduct->sku ?? '-' }}</small>
            </div>
            <div class="text-end">
                <small class="d-block text-muted fw-bold text-uppercase" style="font-size: 0.7rem;">{{ __('erp.cogs_current_average_value') }}</small>
                <span class="badge bg-dark fw-bold fs-6">Rp {{ number_format($selectedProduct->average_cost ?? 0, 2, ',', '.') }}</span>
            </div>
        </div>
        <div class="table-responsive bg-white rounded-bottom-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-light text-muted text-uppercase" style="font-size: 0.75rem;">
                    <tr>
                        <th class="ps-3 py-3">{{ __('erp.date') }}</th>
                        <th>{{ __('erp.proof_no') }}</th>
                        <th>{{ __('erp.description') }}</th>
                        <th class="text-center">{{ __('erp.mfg_ledger_in') }}</th>
                        <th class="text-center">{{ __('erp.logout') }}</th>
                        <th class="text-end">{{ __('erp.unit_price_cogs') }}</th>
                        <th class="text-center bg-light border-start">{{ __('erp.remaining_stock') }}</th>
                        <th class="text-end bg-light pe-3">{{ __('erp.value_balance_rp') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ledgers as $ledger)
                    <tr>
                        <td class="ps-3 fw-medium">{{ date('d/m/Y', strtotime($ledger->transaction_date)) }}</td>
                        <td><span class="badge bg-light text-dark border">{{ $ledger->evidence_number }}</span></td>
                        <td class="text-wrap" style="min-width: 200px;">{{ $ledger->description }}</td>
                        
                        <td class="text-center fw-bold text-success">{{ $ledger->type == 'IN' ? '+'.$ledger->qty : '-' }}</td>
                        <td class="text-center fw-bold text-danger">{{ $ledger->type == 'OUT' ? '-'.$ledger->qty : '-' }}</td>
                        
                        <td class="text-end font-monospace">{{ number_format($ledger->unit_cost, 2, ',', '.') }}</td>
                        
                        <td class="text-center bg-light border-start fw-bold text-primary">{{ $ledger->running_qty }}</td>
                        <td class="text-end bg-light pe-3 fw-bold">{{ number_format($ledger->running_value, 2, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="8" class="text-center py-5 text-muted">{{ __('erp.no_stock_movement_history_period') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
