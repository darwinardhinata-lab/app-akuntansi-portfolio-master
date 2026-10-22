@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.general_ledger') => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>

<style>
    /* Mengunci font seluruh area Buku Besar menjadi Inter */
    .ledger-wrapper { font-family: 'Inter', sans-serif; color: #334155; }
    [data-ledger-account] { margin-bottom: 1.5rem; }
    .select2-container--bootstrap-5 .select2-selection--multiple { min-height: 38px; }
    .select2-container--bootstrap-5 .select2-selection--multiple .select2-selection__choice { white-space: normal; overflow-wrap: anywhere; }
    
    /* Menyelaraskan ukuran font, padding, dan kerapian baris tabel */
    .ledger-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 12px 10px; }
    .ledger-table td { font-size: 0.85rem; vertical-align: middle; padding: 12px 10px; border-bottom: 1px solid #f8fafc; line-height: 1.5; }
    
    /* Memastikan font angka (nominal & saldo) proporsional dan tidak membesar */
    .ledger-table .nominal-number { font-family: 'Inter', sans-serif; font-size: 0.85rem; letter-spacing: -0.2px; }
    
    /* Menyesuaikan modal pop-out */
    .modal-journal-header { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .modal-journal-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    .modal-journal-table td { font-size: 0.85rem; vertical-align: middle; }
    
    @media print {
        .no-print { display: none !important; }
        [data-ledger-account] + [data-ledger-account] { break-before: page; }
    }
</style>

<div class="container-fluid ledger-wrapper">

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3 no-print">
        <div>
            <h3 class="fw-bold mb-1" style="color: #0f172a;">{{ __('erp.general_ledger_title') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.ledger_card_hint') }}</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" onclick="exportPDF()" class="btn btn-danger fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" title="{{ __('erp.export_pdf') }}">
                <i class="fa-solid fa-file-pdf me-1"></i> {{ __('erp.export_pdf') }}
            </button>
            <a href="{{ route('buku-besar.export', [
                'account_code' => count($accountCodes ?? [$accCode]) === 1 ? $accCode : null,
                'account_codes' => count($accountCodes ?? [$accCode]) > 1 ? $accountCodes : null,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]) }}" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" title="{{ __('erp.export_excel') }}">
                <i class="fa-solid fa-file-excel me-1"></i> {{ __('erp.export_excel_btn') }}
            </a>
        </div>
    </div>

    <form id="filterLedgerForm" action="{{ route('buku-besar.index') }}" method="GET" class="card p-3 mb-4 shadow-sm border-0 bg-white no-print" style="border-radius: 12px;">
        <div class="row g-2 align-items-center">
            
            <div class="col-md-3">
                <label for="ledgerAccounts" class="form-label small fw-bold text-muted mb-1">{{ __('erp.select_account_coa') }}</label>
                <select id="ledgerAccounts" name="account_codes[]" multiple class="form-select form-select-sm select2-account">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->account_code }}" {{ in_array($acc->account_code, $accountCodes ?? [$accCode]) ? 'selected' : '' }}>
                            {{ $acc->account_code }} - {{ $acc->translatedName() }}
                        </option>
                    @endforeach
                </select>
                <div class="form-text small">{{ __('erp.multi_account_hint') }}</div>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_full') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_full') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.rows_per_page') }}</label>
                <select name="per_page" class="form-select form-select-sm">
                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                </select>
            </div>

            <div class="col-md-3 d-flex gap-2 mt-md-4 pt-md-1">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_label') }}</button>
                <div class="btn-group shadow-sm">
                    <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-2" title="{{ __('erp.print') }}"><i class="fa-solid fa-print"></i></button>
                    <button type="button" onclick="exportPDF()" class="btn btn-sm btn-outline-danger fw-bold px-2" title="{{ __('erp.export_pdf') }}"><i class="fa-solid fa-file-pdf"></i></button>
                    <a href="{{ route('buku-besar.export', ['account_codes' => $accountCodes ?? [$accCode], 'start_date' => $startDate, 'end_date' => $endDate]) }}" class="btn btn-sm btn-outline-success fw-bold px-2" title="{{ __('erp.export_excel') }}"><i class="fa-solid fa-file-excel"></i></a>
                </div>
            </div>

        </div>
    </form>

    <!-- AREA NOTIFIKASI SESSION -->
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i> {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('erp.close_btn') }}"></button>
        </div>
    @endif

    <div id="print-area">
        @foreach(($sections ?? [get_defined_vars()]) as $section)
            @include('ledger.account-card', $section)
        @endforeach
    </div>
</div>

