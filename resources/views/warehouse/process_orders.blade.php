@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Warehouse' => '#', 'Proses Pesanan' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-boxes-packing text-primary me-2"></i>Dashboard Gudang: Proses Pesanan</h3>
            <p class="text-muted small mb-0">Daftar pesanan aktif yang menunggu proses (Picking, Packing, Shipping).</p>
        </div>
        <div>
            <span class="badge bg-warning text-dark px-3 py-2 fs-6 shadow-sm"><i class="fa-solid fa-stopwatch me-1"></i> {{ $orders->total() }} Pesanan</span>
        </div>
    </div>

    {{-- SUB-SUB-MENU / NAV PILLS --}}
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded border shadow-sm flex-nowrap overflow-auto" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'picking' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.process-orders', ['tab' => 'picking']) }}">Picking</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'packing' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.process-orders', ['tab' => 'packing']) }}">Packing</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'shipping' ? 'active' : 'text-muted' }}" href="{{ route('warehouse.process-orders', ['tab' => 'shipping']) }}">Shipping</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'sudah_dikirim' ? 'active bg-success' : 'text-muted' }}" href="{{ route('warehouse.process-orders', ['tab' => 'sudah_dikirim']) }}">Sudah Dikirim</a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'selesai' ? 'active bg-dark' : 'text-muted' }}" href="{{ route('warehouse.process-orders', ['tab' => 'selesai']) }}">Selesai</a>
        </li>
    </ul>

    <div class="row g-4">
        @forelse($orders as $o)
        <div class="col-12 col-md-6 col-xl-4">
            <div class="card h-100 border-0 shadow-sm" style="border-radius: 12px; border-left: 6px solid #f59e0b !important;">
                <div class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-start">
                    <div>
                        <span class="badge bg-light text-dark border mb-2"><i class="fa-solid fa-calendar-day me-1"></i> {{ \Carbon\Carbon::parse($o->transaction_date)->format('d M Y') }}</span>
                        <h5 class="fw-bold text-primary mb-1">{{ $o->so_number }}</h5>
                    </div>
                    <span class="badge bg-warning text-dark"><i class="fa-solid fa-box"></i> APPROVED</span>
                </div>
                <div class="card-body py-3">
                    <div class="mb-3">
                        <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.7rem;">Penerima / Pelanggan</small>
                        <span class="fw-bold text-dark fs-6">{{ $o->receiver_name ?? $o->contact_name }}</span>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="p-2 bg-light rounded border">
                                <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.65rem;">Kurir</small>
                                <span class="fw-bold text-dark">{{ $o->courier ?? 'Bawaan Sistem' }}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 bg-light rounded border">
                                <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 0.65rem;">Total Item</small>
                                <span class="fw-bold text-danger">{{ $o->details->sum('qty') }} Pcs</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0 pb-4">
                    <button type="button" class="btn btn-outline-primary fw-bold w-100" data-bs-toggle="modal" data-bs-target="#pickModal{{ $o->id }}">
                        <i class="fa-solid fa-list-check me-1"></i> Lihat Daftar Barang (Picking List)
                    </button>
                </div>
            </div>
        </div>

        {{-- Modal Picking List --}}
        <div class="modal fade" id="pickModal{{ $o->id }}" tabindex="-1">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 12px;">
                    <div class="modal-header bg-dark text-white py-3">
                        <h5 class="modal-title fw-bold"><i class="fa-solid fa-clipboard-list me-2"></i> Picking List: {{ $o->so_number }}</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body bg-light p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" style="font-size: 0.9rem;">
                                <thead class="table-secondary text-uppercase text-muted" style="font-size: 0.75rem;">
                                    <tr>
                                        <th class="ps-4 py-3" width="60%">SKU & Deskripsi Barang</th>
                                        <th class="text-center py-3" width="20%">Qty Dipesan</th>
                                        <th class="text-center pe-4 py-3" width="20%">Ceklis</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($o->details as $det)
                                    <tr>
                                        <td class="ps-4 py-3">
                                            <div class="fw-bold text-primary fs-6">{{ $det->item_code }}</div>
                                            <div class="text-dark">{{ $det->description }}</div>
                                        </td>
                                        <td class="text-center py-3">
                                            <span class="badge bg-danger fs-6 px-3 py-2">{{ $det->qty }}</span>
                                        </td>
                                        <td class="text-center pe-4 py-3">
                                            <input class="form-check-input" type="checkbox" style="width: 25px; height: 25px; cursor: pointer;">
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="p-4 bg-white border-top">
                            <div class="alert alert-info py-2 small mb-0">
                                <i class="fa-solid fa-info-circle me-1"></i> Setelah barang selesai di-packing, instruksikan bagian Finance/Admin untuk memproses resi dan menerbitkan Faktur Penjualan di menu <b>Sales Orders</b>.
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-white py-3">
                        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Tutup</button>
                    </div>
                </div>
            </div>
        </div>
        @empty
        <div class="col-12">
            <div class="text-center py-5">
                <i class="fa-solid fa-box-open fa-3x text-muted mb-3 d-block"></i>
                <h5 class="fw-bold text-secondary">Gudang Kosong</h5>
                <p class="text-muted">Tidak ada pesanan penjualan yang menunggu untuk diproses saat ini.</p>
            </div>
        </div>
        @endforelse
    </div>

    <div class="mt-4 d-flex justify-content-center">
        {{ $orders->links() }}
    </div>
</div>
@endsection