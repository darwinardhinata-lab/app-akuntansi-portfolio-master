@extends('layouts.app')

@section('title', 'Rekonsiliasi Jubelio vs ERP')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-3 main-header-compact">
    <div>
        <h4 class="mb-0 fw-bold"><i class="fa-solid fa-arrows-left-right me-2 text-primary"></i>Rekonsiliasi Jubelio vs ERP</h4>
        <small class="text-muted">Bandingkan saldo per akun antara sumber Jubelio dan ledger ERP untuk periode yang sama.</small>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card border-0 shadow-sm mb-3" style="border-radius: 12px;">
    <div class="card-body">
        <form action="{{ route('reconciliation.compare') }}" method="POST" enctype="multipart/form-data" class="row g-3">
            @csrf
            <div class="col-md-4">
                <label class="form-label fw-bold">File Sumber Jubelio (CSV)</label>
                <input type="file" name="jubelio_file" class="form-control" accept=".csv,.txt" required>
                <div class="form-text">Kolom: kode akun, (nama akun), nominal. Delimiter koma/titik-koma.</div>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tanggal Awal</label>
                <input type="date" name="start_date" class="form-control" value="{{ $startDate ?? old('start_date') }}" required>
            </div>
            <div class="col-md-3">
                <label class="form-label fw-bold">Tanggal Akhir</label>
                <input type="date" name="end_date" class="form-control" value="{{ $endDate ?? old('end_date') }}" required>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Toleransi (Rp)</label>
                <input type="number" name="tolerance" class="form-control" value="{{ $tolerance ?? 1000 }}" min="0" step="1">
            </div>
            <div class="col-md-2">
                <label class="form-label fw-bold">Format File</label>
                <select name="format" class="form-select">
                    <option value="auto" {{ ($format ?? 'auto') == 'auto' ? 'selected' : '' }}>Auto Detect</option>
                    <option value="coded" {{ ($format ?? 'auto') == 'coded' ? 'selected' : '' }}>Berkode (ada kode akun)</option>
                    <option value="native" {{ ($format ?? 'auto') == 'native' ? 'selected' : '' }}>Native Jubelio (tanpa kode)</option>
                </select>
                <div class="form-text">Pilih "Native" jika file tidak memiliki kolom kode akun.</div>
            </div>
            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary px-4">
                    <i class="fa-solid fa-magnifying-glass me-2"></i>Bandingkan
                </button>
            </div>
        </form>
    </div>
</div>

