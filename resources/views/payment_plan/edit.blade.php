@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('payment.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_btn') }}</a>
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.payment_plan') => route('payment.index'), __('erp.bc_edit') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    @php
        $statusTerkunci = in_array($data->status_payment, ['PAID', 'POSTED']);
        $details = $data->details ?? collect();
    @endphp
    <div class="card shadow-sm border-warning">
        <div class="card-header bg-warning text-dark">
            <h5 class="m-0 fw-bold"><i class="fas fa-edit me-2"></i> Edit Data Payment Plan #{{ $data->no_transaksi }}</h5>
        </div>
        <div class="card-body">
            @if($statusTerkunci)
                <div class="alert alert-danger fw-bold">
                    <i class="fa-solid fa-lock me-1"></i> {{ __('erp.status_already') }} <strong>{{ $data->status_payment }}</strong>. Nominal item tidak dapat diubah/ditambah/dihapus lagi (sudah diposting ke Jurnal). Anda tetap bisa mengubah data non-nominal (vendor, keterangan header, dsb).
                </div>
            @endif

            <form action="{{ route('payment.update', $data->id_payment) }}" method="POST" enctype="multipart/form-data" id="form-payment-edit">
                @csrf
                @method('PUT')
                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.select_division_required') }}</label>
                        <select name="id_divisi" class="form-select" required>
                            @foreach($divisi as $div)
                                <option value="{{ $div->id_divisi }}" {{ $data->id_divisi == $div->id_divisi ? 'selected' : '' }}>
                                    {{ $div->nama_divisi }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.ops_account_required') }}</label>
                        <select name="jenis_transaksi" class="form-select" required>
                            <option value="BCA BBW OPS" {{ $data->jenis_transaksi == 'BCA BBW OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_bbw_ops') }}</option>
                            <option value="BCA BBB OPS" {{ $data->jenis_transaksi == 'BCA BBB OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_bbb_ops') }}</option>
                            <option value="BCA KOI OPS" {{ $data->jenis_transaksi == 'BCA KOI OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_koi_ops') }}</option>
                            <option value="BCA GBB OPS" {{ $data->jenis_transaksi == 'BCA GBB OPS' ? 'selected' : '' }}>{{ __('erp.bank_bca_gbb_ops') }}</option>
                            <option value="BCA BBW" {{ $data->jenis_transaksi == 'BCA BBW' ? 'selected' : '' }}>{{ __('erp.bank_bca_bbw') }}</option>
                            <option value="BCA BBB" {{ $data->jenis_transaksi == 'BCA BBB' ? 'selected' : '' }}>{{ __('erp.bank_bca_bbb') }}</option>
                            <option value="BCA KOI" {{ $data->jenis_transaksi == 'BCA KOI' ? 'selected' : '' }}>{{ __('erp.bank_bca_koi') }}</option>
                            <option value="BCA GBB" {{ $data->jenis_transaksi == 'BCA GBB' ? 'selected' : '' }}>{{ __('erp.bank_bca_gbb') }}</option>
                            <option value="MANDIRI BBW" {{ $data->jenis_transaksi == 'MANDIRI BBW' ? 'selected' : '' }}>{{ __('erp.bank_mandiri_bbw') }}</option>
                            <option value="MANDIRI KOI" {{ $data->jenis_transaksi == 'MANDIRI KOI' ? 'selected' : '' }}>{{ __('erp.bank_mandiri_koi') }}</option>
                            <option value="MANDIRI BBB" {{ $data->jenis_transaksi == 'MANDIRI BBB' ? 'selected' : '' }}>{{ __('erp.bank_mandiri_bbb') }}</option>
                            <option value="XENDIT" {{ $data->jenis_transaksi == 'XENDIT' ? 'selected' : '' }}>{{ __('erp.bank_xendit') }}</option>
                            <option value="BRI BBW" {{ $data->jenis_transaksi == 'BRI BBW' ? 'selected' : '' }}>{{ __('erp.bank_bri_bbw') }}</option>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.payment_category_required') }}</label>
                        <select name="kategori_payment" class="form-select" required>
                            @if(isset($payment_categories))
                                @foreach($payment_categories as $cat)
                                    <option value="{{ $cat->name }}" {{ $data->kategori_payment == $cat->name ? 'selected' : '' }}>{{ $cat->name }}</option>
                                @endforeach
                                @if(!empty($data->kategori_payment) && !$payment_categories->contains('name', $data->kategori_payment))
                                    <option value="{{ $data->kategori_payment }}" selected>{{ $data->kategori_payment }} (Nonaktif/Lama)</option>
                                @endif
                            @endif
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.submission_date_required') }}</label>
                        <input type="date" name="tgl_pengajuan" class="form-control" value="{{ $data->tgl_pengajuan }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.tx_date_required') }}</label>
                        <input type="date" name="tgl_transaksi" class="form-control" value="{{ $data->tgl_transaksi ?? $data->tgl_pengajuan }}" required>
                        <small class="text-muted">{{ __('erp.tx_date_journal_hint') }}</small>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.due_date') }}</label>
                        <input type="date" name="jatuh_tempo" class="form-control" value="{{ $data->jatuh_tempo }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.vendor_store_required') }}</label>
                        <input type="text" name="vendor_toko" class="form-control" value="{{ $data->vendor_toko }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.person_in_charge_required') }}</label>
                        <input type="text" name="penerima_pj" class="form-control" value="{{ $data->penerima_pj }}" required>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.account_virtual_account') }}</label>
                        <input type="text" name="rekening_va" class="form-control" value="{{ $data->rekening_va }}">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.store_name_link') }}</label>
                        <input type="text" name="nama_toko_link" class="form-control" value="{{ $data->nama_toko_link ?? '' }}" placeholder="{{ __('erp.eg_store_link') }}">
                    </div>

                    <div class="col-md-12 mb-3">
                        <label class="form-label fw-bold text-dark">{{ __('erp.general_notes') }}</label>
                        <textarea name="keterangan" class="form-control" rows="2">{{ $data->keterangan }}</textarea>
                    </div>

                    <!-- ITEM PAYMENT PLAN (repeater) -->
                    <div class="col-md-12 mb-3">
                        <div class="bg-light p-3 border border-warning rounded">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h6 class="fw-bold text-warning mb-0"><i class="fa-solid fa-cart-shopping me-1"></i> {{ __('erp.payment_item_detail') }}</h6>
                                @unless($statusTerkunci)
                                <button type="button" class="btn btn-warning btn-sm fw-bold text-dark" onclick="tambahItem()">
                                    <i class="fa-solid fa-plus me-1"></i> {{ __('erp.add_item_btn') }}
                                </button>
                                @endunless
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm table-bordered align-middle bg-white" id="tabel-item">
                                    <thead class="table-secondary text-muted small text-center">
                                        <tr>
                                            <th width="16%">{{ __('erp.item_name') }}</th>
                                            <th width="19%">{{ __('erp.notes_required') }}</th>
                                            <th width="8%">{{ __('erp.qty') }}</th>
                                            <th width="8%">{{ __('erp.unit') }}</th>
                                            <th width="12%">{{ __('erp.unit_price_rp') }}</th>
                                            <th width="12%">{{ __('erp.amount_rp') }}</th>
                                            <th width="12%">{{ __('erp.actual_amount_rp') }}</th>
                                            <th width="9%">{{ __('erp.proof_label') }}</th>
                                            <th width="4%"></th>
                                        </tr>
                                    </thead>
                                    <tbody id="baris-item">
                                        @forelse($details as $i => $d)
                                        <tr class="baris-item-row">
                                            <td>
                                                <input type="hidden" name="items[{{ $i }}][id_detail]" value="{{ $d->id_detail }}">
                                                <input type="text" name="items[{{ $i }}][nama_item]" class="form-control form-control-sm" value="{{ $d->nama_item }}" placeholder="{{ __('erp.item_service_name') }}" {{ $statusTerkunci ? 'readonly' : '' }}>
                                            </td>
                                            <td><input type="text" name="items[{{ $i }}][keterangan]" class="form-control form-control-sm item-keterangan" value="{{ $d->keterangan }}" required {{ $statusTerkunci ? 'readonly' : '' }}></td>
                                            <td><input type="number" step="0.01" min="0.01" name="items[{{ $i }}][qty]" class="form-control form-control-sm text-center item-qty" value="{{ $d->qty }}" {{ $statusTerkunci ? 'readonly' : '' }}></td>
                                            <td><input type="text" name="items[{{ $i }}][satuan]" class="form-control form-control-sm text-center item-satuan" value="{{ $d->satuan }}" {{ $statusTerkunci ? 'readonly' : '' }}></td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm text-end item-harga-mask" value="{{ $d->harga_satuan !== null ? number_format($d->harga_satuan, 0, ',', '.') : '' }}" placeholder="0" {{ $statusTerkunci ? 'readonly' : '' }}>
                                                <input type="hidden" name="items[{{ $i }}][harga_satuan]" class="item-harga-asli" value="{{ $d->harga_satuan ?? '' }}">
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm text-end fw-bold text-primary item-nominal-mask" value="{{ number_format($d->nominal, 0, ',', '.') }}" {{ $statusTerkunci ? 'readonly' : '' }}>
                                                <input type="hidden" name="items[{{ $i }}][nominal]" class="item-nominal-asli" value="{{ (int) $d->nominal }}" required>
                                            </td>
                                            <td>
                                                <input type="text" class="form-control form-control-sm text-end fw-bold text-success item-aktual-mask" value="{{ $d->nominal_aktual !== null ? number_format($d->nominal_aktual, 0, ',', '.') : '' }}" placeholder="{{ __('erp.leave_blank_if_nominal') }}">
                                                <input type="hidden" name="items[{{ $i }}][nominal_aktual]" class="item-aktual-asli" value="{{ $d->nominal_aktual ?? '' }}">
                                            </td>
                                            <td>
                                                <input type="file" name="items[{{ $i }}][bukti_file]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf">
                                                @if($d->bukti_file)
                                                    <a href="{{ asset('storage/' . $d->bukti_file) }}" target="_blank" class="small d-block mt-1"><i class="fa-solid fa-paperclip"></i> {{ __('erp.view') }}</a>
                                                @endif
                                            </td>
                                            <td class="text-center">
                                                @unless($statusTerkunci)
                                                <button type="button" class="btn btn-outline-danger btn-sm" onclick="hapusItem(this, {{ $d->id_detail }})"><i class="fa-solid fa-trash"></i></button>
                                                @endunless
                                            </td>
                                        </tr>
                                        @empty
                                        {{-- kalau tidak ada detail sama sekali, baris kosong akan ditambahkan lewat JS saat load --}}
                                        @endforelse
                                    </tbody>
                                    <tfoot>
                                        <tr class="table-light">
                                            <td colspan="5" class="text-end fw-bold">{{ __('erp.total_amount_caps') }}</td>
                                            <td class="fw-bold text-primary text-end" id="total-nominal-display">Rp {{ number_format($data->nominal, 0, ',', '.') }}</td>
                                            <td class="fw-bold text-success text-end" id="total-aktual-display">
                                                Rp {{ number_format($data->nominal_aktual ?? 0, 0, ',', '.') }}
                                            </td>
                                            <td colspan="2"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            <input type="hidden" id="deleted_detail_ids_json" name="deleted_detail_ids_json" value="[]">
                        </div>
                    </div>

                </div>

                <hr class="mb-4">

                <button type="submit" class="btn btn-warning px-4 py-2 fw-bold text-dark">
                    <i class="fas fa-save me-2"></i> Update Perubahan Data
                </button>
                <a href="{{ route('payment.index') }}" class="btn btn-secondary px-4 py-2 fw-bold ms-2">{{ __('erp.cancel') }}</a>
            </form>
        </div>
    </div>
