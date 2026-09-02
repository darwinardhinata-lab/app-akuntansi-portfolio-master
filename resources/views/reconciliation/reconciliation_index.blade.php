{{-- Simpan di: resources/views/reconciliation/index.blade.php --}}
{{-- Sesuaikan @extends() dengan nama layout utama Anda jika berbeda --}}
@extends('layouts.app')

@section('title', 'Rekonsiliasi Jubelio vs ERP')

@section('content')
<div class="container-fluid">

    <div class="d-flex justify-content-between align-items-center mb-3">
        <h4 class="mb-0">🔍 Rekonsiliasi Laporan: Jubelio vs ERP</h4>
    </div>

    <p class="text-muted">
        Bandingkan total per akun (Pendapatan, HPP, Biaya, Pendapatan Lain, Beban Lain — prefix 4-9)
        antara file sumber Jubelio dan ledger ERP untuk periode yang sama. Akun dengan selisih
        di atas toleransi akan ditandai, sehingga gap data bisa ditemukan sebelum laporan Laba Rugi dicetak.
    </p>

    @if (session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- ============================= FORM UPLOAD ============================= --}}
    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ route('reconciliation.compare') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label">File Sumber Jubelio (CSV)</label>
                        <input type="file" name="jubelio_file" class="form-control" accept=".csv,.txt" required>
                        <small class="text-muted">Kolom minimal: Kode Akun, Nama Akun, Nominal (Rp).</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Awal</label>
                        <input type="date" name="start_date" class="form-control"
                               value="{{ old('start_date', $startDate ?? date('Y-m-01')) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tanggal Akhir</label>
                        <input type="date" name="end_date" class="form-control"
                               value="{{ old('end_date', $endDate ?? date('Y-m-t')) }}" required>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Toleransi Selisih (Rp)</label>
                        <input type="number" step="0.01" name="tolerance" class="form-control"
                               value="{{ old('tolerance', $tolerance ?? 1000) }}">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Bandingkan</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    {{-- ============================= HASIL ============================= --}}
    @isset($result)
        @php
            $summary = $result['summary'];
            $rows    = $result['rows'];
            $fmt = fn($v) => 'Rp ' . number_format($v, 2, ',', '.');
        @endphp

        {{-- Ringkasan --}}
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-4 fw-bold">{{ $summary['total_akun'] }}</div>
                        <div class="text-muted small">Total Akun Dibandingkan</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <div class="fs-4 fw-bold text-success">{{ $summary['match_count'] }}</div>
                        <div class="text-muted small">Cocok (MATCH)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-danger">
                    <div class="card-body">
                        <div class="fs-4 fw-bold text-danger">{{ $summary['mismatch_count'] }}</div>
                        <div class="text-muted small">Nominal Beda</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <div class="fs-4 fw-bold text-warning">{{ $summary['missing_in_erp_count'] }}</div>
                        <div class="text-muted small">Belum Ada di ERP</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-warning">
                    <div class="card-body">
                        <div class="fs-4 fw-bold text-warning">{{ $summary['missing_in_jub_count'] }}</div>
                        <div class="text-muted small">Tidak Ada di Sumber</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-6 fw-bold">{{ $fmt($summary['total_abs_selisih']) }}</div>
                        <div class="text-muted small">Total Selisih Absolut</div>
                    </div>
                </div>
            </div>
        </div>

        @if (($summary['skipped_rows'] ?? 0) > 0)
            <div class="alert alert-warning">
                ⚠️ {{ $summary['skipped_rows'] }} baris di file yang diupload tidak bisa dikenali (kode akun/nominal
                tidak terdeteksi) dan dilewati. Periksa kembali format kolomnya jika jumlah ini besar.
            </div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-2">
            <h5 class="mb-0">Detail per Akun</h5>
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportSelisihToCsv()">
                ⬇ Unduh CSV (khusus yang bermasalah)
            </button>
        </div>

        <div class="table-responsive">
            <table class="table table-sm table-bordered table-hover align-middle" id="reconTable">
                <thead class="table-light">
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jubelio</th>
                        <th class="text-end">ERP</th>
                        <th class="text-end">Selisih</th>
                        <th class="text-center">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php
                            $badgeClass = match ($row['status']) {
                                'MATCH' => 'success',
                                'MISMATCH' => 'danger',
                                'TIDAK_ADA_DI_ERP' => 'warning',
                                'TIDAK_ADA_DI_JUBELIO' => 'secondary',
                                default => 'light',
                            };
                            $badgeLabel = match ($row['status']) {
                                'MATCH' => 'Cocok',
                                'MISMATCH' => 'Nominal Beda',
                                'TIDAK_ADA_DI_ERP' => 'Belum di ERP',
                                'TIDAK_ADA_DI_JUBELIO' => 'Tidak di Sumber',
                                default => $row['status'],
                            };
                        @endphp
                        <tr class="{{ $row['status'] !== 'MATCH' ? 'table-' . ($badgeClass === 'danger' ? 'danger' : 'warning') . '-subtle' : '' }}">
                            <td>{{ $row['account_code'] }}</td>
                            <td>{{ $row['account_name'] }}</td>
                            <td class="text-end">{{ $fmt($row['jubelio']) }}</td>
                            <td class="text-end">{{ $fmt($row['erp']) }}</td>
                            <td class="text-end fw-bold {{ $row['status'] !== 'MATCH' ? 'text-danger' : '' }}">
                                {{ $fmt($row['selisih']) }}
                            </td>
                            <td class="text-center">
                                <span class="badge bg-{{ $badgeClass }}">{{ $badgeLabel }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="fw-bold table-light">
                        <td colspan="2">TOTAL</td>
                        <td class="text-end">{{ $fmt($summary['jubelio_grand_total']) }}</td>
                        <td class="text-end">{{ $fmt($summary['erp_grand_total']) }}</td>
                        <td class="text-end">{{ $fmt($summary['erp_grand_total'] - $summary['jubelio_grand_total']) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endisset

</div>

<script>
    // Export sisi client, hanya baris yang statusnya BUKAN 'Cocok' — tanpa perlu upload ulang file.
    function exportSelisihToCsv() {
        const table = document.getElementById('reconTable');
        if (!table) return;

        let csv = 'Kode Akun,Nama Akun,Jubelio,ERP,Selisih,Status\n';
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const statusBadge = row.querySelector('.badge');
            if (statusBadge && statusBadge.textContent.trim() === 'Cocok') return;

            const cols = row.querySelectorAll('td');
            const vals = Array.from(cols).map(c => '"' + c.textContent.trim().replace(/"/g, '""') + '"');
            csv += vals.join(',') + '\n';
        });

        const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = 'rekonsiliasi_selisih.csv';
        link.click();
    }
</script>
@endsection
