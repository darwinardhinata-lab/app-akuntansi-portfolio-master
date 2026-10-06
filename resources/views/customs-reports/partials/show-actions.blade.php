{{-- Partial: Show View Header Card + Action Bar --}}
<div class="card shadow-sm border-0 mb-3">
    <div class="card-header bg-white fw-bold">
        <i class="fa-solid fa-file-invoice text-info me-1"></i> Header Periode
    </div>
    <div class="card-body py-2">
        <div class="row g-2 text-small">
            <div class="col-md-3"><strong>Type:</strong> {{ $period->report_type }}</div>
            <div class="col-md-3"><strong>Bulan:</strong> {{ $period->periode_bulan }}</div>
            <div class="col-md-3"><strong>Tahun:</strong> {{ $period->periode_tahun }}</div>
            <div class="col-md-3"><strong>Dibuat:</strong> {{ $period->created_at ? $period->created_at->format('d/m/Y H:i') : '-' }}</div>
        </div>
        @if($period->catatan)
            <div class="mt-1"><strong>Catatan:</strong> {{ $period->catatan }}</div>
        @endif
    </div>
</div>

<div class="card shadow-sm border-0 mb-3">
    <div class="card-body d-flex flex-wrap gap-2 py-2">

        @if($period->isDraft())
            @if(config('customs.enabled', false) && in_array($period->report_type, [\App\Modules\CustomsReports\Models\ReportPeriod::TYPE_PEMASUKAN, \App\Modules\CustomsReports\Models\ReportPeriod::TYPE_PENGELUARAN]))
                <button type="button" class="btn btn-outline-info btn-sm" onclick="openPopulateModal()">
                    <i class="fa-solid fa-magic me-1"></i> Populate dari H2H
                </button>
            @endif
            @if(! config('customs.enabled'))
                <button type="button" class="btn btn-outline-info btn-sm" onclick="populateMutasi()">
                    <i class="fa-solid fa-magic me-1"></i> {{ __('customs_settings.sync') }}
                </button>
            @endif

            @if(config('customs.enabled') || ! app(\App\Modules\CustomsReports\Services\CustomsSettingsService::class)->autoSyncInternal())
            <label class="btn btn-outline-success btn-sm mb-0" id="importBtn">
                <i class="fa-solid fa-file-arrow-up me-1"></i> Import Excel
                <input type="file" id="importFileInput" name="file_excel" accept=".xlsx,.xls,.csv" style="display:none">
            </label>

            <a href="{{ route('customs-reports.template', $period) }}" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Template
            </a>
            @endif

            <button type="button" class="btn btn-outline-warning btn-sm" onclick="finalizePeriod()">
                <i class="fa-solid fa-check-to-slot me-1"></i> Finalisasi
            </button>
        @endif

        @if($period->isFinal())
            <button type="button" class="btn btn-outline-primary btn-sm" onclick="markUploaded()">
                <i class="fa-solid fa-upload me-1"></i> Tandai Diunggah
            </button>
        @endif

        @if($lines->isNotEmpty())
            <a href="{{ route('customs-reports.export', $period) }}" class="btn btn-outline-success btn-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </a>
        @endif

    </div>
</div>

{{-- Hidden Forms --}}
<form id="finalizeForm" action="{{ route('customs-reports.finalize', $period) }}" method="POST">@csrf</form>
<form id="markUploadedForm" action="{{ route('customs-reports.mark-uploaded', $period) }}" method="POST">@csrf</form>
<form id="populateForm" action="{{ route('customs-reports.populate-from-h2h', $period) }}" method="POST">@csrf</form>
<form id="populateMutasiForm" action="{{ route('customs-reports.populate-mutasi', $period) }}" method="POST">@csrf</form>

<script>
document.getElementById('importFileInput')?.addEventListener('change', function(e) {
    var file = e.target.files[0];
    if (!file) return;
    var fd = new FormData();
    fd.append('file_excel', file);
    fd.append('_token', '{{ csrf_token() }}');
    fetch('{{ route('customs-reports.import', $period) }}', {
        method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' }
    }).then(function(){ window.location.reload(); }).catch(function(){ window.location.reload(); });
});
function finalizePeriod() {
    if (confirm('Finalisasi? Setelah FINAL, tidak bisa import lagi.')) {
        document.getElementById('finalizeForm').submit();
    }
}
function markUploaded() {
    if (confirm('Tandai sudah diunggah ke CEISA?')) {
        document.getElementById('markUploadedForm').submit();
    }
}
function openPopulateModal() {
    if (confirm('Import otomatis dari dokumen H2H (PIB/PEB dengan status SPPB/NPE)?')) {
        document.getElementById('populateForm').submit();
    }
}
function populateMutasi() {
    if (confirm('Bangun ulang laporan dari transaksi sistem? Seluruh baris draft, termasuk input/import sebelumnya, akan diganti dengan data sumber terbaru.')) {
        document.getElementById('populateMutasiForm').submit();
    }
}
</script>