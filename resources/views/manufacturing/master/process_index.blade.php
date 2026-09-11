@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', __('erp.mfg_master_process') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.process_rate_master_vendor') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.process_rate_desc') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('mfg.processes.index', ['export' => 'excel']) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fa-solid fa-file-import me-1"></i> Import
            </button>
            <button type="button" class="btn btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="fa-solid fa-plus me-1"></i> {{ __('erp.add_process_btn') }}
            </button>
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

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13.5px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        <tr>
                            <th>{{ __('erp.mfg_process_name') }}</th><th>{{ __('erp.type_label') }}</th><th>{{ __('erp.mfg_rate_unit') }}</th><th>{{ __('erp.rate_label') }}</th>
                            <th>{{ __('erp.default_vendor') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($processes as $process)
                            <tr>
                                <td class="fw-bold py-2">{{ $process->process_name }}</td>
                                <td class="text-center py-2"><span class="badge bg-info text-dark">{{ $process->process_type }}</span></td>
                                <td class="text-center py-2">{{ $process->rate_unit }}</td>
                                <td class="text-end py-2">Rp {{ number_format($process->process_rate, 2) }}</td>
                                <td class="py-2">{{ $process->defaultSupplier->supplier_name ?? '-' }}</td>
                                <td class="text-center py-2">
                                    <span class="badge {{ $process->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $process->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="text-center py-2">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal"
                                            data-bs-target="#modalEdit{{ $process->id }}"><i class="fas fa-edit"></i></button>
                                        <form action="{{ route('mfg.processes.destroy', $process->id) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm(__('erp.confirm_delete_process'))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="modalEdit{{ $process->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <form action="{{ route('mfg.processes.update', $process->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">{{ __('erp.edit_process_rate') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <div class="mb-2"><label class="form-label">{{ __('erp.mfg_process_type') }}</label>
                                                    <select name="process_type" class="form-select" required>
                                                        @foreach(['KNITTING','DYEING','PRINTING','FINISHING','CUTTING','STITCHING','OTHER'] as $type)
                                                            <option value="{{ $type }}" {{ $process->process_type === $type ? 'selected' : '' }}>{{ $type }}</option>
                                                        @endforeach
                                                    </select></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.mfg_process_name') }}</label>
                                                    <input type="text" name="process_name" class="form-control" value="{{ $process->process_name }}" required></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.mfg_rate_unit') }}</label>
                                                    <select name="rate_unit" class="form-select" required>
                                                        <option value="PER_KG" {{ $process->rate_unit === 'PER_KG' ? 'selected' : '' }}>{{ __('erp.per_kg') }}</option>
                                                        <option value="PER_PCS" {{ $process->rate_unit === 'PER_PCS' ? 'selected' : '' }}>{{ __('erp.per_pcs') }}</option>
                                                    </select></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.rate_rp') }}</label>
                                                    <input type="number" step="0.01" name="process_rate" class="form-control" value="{{ $process->process_rate }}" required></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.default_vendor') }}</label>
                                                    <select name="default_supplier_id" class="form-select">
                                                        <option value="">{{ __('erp.none_option') }}</option>
                                                        @foreach($suppliers as $s)
                                                            <option value="{{ $s->id }}" {{ $process->default_supplier_id == $s->id ? 'selected' : '' }}>{{ $s->supplier_name }}</option>
                                                        @endforeach
                                                    </select></div>
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $process->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label">{{ __('erp.active_label') }}</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="7" class="text-center py-5 text-muted">{{ __('erp.no_process_rate_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $processes->links() }}
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.processes.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.add_process_rate') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">{{ __('erp.mfg_process_type') }}</label>
                        <select name="process_type" class="form-select" required>
                            <option value="KNITTING">{{ __('erp.process_knitting') }}</option>
                            <option value="DYEING">{{ __('erp.process_dyeing') }}</option>
                            <option value="PRINTING">{{ __('erp.process_printing') }}</option>
                            <option value="FINISHING">{{ __('erp.process_finishing') }}</option>
                            <option value="CUTTING">{{ __('erp.process_cutting') }}</option>
                            <option value="STITCHING">{{ __('erp.process_stitching') }}</option>
                            <option value="OTHER">{{ __('erp.other_caps') }}</option>
                        </select></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.mfg_process_name') }}</label>
                        <input type="text" name="process_name" class="form-control" required placeholder="Knitting Single Jersey"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.mfg_rate_unit') }}</label>
                        <select name="rate_unit" class="form-select" required>
                            <option value="PER_KG">{{ __('erp.per_kg') }}</option>
                            <option value="PER_PCS">{{ __('erp.per_pcs') }}</option>
                        </select></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.rate_rp') }}</label>
                        <input type="number" step="0.01" name="process_rate" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.default_vendor') }}</label>
                        <select name="default_supplier_id" class="form-select">
                            <option value="">{{ __('erp.none_option') }}</option>
                            @foreach($suppliers as $s)
                                <option value="{{ $s->id }}">{{ $s->supplier_name }}</option>
                            @endforeach
                        </select></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.processes.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.import_process_rate_master') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fa-solid fa-info-circle me-1"></i> Gunakan susunan kolom template.
                        <a href="{{ route('mfg.processes.download-template') }}" class="fw-bold">{{ __('erp.download_template') }}</a>
                    </div>
                    <div class="mb-2">
                        <label class="form-label">{{ __('erp.choose_file_xlsx') }}</label>
                        <input type="file" name="file_excel" class="form-control" required accept=".xlsx,.xls,.csv">
                    </div>
                    <div class="form-text">{{ __('erp.data_always_new') }}</div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary fw-bold">{{ __('erp.start_import') }}</button></div>
            </div>
        </form>
    </div>
</div>
@endsection
