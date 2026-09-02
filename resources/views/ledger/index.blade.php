@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Akuntansi' => '#', 'Buku Besar' => null]" />
@endsection

@section('content')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
    /* Mengunci font seluruh area Buku Besar menjadi Inter */
    .ledger-wrapper { font-family: 'Inter', sans-serif; color: #334155; }
    
    /* Menyelaraskan ukuran font, padding, dan kerapian baris tabel */
    .ledger-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; border-bottom: 2px solid #e2e8f0; padding: 12px 10px; }
    .ledger-table td { font-size: 0.85rem; vertical-align: middle; padding: 12px 10px; border-bottom: 1px solid #f8fafc; line-height: 1.5; }
    
    /* Memastikan font angka (nominal & saldo) proporsional dan tidak membesar */
    .ledger-table .nominal-number { font-family: 'Inter', sans-serif; font-size: 0.85rem; letter-spacing: -0.2px; }
    
    /* Menyesuaikan modal pop-out */
    .modal-journal-header { background-color: #f8fafc; border-bottom: 2px solid #e2e8f0; }
    .modal-journal-table th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; }
    .modal-journal-table td { font-size: 0.85rem; vertical-align: middle; }
    
    @media print { .no-print { display: none !important; } }
</style>

<div class="container-fluid ledger-wrapper">

    <div class="mb-4">
        <h3 class="fw-bold mb-1" style="color: #0f172a;">Buku Besar (General Ledger)</h3>
        <p class="text-muted small mb-0">Kartu pergerakan mutasi dan riwayat saldo per satu kode akun.</p>
    </div>

    <form action="{{ route('buku-besar.index') }}" method="GET" class="card p-3 mb-4 shadow-sm border-0 bg-white no-print" style="border-radius: 12px;">
        <div class="row g-2 align-items-center">
            
            <div class="col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">Pilih Akun (COA)</label>
                <select name="account_code" class="form-select form-select-sm select2-account">
                    @foreach($accounts as $acc)
                        <option value="{{ $acc->account_code }}" {{ $accCode == $acc->account_code ? 'selected' : '' }}>
                            {{ $acc->account_code }} - {{ $acc->account_name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Tanggal Awal</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ $startDate }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Tanggal Akhir</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ $endDate }}">
            </div>

            <div class="col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Baris per Halaman</label>
                <select name="per_page" class="form-select form-select-sm">
                    <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                </select>
            </div>

            <div class="col-md-2 d-flex gap-2 mt-md-4 pt-md-1">
                <button type="submit" class="btn btn-sm btn-primary fw-bold w-100"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                <button type="button" onclick="window.print()" class="btn btn-sm btn-outline-secondary fw-bold px-3"><i class="fa-solid fa-print"></i></button>
            </div>

        </div>
    </form>

    <!-- AREA NOTIFIKASI SESSION -->
    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i> {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div id="print-area" class="card border-0 shadow-sm p-0" style="border-radius: 12px;">
        
        <div class="border-bottom p-4 bg-white d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold mb-1 text-uppercase" style="color: #1e293b;">
                    KARTU AKUN: {{ $selectedAccount->account_code ?? $accCode }} - {{ $selectedAccount->account_name ?? '' }}
                </h5>
                <p class="text-muted small mb-0">Periode: {{ date('d M Y', strtotime($startDate)) }} s/d {{ date('d M Y', strtotime($endDate)) }}</p>
            </div>
            <div class="text-end bg-light p-2 px-3 rounded border">
                <span class="small text-muted d-block fw-bold">SALDO NORMAL</span>
                <span class="badge bg-dark fw-bold fs-6">{{ $isDebetNormal ? 'DEBET' : 'KREDIT' }}</span>
            </div>
        </div>

        <div class="table-responsive bg-white">
            <table class="table ledger-table align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th width="11%" class="ps-4">Tanggal</th>
                        <th width="14%">No. Bukti</th>
                        <th width="33%">Keterangan Transaksi</th>
                        <th width="13%" class="text-end">Debet (Rp)</th>
                        <th width="13%" class="text-end">Kredit (Rp)</th>
                        <th width="11%" class="text-end">Saldo Akhir (Rp)</th>
                        <th width="5%" class="text-center pe-4 no-print">Jurnal</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light fw-bold">
                        <td colspan="3" class="ps-4 text-uppercase text-muted">
                            SALDO AWAL 
                            @if($page > 1) 
                                <span class="text-primary">(Pindahan ke Hal {{ $page }})</span>
                            @else 
                                <span class="text-secondary">(Sebelum {{ date('d/m/Y', strtotime($startDate)) }})</span>
                            @endif
                        </td>
                        <td class="text-end">-</td>
                        <td class="text-end">-</td>
                        <td class="text-end text-nowrap text-primary nominal-number">{{ number_format($pageOpeningBalance, 2, ',', '.') }}</td>
                        <td class="text-center pe-4 no-print">-</td>
                    </tr>

                    @php 
                        $runningBalance = $pageOpeningBalance;
                        $totalPageBalance = 0;
                    @endphp
                    @forelse($transactions as $tx)
                        @php 
                            $deb = $tx->position == 'DEBET' ? $tx->amount : 0;
                            $kre = $tx->position == 'KREDIT' ? $tx->amount : 0;
                            
                            if ($isDebetNormal) {
                                $runningBalance += ($deb - $kre);
                            } else {
                                $runningBalance += ($kre - $deb);
                            }
                            
                            $totalPageBalance += $runningBalance;
                        @endphp
                        <tr>
                            <td class="ps-4 fw-medium text-nowrap">{{ date('d M Y', strtotime($tx->transaction_date)) }}</td>
                            <td>
                                <a href="{{ route('trace.document', $tx->evidence_number) }}" 
                                   class="text-primary text-decoration-none fw-bold" 
                                   title="Klik untuk menelusuri dokumen asal">
                                   <i class="fa-solid fa-link fa-sm me-1"></i> {{ $tx->evidence_number }}
                                </a>
                            </td>
                            <td class="fw-medium text-dark">{{ $tx->description }}</td>
                            
                            <td class="text-end text-nowrap nominal-number {{ $deb > 0 ? 'text-success fw-bold' : 'text-muted' }}">
                                {{ $deb > 0 ? number_format($deb, 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end text-nowrap nominal-number {{ $kre > 0 ? 'text-danger fw-bold' : 'text-muted' }}">
                                {{ $kre > 0 ? number_format($kre, 2, ',', '.') : '-' }}
                            </td>
                            <td class="text-end text-nowrap fw-bold text-dark nominal-number">
                                {{ number_format($runningBalance, 2, ',', '.') }}
                            </td>
                            
                            <td class="text-center pe-4 no-print">
                                @if($tx->header && $tx->header->details)
                                    <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalJournal-{{ $tx->header->journal_id ?? $tx->header->id }}" title="Lihat Pasangan Jurnal">
                                        <i class="fa-solid fa-eye small"></i>
                                    </button>
                                @else
                                    <span class="text-muted small">-</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center py-5 text-muted small">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                Tidak ada transaksi mutasi pada rentang tanggal/halaman ini.
                            </td>
                        </tr>
                    @endforelse

                    <tr class="table-dark fw-bold" style="font-size: 0.9rem;">
                        <td colspan="5" class="ps-4 text-uppercase">TOTAL SALDO HALAMAN INI</td>
                        <td class="text-end text-nowrap text-warning nominal-number">{{ number_format($totalPageBalance, 2, ',', '.') }}</td>
                        <td class="no-print pe-4"></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="card-footer bg-white border-top p-3 d-flex justify-content-end no-print">
            {{ $transactions->links() }}
        </div>

    </div>

</div>

@foreach($transactions as $tx)
    @if($tx->header && $tx->header->details)
        @php 
            $headerObj = $tx->header; 
            $modalId = $headerObj->journal_id ?? $headerObj->id;
        @endphp
        <div class="modal fade" id="modalJournal-{{ $modalId }}" tabindex="-1" aria-labelledby="modalLabel-{{ $modalId }}" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-centered">
                <div class="modal-content border-0 shadow ledger-wrapper">
                    
                    <div class="modal-header modal-journal-header px-4 py-3">
                        <div>
                            <span class="badge bg-primary mb-1">Rincian Jurnal Umum</span>
                            <h5 class="modal-title fw-bold text-dark mb-0" id="modalLabel-{{ $modalId }}">
                                No. Bukti: {{ $headerObj->evidence_number }}
                            </h5>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>

                    <div class="modal-body px-4 py-3">
                        <div class="row g-2 mb-3 small">
                            <div class="col-6">
                                <span class="text-muted d-block fw-bold" style="font-size: 0.75rem;">TANGGAL TRANSAKSI</span>
                                <span class="fw-medium text-dark">{{ date('d M Y', strtotime($headerObj->transaction_date)) }}</span>
                            </div>
                            <div class="col-6">
                                <span class="text-muted d-block fw-bold" style="font-size: 0.75rem;">KETERANGAN UMUM</span>
                                <span class="fw-medium text-dark">{{ $headerObj->description ?? '-' }}</span>
                            </div>
                        </div>

                        <div class="border rounded overflow-hidden">
                            <table class="table modal-journal-table mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%" class="ps-3">Nama Akun & Ref</th>
                                        <th width="25%" class="text-end">Debet (Rp)</th>
                                        <th width="25%" class="text-end pe-3">Kredit (Rp)</th>
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
                                                        {{ $det->account->account_name ?? 'Akun Terhapus' }}
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
                                        <td class="ps-3 text-end text-uppercase text-muted small">TOTAL BALANCE</td>
                                        <td class="text-end text-nowrap nominal-number text-success">{{ number_format($totDeb, 2, ',', '.') }}</td>
                                        <td class="text-end text-nowrap nominal-number pe-3 text-danger">{{ number_format($totKre, 2, ',', '.') }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                    </div>

                    <div class="modal-footer bg-light py-2">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Tutup</button>
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
            placeholder: 'Ketik untuk mencari kode atau nama akun...'
        });
    });
</script>
@endsection