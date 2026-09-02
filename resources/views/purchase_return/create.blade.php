@extends('layouts.app')
@section('top_bar_left')
    <a href="{{ route('purchase-returns.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Pembelian' => '#', 'Retur Pembelian' => route('purchase-returns.index'), 'Buat Baru' => null]" />
@endsection
@section('content')
<div class="container-fluid max-w-4xl mx-auto mt-4 mb-5">
    <div class="card border-0 shadow-sm" style="border-radius: 12px;">
        <div class="card-header bg-danger text-white py-3">
            <h5 class="fw-bold m-0"><i class="fa-solid fa-arrow-rotate-left me-2"></i> Form Retur Pembelian (Debit Note)</h5>
        </div>
        <form action="{{ route('purchase-returns.store') }}" method="POST" class="card-body p-4">
            @csrf
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">No. Dokumen Retur</label>
                    <input type="text" name="return_number" class="form-control fw-bold text-danger" value="{{ $autoNumber }}" readonly>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Tanggal Retur *</label>
                    <input type="date" name="return_date" class="form-control fw-bold" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label fw-bold small text-muted">Pilih PO Asli *</label>
                    <select name="purchase_order_id" class="form-select fw-bold" required>
                        <option value="">-- Pilih PO --</option>
                        @foreach($pos as $po)
                            <option value="{{ $po->id }}">{{ $po->po_number }} - {{ $po->contact_name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>

            <div class="card mt-4 border border-info shadow-sm">
                <div class="card-header bg-light">
                    <h6 class="fw-bold m-0 text-dark"><i class="fa-solid fa-list-check me-2"></i>Pilih Barang yang Diretur</h6>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0" id="table-items">
                        <thead class="table-secondary text-muted text-center" style="font-size: 0.8rem;">
                            <tr>
                                <th width="25%">Item Code</th>
                                <th width="35%">Deskripsi</th>
                                <th width="15%">Harga Satuan</th>
                                <th width="10%">Qty di PO</th>
                                <th width="15%">Qty Diretur</th>
                            </tr>
                        </thead>
                        <tbody id="po-items-body">
                            <tr><td colspan="5" class="text-center py-4 text-muted">Silakan pilih PO Asli di atas terlebih dahulu.</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <hr class="my-4">
            <div class="d-flex justify-content-end gap-2">
                <a href="{{ route('purchase-returns.index') }}" class="btn btn-outline-secondary fw-bold px-4">Batal</a>
                <button type="submit" class="btn btn-danger fw-bold px-4"><i class="fa-solid fa-save me-1"></i> Simpan Draft Retur</button>
            </div>
        </form>
    </div>
</div>
@endsection

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function() {
        $('select[name="purchase_order_id"]').on('change', function() {
            let poId = $(this).val();
            let tbody = $('#po-items-body');
            
            if (!poId) {
                tbody.html('<tr><td colspan="5" class="text-center py-4 text-muted">Silakan pilih PO Asli di atas terlebih dahulu.</td></tr>');
                return;
            }

            tbody.html('<tr><td colspan="5" class="text-center py-4"><div class="spinner-border text-primary spinner-border-sm"></div> Memuat barang...</td></tr>');

            $.ajax({
                url: '/purchase-returns/get-po-items/' + poId,
                type: 'GET',
                success: function(res) {
                    tbody.empty();
                    if (res.items.length === 0) {
                        tbody.html('<tr><td colspan="5" class="text-center py-4 text-danger">Tidak ada barang pada PO ini.</td></tr>');
                        return;
                    }

                    res.items.forEach(item => {
                        let tr = `
                            <tr>
                                <td class="ps-3 fw-bold text-dark">${item.item_code}</td>
                                <td>${item.description || '-'}</td>
                                <td class="text-end font-monospace">Rp ${parseFloat(item.price).toLocaleString('id-ID')}</td>
                                <td class="text-center"><span class="badge bg-secondary">${item.qty}</span></td>
                                <td class="pe-3">
                                    <input type="number" name="items[${item.id}]" class="form-control form-control-sm text-center fw-bold border-danger text-danger" value="0" min="0" max="${item.qty}">
                                </td>
                            </tr>
                        `;
                        tbody.append(tr);
                    });
                },
                error: function() {
                    tbody.html('<tr><td colspan="5" class="text-center py-4 text-danger">Gagal menarik data PO.</td></tr>');
                }
            });
        });
    });
</script>