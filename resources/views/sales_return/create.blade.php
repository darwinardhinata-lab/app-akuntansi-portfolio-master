@extends('layouts.app')

@section('title', 'Buat Retur Penjualan')

@section('top_bar_left')
    <a href="{{ route('sales-returns.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Penjualan' => '#', 'Retur Penjualan' => route('sales-returns.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    <form action="{{ route('sales-returns.store') }}" method="POST" id="form-return">
        @csrf

        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white p-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-arrow-rotate-left text-danger me-2"></i> Informasi Retur Penjualan</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">No. Retur <span class="text-danger">*</span></label>
                        <input type="text" name="return_number" class="form-control fw-bold text-danger" value="{{ old('return_number', $autoNumber) }}" readonly required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Tanggal Retur <span class="text-danger">*</span></label>
                        <input type="date" name="return_date" class="form-control" value="{{ old('return_date', date('Y-m-d')) }}" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Pilih Faktur Penjualan <span class="text-danger">*</span></label>
                        <select name="sales_invoice_id" id="select-invoice" class="form-select fw-bold" required>
                            <option value="">-- Pilih Faktur --</option>
                            @foreach($invoices as $inv)
                                <option value="{{ $inv['id'] }}" {{ old('sales_invoice_id') == $inv['id'] ? 'selected' : '' }}>
                                    {{ $inv['invoice_number'] }} - {{ $inv['contact_name'] }} ({{ $inv['transaction_date'] }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Pelanggan</label>
                        <input type="text" name="contact_name" id="input-contact" class="form-control" value="{{ old('contact_name') }}" readonly>
                    </div>
                </div>
            </div>
        </div>

        {{-- DETAIL BARANG --}}
        <div class="card border-0 shadow-sm rounded-3 mb-3">
            <div class="card-header bg-white p-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-clipboard-list me-2"></i> Detail Barang Retur</h5>
                <small class="text-muted">Centang / isi Qty Retur untuk barang yang diretur</small>
            </div>
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-dark">
                        <tr>
                            <th class="ps-3 py-3" style="width: 5%;">No</th>
                            <th class="py-3" style="width: 18%;">Kode Barang</th>
                            <th class="py-3" style="width: 25%;">Deskripsi</th>
                            <th class="text-center py-3" style="width: 12%;">Harga Jual</th>
                            <th class="text-center py-3" style="width: 8%;">Qty Faktur</th>
                            <th class="text-center py-3" style="width: 12%;">Qty Retur</th>
                        </tr>
                    </thead>
                    <tbody id="items-body">
                        <tr id="no-data-row">
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fa-solid fa-hand-pointer me-1"></i> Pilih Faktur Penjualan untuk memuat barang.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="{{ route('sales-returns.index') }}" class="btn btn-lg btn-light fw-bold px-4">Batal</a>
            <button type="submit" class="btn btn-lg btn-primary fw-bold px-5 shadow-sm">
                <i class="fa-solid fa-save me-1"></i> Simpan & Lanjut Pemeriksaan
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script>
    // --- SELECT INVOICE -> LOAD ITEMS ---
    $('#select-invoice').change(function() {
        let invId = $(this).val();
        $('#items-body').html(`
            <tr id="no-data-row">
                <td colspan="6" class="text-center py-4 text-muted">
                    <i class="fa-solid fa-spinner fa-spin me-1"></i> Memuat data...
                </td>
            </tr>
        `);
        $('#input-contact').val('');

        if (!invId) {
            $('#items-body').html(`
                <tr id="no-data-row">
                    <td colspan="6" class="text-center py-4 text-muted">Pilih Faktur Penjualan untuk memuat barang.</td>
                </tr>
            `);
            return;
        }

        $.get('{{ url("sales-returns/get-invoice-items") }}/' + invId, function(res) {
            $('#input-contact').val(res.contact_name);
            
            if (!res.items || res.items.length === 0) {
                $('#items-body').html(`
                    <tr id="no-data-row">
                        <td colspan="6" class="text-center py-4 text-muted">Tidak ada barang pada faktur ini.</td>
                    </tr>
                `);
                return;
            }

            let html = '';
            res.items.forEach(function(item, idx) {
                html += `
                <tr>
                    <td class="ps-3 text-center">${idx + 1}</td>
                    <td class="fw-bold">${item.item_code}
                        <input type="hidden" name="details[${idx}][item_code]" value="${item.item_code}">
                        <input type="hidden" name="details[${idx}][price]" value="${item.price}">
                        <input type="hidden" name="details[${idx}][description]" value="${item.description || ''}">
                    </td>
                    <td>${item.description || '-'}</td>
                    <td class="text-end">Rp ${parseFloat(item.price).toLocaleString('id-ID')}</td>
                    <td class="text-center fw-bold">${item.qty}</td>
                    <td class="text-center">
                        <input type="number" name="details[${idx}][qty_returned]" class="form-control form-control-sm text-center fw-bold" value="0" min="0" max="${item.qty}" style="width: 80px; margin: 0 auto;">
                    </td>
                </tr>`;
            });
            $('#items-body').html(html);
        });
    });
</script>
@endpush