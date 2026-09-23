@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.mfg_module') => '#', __('erp.bc_master_service_supplier') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.mfg_service_supplier_master') }}</h3>
            <p class="text-muted small mb-0">{{ __('erp.mfg_supplier_types_hint') }}</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="{{ route('mfg.suppliers.index', ['export' => 'excel']) }}" class="btn btn-success fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export
            </a>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalImport">
                <i class="fa-solid fa-file-import me-1"></i> Import
            </button>
            <button type="button" class="btn btn-primary fw-bold px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCreate">
                <i class="fa-solid fa-plus me-1"></i> {{ __('erp.add_supplier_btn') }}
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

    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2">
                <div class="col-md-4">
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="{{ __('erp.search_supplier') }}" value="{{ $search ?? '' }}">
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
                            <th>{{ __('erp.code_label') }}</th><th>{{ __('erp.name_label') }}</th><th>{{ __('erp.type_label') }}</th><th>{{ __('erp.helper_code_ap') }}</th>
                            <th>{{ __('erp.contact_label') }}</th><th>{{ __('erp.phone_label') }}</th><th>{{ __('erp.status') }}</th><th>{{ __('erp.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($suppliers as $supplier)
                            <tr>
                                <td class="fw-bold py-2">{{ $supplier->supplier_code }}</td>
                                <td class="py-2">{{ $supplier->supplier_name }}</td>
                                <td class="text-center py-2"><span class="badge bg-info text-dark">{{ $supplier->supplier_type }}</span></td>
                                <td class="py-2">{{ $supplier->helper_code ?? '-' }}</td>
                                <td class="py-2">{{ $supplier->contact_person ?? '-' }}</td>
                                <td class="py-2">{{ $supplier->phone ?? '-' }}</td>
                                <td class="text-center py-2">
                                    <span class="badge {{ $supplier->is_active ? 'bg-success' : 'bg-secondary' }}">{{ $supplier->is_active ? 'AKTIF' : 'NONAKTIF' }}</span>
                                </td>
                                <td class="text-center py-2">
                                    <div class="btn-group">
                                        <button type="button" class="btn btn-sm btn-outline-warning" data-bs-toggle="modal"
                                            data-bs-target="#modalEdit{{ $supplier->id }}"><i class="fas fa-edit"></i></button>
                                        <form action="{{ route('mfg.suppliers.destroy', $supplier->id) }}" method="POST" class="d-inline"
                                            onsubmit="return confirm(__('erp.confirm_delete_supplier'))">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>

                            <div class="modal fade" id="modalEdit{{ $supplier->id }}" tabindex="-1">
                                <div class="modal-dialog">
                                    <form action="{{ route('mfg.suppliers.update', $supplier->id) }}" method="POST">
                                        @csrf @method('PUT')
                                        <div class="modal-content">
                                            <div class="modal-header"><h5 class="modal-title">{{ __('erp.edit_supplier') }}</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                                            <div class="modal-body">
                                                <div class="mb-2"><label class="form-label">{{ __('erp.supplier_code') }}</label>
                                                    <input type="text" name="supplier_code" class="form-control" value="{{ $supplier->supplier_code }}" required></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.supplier_name') }}</label>
                                                    <input type="text" name="supplier_name" class="form-control" value="{{ $supplier->supplier_name }}" required></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.type_label') }}</label>
                                                    <select name="supplier_type" class="form-select" required>
                                                        @foreach(['RAW_MATERIAL','KNITTER','PROCESSOR','CUTTING','STITCHER','FINISHING','OTHER'] as $type)
                                                            <option value="{{ $type }}" {{ $supplier->supplier_type === $type ? 'selected' : '' }}>{{ $type }}</option>
                                                        @endforeach
                                                    </select></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.helper_code_subledger_ap') }}</label>
                                                    <select name="helper_code" class="form-select">
                                                        <option value="">{{ __('erp.none_option') }}</option>
                                                        @foreach($helperCodes as $hc)
                                                            <option value="{{ $hc->helper_code }}" {{ $supplier->helper_code === $hc->helper_code ? 'selected' : '' }}>{{ $hc->helper_code }} - {{ $hc->entity_name }}</option>
                                                        @endforeach
                                                    </select></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.contact_person') }}</label>
                                                    <input type="text" name="contact_person" class="form-control" value="{{ $supplier->contact_person }}"></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.phone_label') }}</label>
                                                    <input type="text" name="phone" class="form-control" value="{{ $supplier->phone }}"></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.email_label') }}</label>
                                                    <input type="email" name="email" class="form-control" value="{{ $supplier->email }}"></div>
                                                <div class="mb-2"><label class="form-label">{{ __('erp.address_label') }}</label>
                                                    <textarea name="address" class="form-control" rows="2">{{ $supplier->address }}</textarea></div>
                                                <div class="form-check">
                                                    <input type="checkbox" name="is_active" class="form-check-input" value="1" {{ $supplier->is_active ? 'checked' : '' }}>
                                                    <label class="form-check-label">{{ __('erp.active_label') }}</label>
                                                </div>
                                            </div>
                                            <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        @empty
                            <tr><td colspan="8" class="text-center py-5 text-muted">{{ __('erp.no_supplier_data') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    {{ $suppliers->links() }}
</div>

<div class="modal fade" id="modalCreate" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.suppliers.store') }}" method="POST">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.add_supplier') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="mb-2"><label class="form-label">{{ __('erp.supplier_code') }}</label>
                        <input type="text" name="supplier_code" class="form-control" required placeholder="SUP-KNIT-01"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.supplier_name') }}</label>
                        <input type="text" name="supplier_name" class="form-control" required></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.type_label') }}</label>
                        <select name="supplier_type" class="form-select" required>
                            <option value="RAW_MATERIAL">{{ __('erp.supplier_type_raw_material') }}</option>
                            <option value="KNITTER">{{ __('erp.supplier_type_knitter') }}</option>
                            <option value="PROCESSOR">{{ __('erp.supplier_type_processor') }}</option>
                            <option value="CUTTING">{{ __('erp.process_cutting') }}</option>
                            <option value="STITCHER">{{ __('erp.supplier_type_stitcher') }}</option>
                            <option value="FINISHING">{{ __('erp.process_finishing') }}</option>
                            <option value="OTHER">{{ __('erp.other_caps') }}</option>
                        </select></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.helper_code_subledger_ap') }}</label>
                        <select name="helper_code" class="form-select">
                            <option value="">{{ __('erp.none_option') }}</option>
                            @foreach($helperCodes as $hc)
                                <option value="{{ $hc->helper_code }}">{{ $hc->helper_code }} - {{ $hc->entity_name }}</option>
                            @endforeach
                        </select></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.contact_person') }}</label>
                        <input type="text" name="contact_person" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.phone_label') }}</label>
                        <input type="text" name="phone" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.email_label') }}</label>
                        <input type="email" name="email" class="form-control"></div>
                    <div class="mb-2"><label class="form-label">{{ __('erp.address_label') }}</label>
                        <textarea name="address" class="form-control" rows="2"></textarea></div>
                </div>
                <div class="modal-footer"><button type="submit" class="btn btn-primary">{{ __('erp.save_btn') }}</button></div>
            </div>
        </form>
    </div>
</div>

<div class="modal fade" id="modalImport" tabindex="-1">
    <div class="modal-dialog">
        <form action="{{ route('mfg.suppliers.import') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="modal-content">
                <div class="modal-header"><h5 class="modal-title">{{ __('erp.import_supplier_master') }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
                <div class="modal-body">
                    <div class="alert alert-info small">
                        <i class="fa-solid fa-info-circle me-1"></i> Gunakan susunan kolom template.
                        <a href="{{ route('mfg.suppliers.download-template') }}" class="fw-bold">{{ __('erp.download_template') }}</a>
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
