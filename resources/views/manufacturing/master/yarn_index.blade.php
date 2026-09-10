@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', __('erp.bc_master_yarn') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.bc_master_yarn') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.yarn_master_stock_cogs_hint') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('mfg.yarns.index', ['export' => 'excel']) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fa-solid fa-file-import me-1"></i> Import
            </button>
            <button type="button" class="btn btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="fa-solid fa-plus me-1"></i> Tambah Yarn
            </button>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm">{{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm">{{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    @endif

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('erp.search_yarn') }}" value="{{ $search ?? '' }}">
                </div>
                <div class="col-md-2"><button type="submit" class="btn btn-sm btn-outline-primary w-100">{{ __('erp.search_btn') }}</button></div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        <tr>
                            <th>{{ __('erp.code_label') }}</th><th>{{ __('erp.kind_label') }}</th><th>{{ __('erp.count_label') }}</th><th>{{ __('erp.mfg_composition') }}</th><th>{{ __('erp.color_label') }}</th>
                            <th>{{ __('erp.unit') }}</th><th>{{ __('erp.stock_label') }}</th><th>{{ __('erp.average_cogs') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($yarns as $yarn)
                            <tr>
                                <td class="fw-bold py-2">{{ $yarn->yarn_code }}</td>
                                <td class="py-2">{{ $yarn->yarn_type }}</td>
                                <td class="py-2">{{ $yarn->yarn_count ?? '-' }}</td>
                                <td class="py-2">{{ $yarn->composition ?? '-' }}</td>
                                <td class="py-2">{{ $yarn->color ?? '-' }}</td>
                                <td class="text-center py-2">{{ $yarn->unit }}</td>
                                <td class="text-end py-2">{{ number_format($yarn->stock_quantity, 2) }}</td>
                                <td class="text-end py-2">Rp {{ number_format($yarn->average_cost, 2) }}</td>
                                <td class="text-center py-2">
                                    <span class="badge {{ $yarn->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $yarn->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="text-center py-2">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal"
                                            data-bs-target="#modalEdit{{ $yarn->id }}"><i class="fas fa-edit"></i></button>
                                        <form action="{{ route('mfg.yarns.destroy', $yarn->id) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm('Yakin hapus yarn ini?')">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            {{-- Modal Edit per baris --}}
                            <div class="modal fade" id="modalEdit{{ $yarn->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <form action="{{ route('mfg.yarns.update', $yarn->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">{{ __('erp.edit_yarn') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <div class="mb-2"><label class="form-label">{{ __('erp.yarn_code') }}</label>
                                                    <input type="text" name="yarn_code" class="form-control" value="{{ $yarn->yarn_code }}" required></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.kind_label') }}</label>
                                                    <input type="text" name="yarn_type" class="form-control" value="{{ $yarn->yarn_type }}" required></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.count_label') }}</label>
                                                    <input type="text" name="yarn_count" class="form-control" value="{{ $yarn->yarn_count }}"></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.mfg_composition') }}</label>
                                                    <input type="text" name="composition" class="form-control" value="{{ $yarn->composition }}"></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.color_label') }}</label>
                                                    <input type="text" name="color" class="form-control" value="{{ $yarn->color }}"></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.unit') }}</label>
                                                    <input type="text" name="unit" class="form-control" value="{{ $yarn->unit }}" required></div>
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $yarn->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label">{{ __('erp.active_label') }}</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="10" class="text-center py-5 text-muted">{{ __('erp.no_yarn_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $yarns->links() }}
</div>

{{-- Modal Create --}}
<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.yarns.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.add_yarn') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">{{ __('erp.yarn_code') }}</label>
                        <input type="text" name="yarn_code" class="form-control" required placeholder="Y-COTTON-30S"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.kind_label') }}</label>
                        <input type="text" name="yarn_type" class="form-control" required placeholder="Cotton Combed"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.count_label') }}</label>
                        <input type="text" name="yarn_count" class="form-control" placeholder="30s"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.mfg_composition') }}</label>
                        <input type="text" name="composition" class="form-control" placeholder="100% Cotton"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.color_label') }}</label>
                        <input type="text" name="color" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.unit') }}</label>
                        <input type="text" name="unit" class="form-control" value="KGS" required></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
            </div>
        </form>
    </div>
</div>

{{-- Modal Import --}}
<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.yarns.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.import_yarn_master') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fa-solid fa-info-circle me-1"></i> Gunakan susunan kolom template.
                        <a href="{{ route('mfg.yarns.download-template') }}" class="fw-bold">{{ __('erp.download_template') }}</a>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('erp.choose_file_xlsx') }}</label>
                        <input type="file" name="file_excel" class="form-control" required accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="form-text">{{ __('erp.stock_cogs_not_imported_hint') }}</div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary fw-bold">{{ __('erp.start_import') }}</button></div>
            </div>
        </form>
    </div>
</div>
@endsection