@foreach(collect($sections ?? [['transactions' => $transactions]])->flatMap(fn ($section) => $section['transactions']->getCollection())->unique('journal_id') as $tx)
    @if($tx->header && $tx->header->details)
        @php 
            $headerObj = $tx->header; 
            $modalId = $headerObj->journal_id ?? $headerObj->id;
                            $sourceLink = $headerObj->document_link ?? ['url' => route('jurnal.edit', $modalId), 'label' => 'Edit Jurnal', 'is_journal' => true];
        @endphp
        <div class="modal fade" id="modalJournal-{{ $modalId }}" tabindex="-1" aria-labelledby="modalLabel-{{ $modalId }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow ledger-wrapper">
                    
                    <div class="modal-header modal-journal-header px-4 py-3">
                        <div>
                            <span class="badge bg-primary mb-1">{{ __('erp.general_journal_detail') }}</span>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="modalLabel-{{ $modalId }}">
                                No. Bukti: {{ $headerObj->evidence_number }}
                            </h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="{{ __('erp.close_btn') }}"></button>
                    </div>

                    <div class="modal-body px-4 py-3">
                        <div class="row g-2 mb-3 small">
                            <div class="col-6">
                                <span class="text-muted d-block fw-bold" style="font-size: 0.75rem;">{{ __('erp.tx_date_caps') }}</span>
                                <span class="fw-medium text-dark">{{ date('d M Y', strtotime($headerObj->transaction_date)) }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fw-bold" style="font-size: 0.75rem;">{{ __('erp.general_notes_caps') }}</span>
                                <span class="fw-medium text-dark">{{ $headerObj->display_notes ?? '-' }}</span>
                            </div>
                        </div>

                        <div class="border rounded overflow-hidden">
                            <table class="table modal-journal-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%" class="ps-3">{{ __('erp.account_name_ref') }}</th>
                                        <th width="25%" class="text-end">{{ __('erp.debit_rp') }}</th>
                                        <th width="25%" class="text-end pe-3">{{ __('erp.credit_rp') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @php $totDeb = 0; $totKre = 0; @endphp
                                    @foreach($headerObj->details as $det)
                                        @php 
                                            if($det->position=='DEBET') $totDeb += $det->amount;
                                            else $totKre += $det->amount;
                                            
                                            $isCurrentAcc = ($det->account_code == $tx->account_code);
                                        @endphp
                                        <tr class="{{ $isCurrentAcc ? 'table-warning' : '' }}">
                                            <td class="ps-3">
                                                <div class="{{ $det->position == 'KREDIT' ? 'ms-3' : '' }}">
                                                    <span class="fw-bold {{ $isCurrentAcc ? 'text-primary' : 'text-dark' }}">
                                                        {{ $det->account?->translatedName() ?? 'Akun Terhapus' }}
                                                    </span>
                                                    <span class="text-muted font-monospace small d-block" style="font-size: 0.75rem;">
                                                        {{ $det->account_code }} @if($det->helper_code) <span class="text-secondary fw-bold">({{ $det->helper_code }})</span> @endif
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-end text-nowrap nominal-number fw-bold {{ $det->position == 'DEBET' ? 'text-success' : 'text-muted' }}">
                                                {{ $det->position == 'DEBET' ? number_format($det->amount, 2, ',', '.') : '-' }}
                                            </td>
                                            <td class="text-end text-nowrap nominal-number pe-3 fw-bold {{ $det->position == 'KREDIT' ? 'text-danger' : 'text-muted' }}">
                                                {{ $det->position == 'KREDIT' ? number_format($det->amount, 2, ',', '.') : '-' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    <tr class="table-light fw-bold" style="border-top: 2px solid #cbd5e1;">
                                        <td class="ps-3 text-end text-uppercase text-muted small">{{ __('erp.total_balance_caps') }}</td>
                                        <td class="text-end text-nowrap nominal-number text-success">{{ number_format($totDeb, 2, ',', '.') }}</td>
                                        <td class="text-end text-nowrap nominal-number pe-3 text-danger">{{ number_format($totKre, 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="modal-footer bg-light py-2">
                        <a href="{{ $sourceLink['url'] }}" class="btn btn-sm btn-outline-primary"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i>{{ $sourceLink['label'] }}</a>
                        @unless($sourceLink['is_journal'])
                            <a href="{{ route('jurnal.edit', $modalId) }}" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-book me-1"></i>{{ __('accounting.text_e9c14a1d484b') }}</a>
                        @endunless
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">{{ __('erp.close') }}</button>
                    </div>

                </div>
            </div>
        </div>
    @endif
@endforeach

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
    $(document).ready(function() {
        $('.select2-account').select2({
            theme: 'bootstrap-5',
            width: '100%',
            placeholder: @json(__('erp.select_account_coa')),
            closeOnSelect: false
        });
    });

    function exportPDF() {
        var element = document.getElementById('print-area');
        var opt = {
            margin: 0.3,
            filename: 'Buku_Besar_{{ count($accountCodes ?? [$accCode]) > 1 ? "Multi_Akun" : $accCode }}_{{ $startDate }}_sd_{{ $endDate }}.pdf',
            image: { type: 'jpeg', quality: 0.98 },
            html2canvas: { scale: 2 },
            jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' },
            pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
        };
        html2pdf().set(opt).from(element).save();
    }
</script>
@endsection
