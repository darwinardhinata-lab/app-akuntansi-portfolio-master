@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('payment.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.payment_plan') => route('payment.index'), __('erp.bc_create_new') => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />
<div class="container-fluid px-0">
    <div class="card shadow-sm border-primary">
        <div class="card-header bg-primary text-white">
            <h5 class="m-0 fw-bold"><i class="fas fa-file-invoice-dollar me-2"></i> {{ __('erp.payment_plan_form_title') }}</h5>
        </div>
        <div class="card-body">
            <form action="{{ route('payment.store') }}" method="POST" enctype="multipart/form-data" id="form-payment">
                @csrf
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.select_division_required') }}</label>
                        <select name="id_divisi" class="form-select" required>
                            <option value="">{{ __('erp.select_division_ph') }}</option>
                            @foreach($divisi as $div)
                                <option value="{{ $div->id_divisi }}">{{ $div->nama_divisi }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.ops_account_required') }}</label>
                        <select name="jenis_transaksi" class="form-select" required>
                            <option value="">{{ __('erp.select_account_bank_ph') }}</option>
                            <option value="BCA BBW OPS">{{ __('erp.bank_bca_bbw_ops') }}</option>
                            <option value="BCA BBB OPS">{{ __('erp.bank_bca_bbb_ops') }}</option>
                            <option value="BCA KOI OPS">{{ __('erp.bank_bca_koi_ops') }}</option>
                            <option value="BCA GBB OPS">{{ __('erp.bank_bca_gbb_ops') }}</option>
                            <option value="MANDIRI BBW">{{ __('erp.bank_mandiri_bbw') }}</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.payment_category_required') }}</label>
                        <select name="kategori_payment" id="kategori_payment" class="form-select fw-bold text-primary" required>
                            <option value="">{{ __('erp.select_category_ph') }}</option>
                            @foreach($payment_categories as $cat)
                                <option value="{{ $cat->name }}">{{ $cat->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.submission_date_required') }}</label>
                        <input type="date" name="tgl_pengajuan" id="tgl_pengajuan" class="form-control" value="{{ date('Y-m-d') }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.tx_date_required') }}</label>
                        <input type="date" name="tgl_transaksi" id="tgl_transaksi" class="form-control" placeholder="Pilih tanggal transaksi">
                        <small class="text-muted">{{ __('erp.tx_date_journal_required_hint') }}</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.due_date') }}</label>
                        <input type="date" name="jatuh_tempo" class="form-control">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.vendor_supplier_required') }}</label>
                        <select name="vendor_toko" id="vendor_toko" class="form-select" required>
                            <option value="">{{ __('erp.select_vendor_ph') }}</option>
                        </select>
                        <input type="hidden" name="ref_bill_number" id="ref_bill_number">
                        <input type="hidden" name="ref_po_number" id="ref_po_number">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.person_in_charge_required') }}</label>
                        <input type="text" name="penerima_pj" class="form-control" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.account_virtual_account') }}</label>
                        <input type="text" name="rekening_va" class="form-control" placeholder="CONTOH: BCA - 123456 - NAMA">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.store_name_link') }}</label>
                        <input type="text" name="nama_toko_link" class="form-control" placeholder="Contoh: Shopee, Tokopedia, atau link toko">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.general_notes_optional') }}</label>
                        <textarea name="keterangan" id="keterangan" class="form-control" rows="2" placeholder="Ringkasan pengajuan, misal: Belanja ATK & Operasional Bulan Ini"></textarea>
                        <small class="text-muted">{{ __('erp.leave_blank_auto_summary_hint') }}</small>
                    </div>

                    <!-- PEMBAYARAN HUTANG: Dropdown Bills by Vendor -->
                    <div class="col-md-12 mb-3" id="bill_area" style="display: none;">
                        <div class="bg-light p-3 border border-warning rounded">
                            <h6 class="fw-bold text-warning mb-3"><i class="fa-solid fa-file-invoice-dollar me-1"></i> {{ __('erp.select_bill_no_ap_payment') }}</h6>
                            <label class="form-label fw-bold text-dark">{{ __('erp.search_select_bill_required') }}</label>
                            <select name="selected_bill" id="selected_bill" class="form-select">
                                <option value="">{{ __('erp.select_bill_ph') }}</option>
                            </select>
                            <small class="text-muted mt-2 d-block">{{ __('erp.select_vendor_then_bill_hint') }}</small>
                        </div>
                    </div>

                    <!-- UANG MUKA: Dropdown POs by Vendor -->
                    <div class="col-md-12 mb-3" id="po_area" style="display: none;">
                        <div class="bg-light p-3 border border-info rounded">
                            <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-boxes-stacked me-1"></i> {{ __('erp.select_po_no_dp') }}</h6>
                            <label class="form-label fw-bold text-dark">{{ __('erp.search_select_po_required') }}</label>
                            <select name="selected_po" id="selected_po" class="form-select">
                                <option value="">{{ __('erp.select_po_placeholder') }}</option>
                            </select>
                            <small class="text-muted mt-2 d-block">{{ __('erp.select_vendor_then_po_hint') }}</small>
                        </div>
                    </div>

                    <!-- PO DETAILS for UANG MUKA (rincian ke Jubelio PO, TERPISAH dari item Payment Plan di bawah) -->
                    <div class="col-md-12 mb-3" id="po_detail_area" style="display: none;">
                        <div class="bg-light p-3 border border-info rounded">
                            <h6 class="fw-bold text-primary mb-3"><i class="fa-solid fa-list me-1"></i> {{ __('erp.po_item_detail_jubelio') }}</h6>
                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle bg-white">
                                    <thead class="table-secondary text-muted small text-center">
                                        <tr>
                                            <th width="30%">{{ __('erp.sku_item_code') }}</th>
                                            <th width="30%">{{ __('erp.item_description') }}</th>
                                            <th width="20%">{{ __('erp.unit_price_rp') }}</th>
                                            <th width="15%">{{ __('erp.qty_ordered_caps') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody id="baris-po">
                                        <tr>
                                            <td><input type="text" name="po_details[0][item_code]" class="form-control form-control-sm po-input" placeholder="SKU..."></td>
                                            <td><input type="text" name="po_details[0][description]" class="form-control form-control-sm po-input" placeholder="Nama Barang..."></td>
                                            <td><input type="number" name="po_details[0][price]" class="form-control form-control-sm text-end po-input" value="0"></td>
                                            <td><input type="number" name="po_details[0][qty]" class="form-control form-control-sm text-center po-input" value="0"></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                            <button type="button" class="btn btn-outline-primary btn-sm fw-bold mt-2" onclick="tambahBarisPO()">{{ __('erp.add_item_row_btn') }}</button>
                        </div>
                    </div>

                    <!-- ITEM PAYMENT PLAN (repeater) -->
                    <div class="col-md-12 mb-3">
                        <div class="bg-light p-3 border border-primary rounded">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-primary m-0"><i class="fa-solid fa-cart-shopping me-1"></i> {{ __('erp.payment_item_detail') }}</h6>
                                <button type="button" class="btn btn-primary btn-sm fw-bold" onclick="tambahItem()">
                                    <i class="fa-solid fa-plus me-1"></i> Tambah Item
                                </button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle bg-white" id="tabel-item">
                                    <thead class="table-secondary text-muted small text-center">
                                        <tr>
                                            <th width="18%">{{ __('erp.item_name') }}</th>
                                            <th width="22%">{{ __('erp.notes_required') }}</th>
                                            <th width="9%">{{ __('erp.qty') }}</th>
                                            <th width="9%">{{ __('erp.unit') }}</th>
                                            <th width="14%">{{ __('erp.unit_price_rp') }}</th>
                                            <th width="14%">{{ __('erp.amount_rp') }}</th>
                                            <th width="10%">{{ __('erp.proof_label') }}</th>
                                            <th width="4%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="baris-item"></tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td colspan="5" class="text-end fw-bold">{{ __('erp.total_amount_caps') }}</td>
                                            <td class="fw-bold text-primary text-end" id="total-nominal-display">{{ __('erp.rp_zero') }}</td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <input type="hidden" name="nominal" id="nominal_asli" value="0">
                        </div>
                    </div>

                </div>

                <hr class="mb-4">

                <button type="submit" class="btn btn-success px-4 py-2 fw-bold">
                    <i class="fas fa-save me-2"></i> Simpan Pengajuan
                </button>
                <a href="{{ route('payment.index') }}" class="btn btn-secondary px-4 py-2 fw-bold ms-2">{{ __('erp.cancel') }}</a>
            </form>
        </div>
    </div>
</div>

<template id="template-item-row">
    <tr class="baris-item-row">
        <td><input type="text" name="items[__IDX__][nama_item]" class="form-control form-control-sm" placeholder="Nama barang/jasa"></td>
        <td><input type="text" name="items[__IDX__][keterangan]" class="form-control form-control-sm item-keterangan" placeholder="Uraian item" required></td>
        <td><input type="number" step="0.01" min="0.01" name="items[__IDX__][qty]" class="form-control form-control-sm text-center item-qty" value="1"></td>
        <td><input type="text" name="items[__IDX__][satuan]" class="form-control form-control-sm text-center item-satuan" value="Pcs"></td>
        <td>
            <input type="text" class="form-control form-control-sm text-end item-harga-mask" placeholder="0">
            <input type="hidden" name="items[__IDX__][harga_satuan]" class="item-harga-asli">
        </td>
        <td>
            <input type="text" class="form-control form-control-sm text-end fw-bold text-primary item-nominal-mask" placeholder="0">
            <input type="hidden" name="items[__IDX__][nominal]" class="item-nominal-asli" required>
        </td>
        <td><input type="file" name="items[__IDX__][bukti_file]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf"></td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="hapusItem(this)"><i class="fa-solid fa-trash"></i></button>
        </td>
    </tr>
</template>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    function formatRupiah(angka) {
        if (!angka) return '';
        let number_string = angka.toString(), sisa = number_string.length % 3,
            rupiah = number_string.substr(0, sisa), ribuan = number_string.substr(sisa).match(/\d{3}/gi);
        if (ribuan) { let separator = sisa ? '.' : ''; rupiah += separator + ribuan.join('.'); }
        return rupiah;
    }
    function parseRupiah(str) {
        return (str || '').toString().replace(/[^0-9]/g, '');
    }

    let itemIdx = 0;
    const nominalAsliTotal = document.getElementById('nominal_asli');
    const totalNominalDisplay = document.getElementById('total-nominal-display');

    function hitungTotalSemuaItem() {
        let total = 0;
        document.querySelectorAll('#baris-item .item-nominal-asli').forEach(function(el) {
            total += parseFloat(el.value || 0);
        });
        nominalAsliTotal.value = total;
        totalNominalDisplay.textContent = 'Rp ' + formatRupiah(Math.round(total).toString());
    }

    function hitungNominalBaris(tr) {
        const hargaAsli = tr.querySelector('.item-harga-asli');
        const qtyInput = tr.querySelector('.item-qty');
        const nominalMask = tr.querySelector('.item-nominal-mask');
        const nominalAsli = tr.querySelector('.item-nominal-asli');

        const harga = parseFloat(hargaAsli.value || 0);
        const qty = parseFloat(qtyInput.value || 0);

        if (harga > 0) {
            const total = Math.round(harga * qty);
            nominalAsli.value = total;
            nominalMask.value = formatRupiah(total.toString());
        }
        hitungTotalSemuaItem();
    }

    function bindBarisItem(tr) {
        const hargaMask = tr.querySelector('.item-harga-mask');
        const hargaAsli = tr.querySelector('.item-harga-asli');
        const nominalMask = tr.querySelector('.item-nominal-mask');
        const nominalAsli = tr.querySelector('.item-nominal-asli');
        const qtyInput = tr.querySelector('.item-qty');

        hargaMask.addEventListener('input', function() {
            hargaAsli.value = parseRupiah(this.value);
            this.value = formatRupiah(hargaAsli.value);
            hitungNominalBaris(tr);
        });

        qtyInput.addEventListener('input', function() { hitungNominalBaris(tr); });
        qtyInput.addEventListener('change', function() { hitungNominalBaris(tr); });

        // Nominal manual: kalau Harga Satuan kosong, user bisa isi nominal langsung
        nominalMask.addEventListener('input', function() {
            nominalAsli.value = parseRupiah(this.value);
            this.value = formatRupiah(nominalAsli.value);
            hitungTotalSemuaItem();
        });
    }

    function tambahItem() {
        const template = document.getElementById('template-item-row');
        const html = template.innerHTML.replaceAll('__IDX__', itemIdx);
        const tbody = document.getElementById('baris-item');
        const wrapper = document.createElement('tbody');
        wrapper.innerHTML = html;
        const tr = wrapper.firstElementChild;
        tbody.appendChild(tr);
        bindBarisItem(tr);
        itemIdx++;
        hitungTotalSemuaItem();
    }

    function hapusItem(btn) {
        const tbody = document.getElementById('baris-item');
        if (tbody.querySelectorAll('.baris-item-row').length <= 1) {
            alert('Minimal harus ada 1 item.');
            return;
        }
        btn.closest('tr').remove();
        hitungTotalSemuaItem();
    }

    // form guard: pastikan minimal 1 item & total > 0 sebelum submit
    document.getElementById('form-payment').addEventListener('submit', function(e) {
        hitungTotalSemuaItem();
        const rows = document.querySelectorAll('#baris-item .baris-item-row');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Tambahkan minimal 1 item pembayaran.');
            return;
        }
        if (parseFloat(nominalAsliTotal.value || 0) <= 0) {
            e.preventDefault();
            alert('Total nominal harus lebih dari 0. Cek Harga Satuan / Nominal tiap item.');
        }
    });

    // Baris item pertama otomatis muncul saat load
    document.addEventListener('DOMContentLoaded', function() {
        tambahItem();
    });

    $(document).ready(function() {
        const kategoriSelect = document.getElementById('kategori_payment');
        const billArea = document.getElementById('bill_area');
        const poArea = document.getElementById('po_area');
        const poDetailArea = document.getElementById('po_detail_area');
        const selectedBill = document.getElementById('selected_bill');
        const selectedPO = document.getElementById('selected_po');
        const refBillInput = document.getElementById('ref_bill_number');
        const refPOInput = document.getElementById('ref_po_number');
        const keteranganInput = document.getElementById('keterangan');
        const vendorSelect = document.getElementById('vendor_toko');

        let currentVendor = '';

        // Step 1: Vendor dropdown with AJAX search & Custom text entry support
        $(vendorSelect).select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: '-- Cari / Pilih / Ketik Vendor --',
            allowClear: true,
            tags: true,
            minimumInputLength: 0,
            ajax: {
                url: '{{ route("payment.api.vendors") }}',
                dataType: 'json',
                delay: 250,
                data: function(params) {
                    return { q: params.term || '' };
                },
                processResults: function(data) {
                    return { results: data };
                },
                cache: true,
                error: function(xhr, status, error) {
                    console.error('Vendor AJAX error:', error);
                    return { results: [] };
                }
            }
        });

        // Step 2: When vendor selected, load Bill or PO dropdown
        $(vendorSelect).on('select2:select', function(e) {
            currentVendor = e.params.data.id;
            refBillInput.value = '';
            refPOInput.value = '';
            $(selectedBill).empty().trigger('change');
            $(selectedPO).empty().trigger('change');

            if (kategoriSelect.value === 'PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)') {
                initBillSelect2();
                $(selectedBill).select2('open');
            } else if (kategoriSelect.value === 'PEMBELIAN PERSEDIAAN (UANG MUKA)') {
                initPOSelect2();
                $(selectedPO).select2('open');
            }
        });

        function setNominalDariReferensi(grandTotal) {
            // Isi item pertama dengan total dari Bill/PO yang dipilih
            const firstRow = document.querySelector('#baris-item .baris-item-row');
            if (!firstRow) return;
            const nominalAsli = firstRow.querySelector('.item-nominal-asli');
            const nominalMask = firstRow.querySelector('.item-nominal-mask');
            nominalAsli.value = grandTotal || 0;
            nominalMask.value = formatRupiah((grandTotal || 0).toString());
            hitungTotalSemuaItem();
        }

        function initBillSelect2() {
            if ($(selectedBill).hasClass('select2-hidden-accessible')) {
                $(selectedBill).select2('destroy');
            }
            $(selectedBill).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih Bill --',
                allowClear: true,
                minimumInputLength: 0,
                ajax: {
                    url: '{{ route("payment.api.bills") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { vendor_name: currentVendor, term: params.term || '' };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true,
                    error: function(xhr, status, error) {
                        console.error('Bills AJAX error:', error);
                        return { results: [] };
                    }
                }
            });

            $(selectedBill).off('select2:select').on('select2:select', function(e) {
                let data = e.params.data;
                refBillInput.value = data.id;
                setNominalDariReferensi(data.grand_total);
                keteranganInput.value = 'Pembayaran Hutang untuk Bill: ' + data.id;
                const firstRowKet = document.querySelector('#baris-item .item-keterangan');
                if (firstRowKet) firstRowKet.value = 'Pembayaran Hutang untuk Bill: ' + data.id;
            });
        }

        function initPOSelect2() {
            if ($(selectedPO).hasClass('select2-hidden-accessible')) {
                $(selectedPO).select2('destroy');
            }
            $(selectedPO).select2({
                theme: 'bootstrap-5',
                width: '100%',
                placeholder: '-- Pilih PO --',
                allowClear: true,
                minimumInputLength: 0,
                ajax: {
                    url: '{{ route("payment.api.pos") }}',
                    dataType: 'json',
                    delay: 250,
                    data: function(params) {
                        return { vendor_name: currentVendor, term: params.term || '' };
                    },
                    processResults: function(data) {
                        return { results: data };
                    },
                    cache: true,
                    error: function(xhr, status, error) {
                        console.error('POs AJAX error:', error);
                        return { results: [] };
                    }
                }
            });

            $(selectedPO).off('select2:select').on('select2:select', function(e) {
                let data = e.params.data;
                refPOInput.value = data.id;
                setNominalDariReferensi(data.grand_total);
                keteranganInput.value = 'Uang Muka Pembelian untuk PO: ' + data.id;
                const firstRowKet = document.querySelector('#baris-item .item-keterangan');
                if (firstRowKet) firstRowKet.value = 'Uang Muka Pembelian untuk PO: ' + data.id;
                poDetailArea.style.display = 'block';
            });
        }

        kategoriSelect.addEventListener('change', function() {
            const val = this.value;
            refBillInput.value = '';
            refPOInput.value = '';
            $(selectedBill).empty().trigger('change');
            $(selectedPO).empty().trigger('change');

            if (val === 'PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)') {
                billArea.style.display = 'block';
                poArea.style.display = 'none';
                poDetailArea.style.display = 'none';
                initBillSelect2();
            } else if (val === 'PEMBELIAN PERSEDIAAN (UANG MUKA)') {
                poArea.style.display = 'block';
                poDetailArea.style.display = 'block';
                billArea.style.display = 'none';
                initPOSelect2();
            } else {
                billArea.style.display = 'none';
                poArea.style.display = 'none';
                poDetailArea.style.display = 'none';
            }
        });
    });

    let barisCount = 1;
    function tambahBarisPO() {
        const tbody = document.getElementById('baris-po');
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="text" name="po_details[${barisCount}][item_code]" class="form-control form-control-sm po-input" placeholder="SKU..." required></td>
            <td><input type="text" name="po_details[${barisCount}][description]" class="form-control form-control-sm po-input" placeholder="Nama Barang..."></td>
            <td><input type="number" name="po_details[${barisCount}][price]" class="form-control form-control-sm text-end po-input" value="0"></td>
            <td><input type="number" name="po_details[${barisCount}][qty]" class="form-control form-control-sm text-center po-input" value="0"></td>
        `;
        tbody.appendChild(tr);
        barisCount++;
    }
</script>
@endsection
