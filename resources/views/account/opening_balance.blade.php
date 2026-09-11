@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.opening_balance') => null]" />
@endsection

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    .coa-wrapper { font-family: 'Inter', sans-serif; color: #334155; }
    .table-coa th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 12px 10px; }
    .table-coa td { font-size: 0.85rem; vertical-align: middle; padding: 8px 10px; border-bottom: 1px solid #f8fafc; }
    .input-balance { text-align: right; font-family: 'Inter', sans-serif; font-size: 0.85rem; font-weight: 600; border-radius: 6px; border: 1px solid #cbd5e1; padding: 4px 8px; width: 100%; }
    .input-balance:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 2px rgba(59, 130, 246, 0.2); }
    .badge-normal { font-size: 0.7rem; font-weight: 700; padding: 4px 8px; border-radius: 4px; }
    .bg-deb { background-color: #dcfce7; color: #166534; }
    .bg-kre { background-color: #fef9c3; color: #854d0e; }
</style>

<div class="container-fluid coa-wrapper mb-5">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">{{ __('erp.setup_opening_balance') }}</h3>
            <p class="text-muted small mb-0">Masukkan nominal saldo awal neraca & laba rugi. Sistem otomatis memposisikannya sesuai sifat saldo normal.</p>
        </div>
  
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#importSaldoModal">
                <i class="fa-solid fa-file-import me-1"></i> {{ __('erp.import_opening_balance_csv') }}
            </button>
            
            <a href="{{ route('account.index') }}" class="btn btn-sm btn-outline-secondary fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> {{ __('erp.back_to') }} {{ __('erp.coa') }}</a>
        </div>
    </div>

    <div class="modal fade" id="importSaldoModal" tabindex="-1" aria-labelledby="importSaldoModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('account.import_opening_balance') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="importSaldoModalLabel">{{ __('erp.import_opening_balance') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('erp.close_btn') }}"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info py-3 small">
                            <strong>{{ __('erp.supported_csv_format') }}</strong><br>
                            Kolom 1: Kode Akun<br>
                            Kolom 2: Nama Akun<br>
                            Kolom 3: Nominal Debet<br>
                            Kolom 4: Nominal Kredit<br>
                            <em>{{ __('erp.first_row_ignored_header') }}</em>
                            
                            <div class="mt-3">
                                <a href="{{ route('account.download_template_opening_balance') }}" class="btn btn-xs btn-light text-primary fw-bold border border-primary shadow-sm">
                                    <i class="fa-solid fa-download"></i> {{ __('erp.download_template_btn') }} CSV Di Sini
                                </a>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="file_csv" class="form-label fw-bold">{{ __('erp.choose_edited_csv') }}</label>
                            <input type="file" class="form-control" name="file" id="file_csv" accept=".csv" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tanggal_saldo" class="form-label fw-bold">{{ __('erp.opening_balance_date') }}</label>
                            <input type="date" class="form-control" name="tanggal_saldo" value="{{ date('Y-01-01') }}" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                        <button type="submit" class="btn btn-success fw-bold"><i class="fa-solid fa-upload me-1"></i> {{ __('erp.process_import') }}</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @if(session('error'))
        <div class="alert alert-danger shadow-sm border-0 mb-4" style="border-radius: 8px;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
        </div>
    @endif
    
    @if(session('success'))
        <div class="alert alert-success shadow-sm border-0 mb-4" style="border-radius: 8px;">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="alert alert-warning shadow-sm border-0 mb-4" style="border-radius: 8px;">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('warning') }}
        </div>
    @endif

    <form action="#" method="POST" class="card border-0 shadow-sm p-4" style="border-radius: 12px;">
        @csrf

        <div class="row mb-4 bg-light p-3 rounded border g-3 align-items-center">
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.opening_balance_cutoff_date') }}</label>
                <input type="date" name="transaction_date" class="form-control fw-bold" value="{{ $existingDate }}" required>
            </div>
            <div class="col-md-8 text-md-end pt-md-3">
                <span class="text-muted small">{{ __('erp.hint_opening_balance_must_balance') }} <b>{{ __('erp.total_debit_equals_credit') }}</b>.</span>
            </div>
        </div>

        <div class="table-responsive mb-4 border rounded overflow-hidden">
            <table class="table table-coa mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="15%" class="ps-3">{{ __('erp.account_code') }}</th>
                        <th width="40%">{{ __('erp.account') }}</th>
                        <th width="15%" class="text-center">{{ __('erp.normal_position') }}</th>
                        <th width="30%" class="text-end pe-3">{{ __('erp.balance_amount_rp') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($accounts as $acc)
                        @php
                            $prefix   = substr(trim($acc->account_code), 0, 1);
                            $isDebet  = in_array($prefix, ['1', '5', '6', '9']);
                            $posName  = $isDebet ? 'DEBET' : 'KREDIT';
                            $badgeCls = $isDebet ? 'bg-deb' : 'bg-kre';
                            
                            $currVal  = $existingDetails[$acc->account_code] ?? 0;
                        @endphp
                        <tr class="{{ $currVal != 0 ? 'table-warning' : '' }}">
                            <td class="ps-3 fw-bold text-primary font-monospace">{{ $acc->account_code }}</td>
                            <td class="fw-medium text-dark">{{ $acc->account_name }}</td>
                            <td class="text-center">
                                <span class="badge-normal {{ $badgeCls }}">{{ $posName }}</span>
                            </td>
                            <td class="pe-3">
                                <input type="text" name="balances[{{ $acc->account_code }}]" class="input-balance amount-input" value="{{ $currVal != 0 ? number_format($currVal, 2, ',', '.') : '' }}" placeholder="0" data-pos="{{ $posName }}">
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="bg-light p-3 rounded border d-flex flex-column flex-md-row justify-content-between align-items-center gap-3">
            <div class="d-flex gap-4 fw-bold text-nowrap">
                <div>{{ __('erp.total_debit_colon') }} <span id="lblDebet" class="text-success fs-6 ms-1">{{ __('erp.rp_zero') }}</span></div>
                <div>{{ __('erp.total_credit_colon') }} <span id="lblKredit" class="text-danger fs-6 ms-1">{{ __('erp.rp_zero') }}</span></div>
                <div>{{ __('erp.difference_colon') }} <span id="lblSelisih" class="text-warning fs-6 ms-1">{{ __('erp.rp_zero') }}</span></div>
            </div>
            <button type="submit" class="btn btn-primary fw-bold px-5 py-2 shadow-sm" id="btnSubmit" disabled>
                <i class="fa-solid fa-floppy-disk me-2"></i> {{ __('erp.save_opening_balance') }}
            </button>
        </div>

    </form>

</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script>
    $(document).ready(function() {
        function calculateTotals() {
            let totDeb = 0;
            let totKre = 0;

            $('.amount-input').each(function() {
                let rawValue = $(this).val();
                if (rawValue) {
                    // Bersihkan titik ribuan dan ubah koma menjadi titik standar desimal matematika
                    let cleaned = rawValue.replace(/\./g, '').replace(',', '.');
                    let val = parseFloat(cleaned) || 0;
                    let pos = $(this).data('pos');

                    if (pos === 'DEBET')  totDeb += val;
                    else                  totKre += val;
                }
            });

            let selisih = Math.abs(totDeb - totKre);

            // Tampilkan kembali ke label teks dengan format ribuan Indonesia
            $('#lblDebet').text('Rp ' + totDeb.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#lblKredit').text('Rp ' + totKre.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));
            $('#lblSelisih').text('Rp ' + selisih.toLocaleString('id-ID', { minimumFractionDigits: 2, maximumFractionDigits: 2 }));

            if (selisih >= 0.01) {
                $('#lblSelisih').removeClass('text-success').addClass('text-danger fw-bold');
                $('#btnSubmit').prop('disabled', true); // Kunci tombol jika masih selisih
            } else {
                $('#lblSelisih').removeClass('text-danger').addClass('text-success');
                $('#btnSubmit').prop('disabled', false); // Buka kunci tombol jika sudah seimbang (Balance)
            }
        }

        $('.amount-input').on('input', calculateTotals);
        calculateTotals();
    });
</script>
@endsection
