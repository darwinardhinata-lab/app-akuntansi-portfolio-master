@extends('layouts.app')
@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_purchasing') => '#', __('erp.bc_bills') => null]" />
@endsection
@section('content')
<div class="container-fluid px-0">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.purchase_bills_module') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.record_supplier_bill_daily_cost_hint') }}</p>
        </div>
        <div class="d-flex gap-2">
            <form action="{{ route('purchase-bills.sync_temp') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning fw-bold shadow-sm" onclick="return confirm('Mulai sinkronisasi Bills dari Dashboard? Proses ini berjalan di background.')">
                    <i class="fa-solid fa-sync me-1"></i> Sync Dashboard
                </button>
            </form>
            <a href="{{ route('purchase-bills.create') }}" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat Tagihan Baru
            </a>
        </div>
    </div>

    @if(session('success')) <div class="alert alert-success shadow-sm fw-bold">{{ session('success') }}</div> @endif

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">{{ __('erp.date') }}</th>
                        <th>{{ __('erp.bill_no') }}</th>
                        <th>{{ __('erp.supplier_vendor') }}</th>
                        <th class="text-end">{{ __('erp.total_bill_rp') }}</th>
                        <th class="text-center">{{ __('erp.status') }}</th>
                        <th class="text-center pe-4">{{ __('erp.action') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($bills as $b)
                    <tr>
                        <td class="ps-4 fw-medium">{{ date('d M Y', strtotime($b->transaction_date)) }}</td>
                        <td class="fw-bold text-primary">{{ $b->bill_number }}</td>
                        <td class="fw-bold text-dark">{{ $b->contact_name }}</td>
                        <td class="text-end fw-bold text-danger">Rp {{ number_format($b->grand_total, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($b->payment_status == 'PAID') <span class="badge bg-success">{{ __('erp.status_paid_off') }}</span>
                            @else <span class="badge bg-warning text-dark">{{ __('erp.status_unpaid') }}</span> @endif
                        </td>
                        <td class="text-center pe-4">
                            <div class="btn-group">
                                <button type="button" onclick="showEntityLog('{{ $b->bill_number }}')" class="btn btn-sm btn-outline-secondary" title="{{ __('erp.activity_log') }}"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                <form action="{{ route('purchase-bills.destroy', $b->id) }}" method="POST" onsubmit="return confirm('Hapus tagihan ini? Jurnal akan dibatalkan.')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="{{ __('erp.delete_btn') }}" style="border-top-left-radius: 0; border-bottom-left-radius: 0;"><i class="fa-solid fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" class="text-center py-5 text-muted">{{ __('erp.no_purchase_bill_data') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $bills->links() }}</div>
    </div>
</div>
@endsection
