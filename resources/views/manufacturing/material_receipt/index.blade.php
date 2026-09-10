@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', __('erp.bc_material_receipt_mrn') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.material_receipt_note_title') }}</h3>
            <p class="text-muted small mb-0">Penerimaan bahan baku (yarn/kain) dari supplier — otomatis posting jurnal Persediaan.</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('mfg.material-receipts.index', array_merge(request()->query(), ['export' => 'excel'])) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fa-solid fa-file-import me-1"></i> Import Massal
            </button>
            <a href="{{ route('mfg.material-receipts.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Buat MRN Baru
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Cari No. MRN" value="{{ $search }}">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100">{{ __('erp.filter_label') }}</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        <tr><th>{{ __('erp.mrn_no') }}</th><th>{{ __('erp.date') }}</th><th>{{ __('erp.supplier_label') }}</th><th>{{ __('erp.gross_weight') }}</th><th>{{ __('erp.bc_tax') }}</th><th>{{ __('erp.net_weight') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th></tr>
                    </thead>
                    <tbody>
                        @forelse ($receipts as $r)
                            <tr>
                                <td class="fw-bold py-2">{{ $r->receipt_number }}</td>
                                <td class="py-2">{{ \Carbon\Carbon::parse($r->receipt_date)->format('d M Y') }}</td>
                                <td class="py-2">{{ $r->supplier->supplier_name ?? '-' }}</td>
                                <td class="text-end py-2">Rp {{ number_format($r->gross_amount, 2) }}</td>
                                <td class="text-end py-2">Rp {{ number_format($r->tax_amount, 2) }}</td>
                                <td class="text-end py-2 fw-bold">Rp {{ number_format($r->net_amount, 2) }}</td>
                                <td class="text-center py-2"><span class="badge {{ $r->status === 'POSTED' ? 'bg-success' : ($r->status === 'VOIDED' ? 'bg-danger' : 'bg-secondary') }}">{{ $r->status }}</span></td>
                                <td class="text-center py-2"><a href="{{ route('mfg.material-receipts.show', $r->id) }}" class="btn btn-sm btn-outline-primary"><i class="fas fa-eye"></i></a></td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="text-center py-5 text-muted">{{ __('erp.no_mrn_yet') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $receipts->links() }}
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.material-receipts.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.import_bulk_mrn') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fa-solid fa-info-circle me-1"></i> Setiap baris tetap diposting lewat proses & validasi yang sama
                        dengan input manual (bisa gagal sebagian jika data salah).
                        <a href="{{ route('mfg.material-receipts.download-template') }}" class="fw-bold">{{ __('erp.download_template') }}</a>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('erp.choose_file_xlsx') }}</label>
                        <input type="file" name="file_excel" class="form-control" required accept=".xlsx,.xls,.csv">
                    </div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary fw-bold">{{ __('erp.start_import') }}</button></div>
            </div>
        </form>
    </div>
</div>
@endsection