</div>

<template id="template-item-row">
    <tr class="baris-item-row">
        <td><input type="text" name="items[__IDX__][nama_item]" class="form-control form-control-sm" placeholder="{{ __('erp.item_service_name') }}"></td>
        <td><input type="text" name="items[__IDX__][keterangan]" class="form-control form-control-sm item-keterangan" placeholder="{{ __('erp.item_description_ph') }}" required></td>
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
        <td>
            <input type="text" class="form-control form-control-sm text-end fw-bold text-success item-aktual-mask" placeholder="{{ __('erp.leave_blank_if_nominal') }}">
            <input type="hidden" name="items[__IDX__][nominal_aktual]" class="item-aktual-asli">
        </td>
        <td><input type="file" name="items[__IDX__][bukti_file]" class="form-control form-control-sm" accept=".jpg,.jpeg,.png,.pdf"></td>
        <td class="text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="hapusItem(this)"><i class="fa-solid fa-trash"></i></button>
        </td>
    </tr>
</template>

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

    let itemIdx = {{ $details->count() }};
    let deletedIds = [];
    const totalNominalDisplay = document.getElementById('total-nominal-display');
    const totalAktualDisplay = document.getElementById('total-aktual-display');
    const deletedIdsInput = document.getElementById('deleted_detail_ids_json');

    function hitungTotalSemuaItem() {
        let totalNominal = 0;
        let totalAktual = 0;
        let adaAktual = false;

        document.querySelectorAll('#baris-item .baris-item-row').forEach(function(tr) {
            const nominalAsli = tr.querySelector('.item-nominal-asli');
            const aktualAsli = tr.querySelector('.item-aktual-asli');
            const nominal = parseFloat(nominalAsli?.value || 0);
            totalNominal += nominal;

            if (aktualAsli && aktualAsli.value !== '') {
                totalAktual += parseFloat(aktualAsli.value);
                adaAktual = true;
            } else {
                totalAktual += nominal;
            }
        });

        totalNominalDisplay.textContent = 'Rp ' + formatRupiah(Math.round(totalNominal).toString());
        totalAktualDisplay.textContent = 'Rp ' + formatRupiah(Math.round(totalAktual).toString());
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
        const aktualMask = tr.querySelector('.item-aktual-mask');
        const aktualAsli = tr.querySelector('.item-aktual-asli');
        const qtyInput = tr.querySelector('.item-qty');

        if (hargaMask.readOnly) return; // status terkunci, tidak perlu binding edit

        hargaMask.addEventListener('input', function() {
            hargaAsli.value = parseRupiah(this.value);
            this.value = formatRupiah(hargaAsli.value);
            hitungNominalBaris(tr);
        });

        qtyInput.addEventListener('input', function() { hitungNominalBaris(tr); });
        qtyInput.addEventListener('change', function() { hitungNominalBaris(tr); });

        nominalMask.addEventListener('input', function() {
            nominalAsli.value = parseRupiah(this.value);
            this.value = formatRupiah(nominalAsli.value);
            hitungTotalSemuaItem();
        });

        aktualMask.addEventListener('input', function() {
            aktualAsli.value = parseRupiah(this.value);
            this.value = formatRupiah(aktualAsli.value);
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

    function hapusItem(btn, idDetail) {
        const tbody = document.getElementById('baris-item');
        if (tbody.querySelectorAll('.baris-item-row').length <= 1) {
            alert('Minimal harus ada 1 item.');
            return;
        }
        if (idDetail) {
            deletedIds.push(idDetail);
            deletedIdsInput.value = JSON.stringify(deletedIds);
        }
        btn.closest('tr').remove();
        hitungTotalSemuaItem();
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('#baris-item .baris-item-row').forEach(bindBarisItem);
        if (document.querySelectorAll('#baris-item .baris-item-row').length === 0) {
            tambahItem();
        }
        hitungTotalSemuaItem();
    });

    document.getElementById('form-payment-edit').addEventListener('submit', function(e) {
        hitungTotalSemuaItem();
        const rows = document.querySelectorAll('#baris-item .baris-item-row');
        if (rows.length === 0) {
            e.preventDefault();
            alert('Minimal harus ada 1 item pembayaran.');
            return;
        }
    });
</script>
@endsection
