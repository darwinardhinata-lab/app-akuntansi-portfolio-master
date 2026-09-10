@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('invoice.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.bc_sales') => '#', __('erp.sales_invoice') => route('invoice.index'), __('erp.bc_detail') => null]" />
@endsection

@section('content')
<div class="container-fluid mx-auto mt-4 mb-5" style="max-width: 1400px;">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="fw-bold mb-0 text-dark">{{ __('erp.sales_invoice_detail') }}</h4>
        <div>
            <a href="{{ route('invoice.index') }}" class="btn btn-outline-secondary fw-bold px-3 me-2">{{ __('erp.back_to_list_caps') }}</a>
            <a href="{{ route('invoice.create') }}" class="btn btn-primary fw-bold px-4 shadow-sm"><i class="fa-solid fa-plus me-1"></i> {{ __('erp.bc_create_new') }}</a>
        </div>
    </div>

    <div class="row g-3">
        {{-- KOLOM KIRI: Informasi Transaksi --}}
        <div class="col-lg-8">
            
            {{-- 1. INFORMASI UTAMA --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold m-0 text-primary"><i class="fa-solid fa-file-invoice-dollar me-2"></i>{{ __('erp.invoice_transaction') }}</h6>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.invoice_number') }}</label>
                            <div class="form-control-plaintext fw-bold text-primary fs-5">{{ $invoice->invoice_number }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.date') }}</label>
                            <div class="form-control-plaintext fw-bold">{{ date('d M Y', strtotime($invoice->transaction_date)) }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.customer_label') }}</label>
                            <div class="form-control-plaintext fw-bold">{{ $invoice->contact_name }}</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.ref_so_no') }}</label>
                            <div class="form-control-plaintext">{{ $invoice->salesOrder->so_number ?? '-' }}</div>
                        </div>
                    </div>
                </div>
            </div>


            {{-- 2. RINCIAN BARANG --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold m-0 text-primary"><i class="fa-solid fa-boxes-stacked me-2"></i>{{ __('erp.invoice_item_detail') }}</h6>
                    <span class="badge bg-primary-subtle text-primary fw-bold">{{ $invoice->details->count() }} Item</span>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="ps-3 py-3 small fw-bold text-muted">{{ __('erp.bc_product') }}</th>
                                    <th class="py-3 small fw-bold text-muted text-end">{{ __('erp.price_label') }}</th>
                                    <th class="py-3 small fw-bold text-muted text-center">{{ __('erp.qty') }}</th>
                                    <th class="py-3 small fw-bold text-muted text-end">{{ __('erp.disc_abbr') }}</th>
                                    <th class="pe-3 py-3 small fw-bold text-muted text-end">{{ __('erp.total') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($invoice->details as $det)
                                <tr>
                                    <td class="ps-3">
                                        <div class="fw-bold text-dark">{{ $det->item_code }}</div>
                                        <div class="small text-muted">{{ $det->product->name ?? $det->description ?? '-' }}</div>
                                    </td>
                                    <td class="text-end">{{ number_format($det->price, 0, ',', '.') }}</td>
                                    <td class="text-center">{{ $det->qty_actual }}</td>
                                    <td class="text-end text-danger">{{ number_format($det->disc_amount, 0, ',', '.') }}</td>
                                    <td class="pe-3 text-end fw-bold text-primary">Rp {{ number_format($det->amount, 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                            <tfoot class="bg-light">
                                <tr>
                                    <td colspan="4" class="text-end py-3 fw-bold text-muted">{{ __('erp.sub_total_label') }}</td>
                                    <td class="pe-3 py-3 text-end fw-bold text-primary">Rp {{ number_format($invoice->sub_total, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-2 fw-bold text-muted">{{ __('erp.item_deduction') }}</td>
                                    <td class="pe-3 py-2 text-end fw-bold text-danger">- Rp {{ number_format($invoice->disc_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-2 fw-bold text-muted">{{ __('erp.other_deduction') }}</td>
                                    <td class="pe-3 py-2 text-end fw-bold text-danger">- Rp {{ number_format($invoice->other_discount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-2 fw-bold text-muted">{{ __('erp.tax_vat_paren') }}</td>
                                    <td class="pe-3 py-2 text-end fw-bold text-primary">+ Rp {{ number_format($invoice->tax_amount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-2 fw-bold text-muted">{{ __('erp.shipping_cost') }}</td>
                                    <td class="pe-3 py-2 text-end fw-bold text-primary">+ Rp {{ number_format($invoice->shipping_cost, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-2 fw-bold text-muted">{{ __('erp.shipping_disc_abbr') }}</td>
                                    <td class="pe-3 py-2 text-end fw-bold text-danger">- Rp {{ number_format($invoice->shipping_discount, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-2 fw-bold text-muted">{{ __('erp.other_costs_misc') }}</td>
                                    <td class="pe-3 py-2 text-end fw-bold text-primary">+ Rp {{ number_format($invoice->other_cost, 0, ',', '.') }}</td>
                                </tr>
                                <tr>
                                    <td colspan="4" class="text-end py-3 fs-5 fw-bold text-dark">{{ __('erp.grand_total_caps') }}</td>
                                    <td class="pe-3 py-3 text-end fs-5 fw-bold text-primary">Rp {{ number_format($invoice->grand_total, 0, ',', '.') }}</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

        </div> {{-- End Col-lg-8 --}}

        {{-- KOLOM KANAN: Informasi Tambahan --}}
        <div class="col-lg-4">
            
            {{-- 3. PENGIRIMAN & PENERIMA (DIAMBIL DARI SO) --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold m-0 text-primary"><i class="fa-solid fa-truck me-2"></i>{{ __('erp.shipping_information') }}</h6>
                </div>
                <div class="card-body">
                    @if($invoice->salesOrder)
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.recipient_name') }}</label>
                            <div class="form-control-plaintext fw-bold">{{ $invoice->salesOrder->receiver_name ?? '-' }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.shipping_address') }}</label>
                            <div class="form-control-plaintext">{{ $invoice->salesOrder->receiver_address ?? '-' }}</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold small text-muted">{{ __('erp.phone_no') }}</label>
                            <div class="form-control-plaintext">{{ $invoice->salesOrder->receiver_phone ?? '-' }}</div>
                        </div>
                        <hr>
                        <div class="row g-3">
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.courier_label') }}</label>
                                <div class="form-control-plaintext">{{ $invoice->salesOrder->courier ?? '-' }}</div>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-bold small text-muted">{{ __('erp.tracking_no_short') }}</label>
                                <div class="form-control-plaintext">{{ $invoice->salesOrder->tracking_number ?? '-' }}</div>
                            </div>
                        </div>
                    @else
                        <div class="text-center py-3 text-muted small">{{ __('erp.no_shipping_data_manual') }}</div>
                    @endif
                </div>
            </div>

            {{-- 4. STATUS & CATATAN --}}
            <div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
                <div class="card-header bg-white border-bottom py-3">
                    <h6 class="fw-bold m-0 text-primary"><i class="fa-solid fa-circle-info me-2"></i>{{ __('erp.status_notes') }}</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.invoice_status') }}</label>
                        <div>
                            <span class="badge bg-success fs-6">{{ __('erp.issued_label') }}</span>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-bold small text-muted">{{ __('erp.description') }}</label>
                        <div class="form-control-plaintext">{{ $invoice->remarks ?? '-' }}</div>
                    </div>
                </div>
            </div>

            {{-- TOMBOL DELETE FAKTUR --}}
            <div class="d-flex justify-content-between mt-4 border-top pt-3 gap-2">
                <a href="{{ route('invoice.index') }}" class="btn btn-light fw-bold px-4 shadow-sm border">
                    <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_to') }} {{ __('erp.list_label') }}</a>
                
                <form action="{{ route('invoice.destroy', $invoice->id) }}" method="POST" onsubmit="return confirm('PERINGATAN: Menghapus faktur ini akan membatalkan Jurnal Keuangan dan mengembalikan Stok Barang ke Gudang. Yakin ingin menghapus?')">
                    @csrf @method('DELETE')
                    <button type="submit" class="btn btn-danger fw-bold px-4 shadow-sm">
                        <i class="fa-solid fa-trash me-1"></i> Hapus Faktur & Batalkan Transaksi
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
