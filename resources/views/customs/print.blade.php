<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cetak {{ $document->document_type }} {{ $document->internal_number }} - {{ config('app.name') }}</title>
    {{-- Halaman cetak standalone: TIDAK memakai @vite (manifest Vite tidak tersedia
         di lingkungan testing & tidak dibutuhkan untuk cetak), cukup CSS ringan sendiri. --}}
    <style>
        body { font-family: system-ui, -apple-system, "Segoe UI", Roboto, Arial, sans-serif; margin: 0; background: #fff; color: #212529; }
        .container { max-width: 900px; margin: 0 auto; padding: 24px 16px; }
        .card { background: #fff; border: 1px solid #dee2e6; border-radius: 6px; margin-bottom: 20px; }
        .card-header { padding: 10px 16px; font-weight: 700; border-bottom: 1px solid #dee2e6; background: #f8f9fa; }
        .card-body { padding: 10px 16px; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; margin-bottom: 0; }
        td, th { padding: 6px 8px; text-align: left; border: 1px solid #e9ecef; vertical-align: top; }
        th { background: #f8f9fa; }
        .text-muted { color: #6c757d; }
        .fw-bold { font-weight: 700; }
        .text-end { text-align: right; }
        .text-center { text-align: center; }
        .no-print { text-align: right; margin-bottom: 10px; }
        .btn { display: inline-block; padding: 6px 14px; border: 1px solid #0d6efd; color: #0d6efd; background: #fff; border-radius: 4px; cursor: pointer; text-decoration: none; font-size: 13px; margin-left: 6px; }
        h4 { margin: 0 0 4px; }
        p { margin: 0; }
        @media print {
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-light">
    <div class="container py-4">
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <h4 class="fw-bold mb-0">Dokumen Kepabeanan ({{ $document->document_type }})</h4>
                        <p class="text-muted small mb-0">Host-to-Host CEISA 4.0 — {{ __('customs.module_title') }}</p>
                    </div>
                    <div class="text-end no-print">
                        <button type="button" class="btn btn-primary btn-sm" onclick="window.print()">
                            <i class="fa-solid fa-print me-1"></i> Cetak / PDF
                        </button>
                        <a href="{{ route('customs.show', $document->id) }}" class="btn btn-outline-secondary btn-sm">
                            Kembali
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white fw-bold">Ringkasan Dokumen</div>
            <div class="card-body py-2">
                <table class="table table-sm mb-0" style="font-size: 13px;">
                    <tr><td style="width: 35%;" class="text-muted">{{ __('customs.field.internal_number') }}</td><td class="fw-bold">{{ $document->internal_number }}</td></tr>
                    <tr><td class="text-muted">{{ __('customs.field.document_type') }}</td><td>{{ $document->document_type }}</td></tr>
                    <tr><td class="text-muted">Status</td><td>{{ $document->status }}</td></tr>
                    <tr><td class="text-muted">{{ __('customs.field.nomor_aju') }}</td><td>{{ $document->nomor_aju ?? '-' }}</td></tr>
                    <tr><td class="text-muted">{{ __('customs.field.nomor_pendaftaran') }}</td><td>{{ $document->nomor_pendaftaran ?? '-' }}</td></tr>
                    <tr><td class="text-muted">{{ __('customs.field.kode_kantor') }}</td><td>{{ $document->kode_kantor ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Mata Uang / Kurs</td><td>{{ $document->currency ?? '-' }} / {{ $document->exchange_rate ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Total Nilai</td><td>{{ $document->total_value ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Sumber</td><td>{{ $document->source_type ?? '-' }} #{{ $document->source_id ?? '-' }}</td></tr>
                    <tr><td class="text-muted">Dikirim / Respons</td>
                        <td>{{ $document->submitted_at?->format('d/m/Y H:i') ?? '-' }} / {{ $document->responded_at?->format('d/m/Y H:i') ?? '-' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-white fw-bold">Detail Barang</div>
            <div class="card-body p-0">
                <table class="table table-sm table-bordered align-middle mb-0" style="font-size: 12px;">
                    <thead class="bg-light">
                        <tr>
                            <th class="text-center">No</th>
                            <th>Deskripsi Barang</th>
                            <th>HS Code</th>
                            <th class="text-end">Qty</th>
                            <th class="text-center">Satuan</th>
                            <th class="text-end">Berat Bersih</th>
                            <th class="text-end">Nilai</th>
                            <th class="text-end">BM %</th>
                            <th class="text-end">PPN %</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($document->details as $detail)
                            <tr>
                                <td class="text-center">{{ $loop->iteration }}</td>
                                <td>{{ $detail->deskripsi_barang ?? '-' }}</td>
                                <td>{{ $detail->hs_code ?? '-' }}</td>
                                <td class="text-end">{{ $detail->qty }}</td>
                                <td class="text-center">{{ $detail->satuan ?? '-' }}</td>
                                <td class="text-end">{{ $detail->berat_bersih }}</td>
                                <td class="text-end">{{ number_format((float) $detail->nilai, 2) }}</td>
                                <td class="text-end">{{ $detail->tarif_bm }}</td>
                                <td class="text-end">{{ $detail->tarif_ppn }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="9" class="text-center text-muted py-4">Belum ada detail barang.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>
