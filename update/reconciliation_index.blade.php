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
        Bandingkan total per akun antara file sumber Jubelio dan ledger ERP untuk periode yang sama.
        Mendukung file dengan kode akun (CSV per-akun) maupun cetakan/export native Jubelio yang
        hanya berisi nama akun tanpa kode — tinggal upload apa adanya, format terdeteksi otomatis.
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
                    <div class="col-md-3">
                        <label class="form-label">File Sumber Jubelio</label>
                        <input type="file" name="jubelio_file" class="form-control" accept=".csv,.txt" required>
                        <small class="text-muted">CSV berkode akun ATAU export/print native Jubelio.</small>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Format File</label>
                        <select name="format" class="form-select">
                            <option value="auto" {{ old('format', $format ?? 'auto') == 'auto' ? 'selected' : '' }}>Otomatis (deteksi)</option>
                            <option value="coded" {{ old('format', $format ?? '') == 'coded' ? 'selected' : '' }}>CSV Berkode Akun</option>
                            <option value="native" {{ old('format', $format ?? '') == 'native' ? 'selected' : '' }}>Native Jubelio (Nama Saja)</option>
                        </select>
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
                        <label class="form-label">Toleransi (Rp)</label>
                        <input type="number" step="0.01" name="tolerance" class="form-control"
                               value="{{ old('tolerance', $tolerance ?? 1000) }}">
                    </div>
                    <div class="col-md-1">
                        <button type="submit" class="btn btn-primary w-100">Cek</button>
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

        <div class="alert alert-light border d-flex justify-content-between align-items-center">
            <span>
                Format terdeteksi/dipakai:
                <strong>{{ $summary['format'] === 'native' ? 'Native Jubelio (dicocokkan by nama akun)' : 'CSV Berkode Akun (dicocokkan by kode)' }}</strong>
            </span>
            @if (($summary['fuzzy_count'] ?? 0) > 0)
                <span class="badge bg-info text-dark">{{ $summary['fuzzy_count'] }} akun dicocokkan via kemiripan nama (perlu verifikasi manual)</span>
            @endif
        </div>

        @if (!empty($summary['control_warnings']))
            <div class="alert alert-warning">
                <strong>⚠️ Kontrol Total (khusus format native):</strong>
                <ul class="mb-0 mt-1">
                    @foreach ($summary['control_warnings'] as $w)
                        <li>{{ $w }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Ringkasan --}}
        <div class="row mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <div class="fs-4 fw-bold">{{ $summary['total_akun'] }}</div>
                        <div class="text-muted small">Total Akun</div>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center border-success">
                    <div class="card-body">
                        <div class="fs-4 fw-bold text-success">{{ $summary['match_count'] }}</div>
                        <div class="text-muted small">Cocok</div>
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
            <div class="alert alert-secondary">
                ℹ️ {{ $summary['skipped_rows'] }} baris di file tidak dikenali (tanpa nominal terbaca / bukan baris akun) dan dilewati otomatis.
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
                        <th class="text-center">Kecocokan</th>
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
                            $matchBadge = match ($row['match_type'] ?? '-') {
                                'FUZZY' => '<span class="badge bg-info text-dark">Mirip — cek manual</span>',
                                'EXACT' => '<span class="badge bg-light text-dark border">Nama Persis</span>',
                                'KODE' => '<span class="badge bg-light text-dark border">Kode Akun</span>',
                                default => '-',
                            };
                        @endphp
                        <tr>
                            <td>{{ $row['account_code'] }}</td>
                            <td>{{ $row['account_name'] }}</td>
                            <td class="text-end">{{ $fmt($row['jubelio']) }}</td>
                            <td class="text-end">{{ $fmt($row['erp']) }}</td>
                            <td class="text-end fw-bold {{ $row['status'] !== 'MATCH' ? 'text-danger' : '' }}">
                                {{ $fmt($row['selisih']) }}
                            </td>
                            <td class="text-center">{!! $matchBadge !!}</td>
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
                        <td colspan="2"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    @endisset

</div>

<script>
    function exportSelisihToCsv() {
        const table = document.getElementById('reconTable');
        if (!table) return;

        let csv = 'Kode Akun,Nama Akun,Jubelio,ERP,Selisih,Kecocokan,Status\n';
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            const statusBadge = row.querySelectorAll('.badge');
            const statusText = statusBadge.length ? statusBadge[statusBadge.length - 1].textContent.trim() : '';
            if (statusText === 'Cocok') return;

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