@if(!empty($result))
    @php
        $summary = $result['summary'];
        $rows = $result['rows'];
    @endphp

    <div class="row g-3 mb-3">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Total Akun</div>
                    <div class="fs-4 fw-bold">{{ $summary['total_akun'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Cocok</div>
                    <div class="fs-4 fw-bold text-success">{{ $summary['match_count'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Tidak Cocok</div>
                    <div class="fs-4 fw-bold text-danger">{{ $summary['mismatch_count'] }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Belum di ERP</div>
                    <div class="fs-4 fw-bold text-warning">{{ $summary['missing_in_erp_count'] }}</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Grand Total Jubelio</div>
                    <div class="fs-5 fw-bold">Rp {{ number_format($summary['jubelio_grand_total'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100" style="border-radius:12px;">
                <div class="card-body">
                    <div class="text-muted small fw-bold">Grand Total ERP</div>
                    <div class="fs-5 fw-bold">Rp {{ number_format($summary['erp_grand_total'], 2, ',', '.') }}</div>
                </div>
            </div>
        </div>
    </div>

    @if($summary['skipped_rows'] > 0)
        <div class="alert alert-warning">
            <i class="fa-solid fa-triangle-exclamation me-2"></i>
            <strong>{{ $summary['skipped_rows'] }}</strong> baris di file Jubelio tidak dapat di-parse dan dilewati.
        </div>
    @endif

    @if(!empty($summary['control_warnings']))
        <div class="alert alert-danger">
            <i class="fa-solid fa-circle-exclamation me-2"></i>
            <strong>Peringatan Kontrol:</strong>
            <ul class="mb-0 mt-2">
                @foreach($summary['control_warnings'] as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if(($summary['fuzzy_count'] ?? 0) > 0)
        <div class="alert alert-info">
            <i class="fa-solid fa-circle-info me-2"></i>
            <strong>{{ $summary['fuzzy_count'] }}</strong> akun dicocokkan menggunakan kemiripan nama (fuzzy match). Harap dicek manual.
        </div>
    @endif

    <div class="d-flex justify-content-between align-items-center mb-2">
        <div class="fw-bold">Detail Rekonsiliasi</div>
        <div class="d-flex gap-2">
            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="exportCsv()">
                <i class="fa-solid fa-download me-1"></i>Unduh CSV
            </button>
        </div>
    </div>

    <div class="card border-0 shadow-sm" style="border-radius:12px;">
        <div class="card-body p-0 table-responsive">
            <table class="table table-hover mb-0" id="reconTable">
                <thead class="table-light">
                    <tr>
                        <th>Kode Akun</th>
                        <th>Nama Akun</th>
                        <th class="text-end">Jubelio</th>
                        <th class="text-end">ERP</th>
                        <th class="text-end">Selisih</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($rows as $r)
                    @php
                        $badge = match($r['status']) {
                            'MATCH' => '<span class="badge bg-success">Cocok</span>',
                            'MISMATCH' => '<span class="badge bg-danger">Nominal Beda</span>',
                            'TIDAK_ADA_DI_ERP' => '<span class="badge bg-warning text-dark">Belum di ERP</span>',
                            'TIDAK_ADA_DI_JUBELIO' => '<span class="badge bg-white text-dark border">Tidak di Sumber</span>',
                            default => '<span class="badge bg-secondary">'.e($r['status']).'</span>'
                        };
                    @endphp
                    <tr>
                        <td class="fw-bold">{{ $r['account_code'] }}</td>
                        <td>{{ $r['account_name'] }}</td>
                        <td class="text-end">Rp {{ number_format($r['jubelio'], 2, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($r['erp'], 2, ',', '.') }}</td>
                        <td class="text-end">Rp {{ number_format($r['selisih'], 2, ',', '.') }}</td>
                        <td>{!! $badge !!}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <form id="exportForm" action="{{ route('reconciliation.export') }}" method="POST" enctype="multipart/form-data" class="d-none">
        @csrf
        <input type="file" name="jubelio_file" id="exportFile">
        <input type="hidden" name="start_date" value="{{ $startDate ?? '' }}">
        <input type="hidden" name="end_date" value="{{ $endDate ?? '' }}">
        <input type="hidden" name="tolerance" value="{{ $tolerance ?? 1000 }}">
    </form>
@endif
@endsection

@push('scripts')
<script>
function exportCsv() {
    var fileInput = document.querySelector('input[name="jubelio_file"]');
    if (window.File && window.FileReader && window.FileList && window.Blob && fileInput && fileInput.files && fileInput.files.length > 0) {
        var dt = new DataTransfer();
        dt.items.add(fileInput.files[0]);
        document.getElementById('exportFile').files = dt.files;
        document.getElementById('exportForm').submit();
        return;
    }

    var formData = new FormData();
    formData.append('jubelio_file', 'placeholder');
    formData.append('start_date', document.querySelector('input[name="start_date"]').value);
    formData.append('end_date', document.querySelector('input[name="end_date"]').value);
    formData.append('tolerance', document.querySelector('input[name="tolerance"]').value);
    formData.append('_token', document.querySelector('input[name="_token"]').value);

    fetch("{{ route('reconciliation.export') }}", {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
    }).then(function(resp){
        if (!resp.ok) throw new Error('Gagal export');
        return resp.blob();
    }).then(function(blob){
        var url = window.URL.createObjectURL(blob);
        var a = document.createElement('a');
        var start = document.querySelector('input[name="start_date"]').value;
        var end = document.querySelector('input[name="end_date"]').value;
        a.href = url;
        a.download = 'Rekonsiliasi_Selisih_' + start + '_sd_' + end + '.csv';
        document.body.appendChild(a);
        a.click();
        a.remove();
        window.URL.revokeObjectURL(url);
    }).catch(function(err){
        alert('Export gagal. Pastikan file sumber masih可选择 untuk diunggah kembali, atau coba lagi.');
    });
}
</script>
@endpush