@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.accounting') => '#', __('erp.general_journal') => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ __('erp.general_journal') }}</h3>
            <p class="text-muted small mb-0">Pencatatan mutasi transaksi harian dengan kaidah pembukuan berpasangan (Double-Entry).</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('jurnal.export', [
                'start_date' => request('start_date'),
                'end_date' => request('end_date'),
                'search' => request('search')
            ]) }}" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </a>
            <button type="button" class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0 text-dark" id="btnSyncTemp">
                <i class="fa-solid fa-rotate me-2"></i> Sync ke Temp
            </button>
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importModal">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>
            <a href="{{ route('jurnal.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
        </div>
    </div>

    <div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('jurnal.import') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="importModalLabel">{{ __('erp.import_general_journal') }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        
                        <div class="alert alert-info py-3 small mb-4" style="background-color: #e0f2fe; color: #0369a1; border-color: #bae6fd; border-radius: 8px;">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h6 class="fw-bold mb-0"><i class="fa-solid fa-circle-info"></i> {{ __('erp.csv_format_jubelio_9col') }}</h6>
                                <a href="{{ route('jurnal.download-template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm" style="font-size: 0.75rem;">
                                    <i class="fa-solid fa-download me-1"></i> Download Template
                                </a>
                            </div>
                            Kolom 1: Tanggal<br>
                            Kolom 2: No. Jurnal (GJ-xxxx)<br>
                            Kolom 3: No. Bukti (INV-xxxx / Lainnya)<br>
                            Kolom 4: Deskripsi / Tipe<br>
                            Kolom 5: Total Debet <em class="text-muted">{{ __('erp.will_be_ignored') }}</em><br>
                            Kolom 6: Total Kredit <em class="text-muted">{{ __('erp.will_be_ignored') }}</em><br>
                            Kolom 7: Nilai Debet (Nominal bersih)<br>
                            Kolom 8: Nilai Kredit (Nominal bersih)<br>
                            Kolom 9: Akun (Contoh: 5-5000 - Harga Pokok Penjualan)<br>
                            <div class="mt-3 text-danger fw-bold border-top border-info pt-2">
                                *Baris pertama di file CSV wajib berupa Header/Judul kolom bawaan dari Jubelio.
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="file_excel" class="form-label fw-bold">{{ __('erp.choose_file_csv_excel') }}</label>
                            <input type="file" class="form-control" name="file_excel" id="file_excel" accept=".csv, .xls, .xlsx" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary fw-bold" data-bs-dismiss="modal">{{ __('erp.cancel') }}</button>
                        <button type="submit" class="btn btn-primary fw-bold">
                            <i class="fa-solid fa-upload me-1"></i> Proses Import
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- AREA NOTIFIKASI ERROR VALIDASI (WAJIB ADA) -->
    @if($errors->any())
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> 
            @foreach($errors->all() as $error)
                {{ $error }}<br>
            @endforeach
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('info'))
        <div class="alert alert-info alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-circle-info me-2"></i> {{ session('info') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show shadow-sm fw-bold" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> {{ __('erp.filter_analytics_search') }}</div>
        <form action="{{ route('jurnal.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.start_date_short') }}</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.end_date_short') }}</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.row_label') }}</label>
                <select name="per_page" class="form-select form-select-sm">
                    <option value="25" {{ request('per_page') == 25 ? 'selected' : '' }}>25</option>
                    <option value="50" {{ request('per_page', 50) == 50 ? 'selected' : '' }}>50</option>
                    <option value="100" {{ request('per_page') == 100 ? 'selected' : '' }}>100</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-4">
                <label class="form-label small fw-bold text-muted mb-1">{{ __('erp.search_number_desc') }}</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari No. Bukti / Keterangan..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> {{ __('erp.search_btn') }}</button>
                <a href="{{ route('jurnal.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <form action="{{ route('jurnal.massDestroy') }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus permanen transaksi yang dipilih?');">
        @csrf @method('DELETE')

        <div class="mb-3">
            <button type="submit" class="btn btn-sm btn-danger fw-bold px-3 shadow-sm">
                <i class="fa-solid fa-trash-can me-2"></i> Hapus Massal Terpilih
            </button>
        </div>

        <div class="card border-0 shadow-sm" style="border-radius: 12px;">
            <div class="table-responsive bg-white">
                <table class="table align-middle mb-0" style="font-size: 0.85rem;">
                    <thead class="table-light text-uppercase text-muted" style="font-size: 0.75rem;">
                        <tr>
                            <th width="4%" class="text-center ps-2">
                                <input type="checkbox" class="form-check-input" onclick="document.querySelectorAll('.journal-checkbox').forEach(cb => cb.checked = this.checked)">
                            </th>
                            <th width="11%" class="ps-2">{{ __('erp.date') }}</th>
                            <th width="14%">{{ __('erp.evidence_no') }}</th>
                            <th width="23%">{{ __('erp.description') }}</th>
                            <th width="24%">{{ __('erp.account_name_ref') }}</th>
                            <th width="11%" class="text-end">{{ __('erp.debit_rp') }}</th>
                            <th width="11%" class="text-end pe-3">{{ __('erp.credit_rp') }}</th>
                            <th width="8%" class="text-center pe-4">{{ __('erp.action') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if(isset($journals))
                            @forelse($journals as $header)
                                @php 
                                    $detailsCount = $header->details->count(); 
                                    $primaryId = $header->journal_id ?? $header->id;
                                    
                                    // Hitung Total Balance
                                    $totDebet = $header->details->where('position', 'DEBET')->sum('amount');
                                    $totKredit = $header->details->where('position', 'KREDIT')->sum('amount');
                                @endphp
                                
                                @if($detailsCount == 0)
                                    <tr style="background-color: #fff5f5;">
                                        <td class="text-center align-middle ps-2">
                                            <input type="checkbox" name="ids[]" value="{{ $primaryId }}" class="form-check-input journal-checkbox">
                                        </td>
                                        <td class="ps-2 align-middle fw-medium">{{ date('d M Y', strtotime($header->transaction_date)) }}</td>
                                        <td class="align-middle">
                                            <a href="{{ route('trace.document', $header->evidence_number) }}" 
                                               class="text-primary text-decoration-none fw-bold" 
                                               title="Klik untuk menelusuri dokumen asal">
                                               <i class="fa-solid fa-link fa-sm me-1"></i> {{ $header->evidence_number }}
                                            </a>
                                        </td>
                                                <td class="align-middle fw-medium">@linkify($header->description)</td>
                                                <td colspan="3" class="text-center text-danger fw-bold align-middle">
                                                    <i class="fa-solid fa-triangle-exclamation"></i> Rincian Kosong / Relasi Terputus
                                                </td>
                                                <td class="text-center align-middle pe-4">
                                                    <div class="btn-group">
                                                        <button type="button" onclick="showEntityLog('{{ $header->evidence_number }}')" class="btn btn-sm btn-outline-info shadow-sm" title="Jejak Log Aktivitas"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                                        <button type="button" onclick="if(confirm('Hapus jurnal error ini?')) document.getElementById('delete-form-{{ $primaryId }}').submit();" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-trash-can"></i></button>
                                                    </div>
                                                </td>
                                            </tr>
                                            <tr style="border-bottom: 2px solid #cbd5e1;"><td colspan="8" class="p-0"></td></tr>
                                @else
                                    @foreach($header->details as $index => $det)
                                        <tr>
                                            @if($index == 0)
                                                <td rowspan="{{ $detailsCount + 1 }}" class="text-center align-top pt-3 ps-2" style="background-color: #f8fafc;">
                                                    <input type="checkbox" name="ids[]" value="{{ $primaryId }}" class="form-check-input journal-checkbox">
                                                </td>
                                                <td rowspan="{{ $detailsCount + 1 }}" class="ps-2 align-top pt-3 fw-medium" style="background-color: #f8fafc;">{{ date('d M Y', strtotime($header->transaction_date)) }}</td>
                                                <td rowspan="{{ $detailsCount + 1 }}" class="align-top pt-3" style="background-color: #f8fafc;">
                                                    <a href="{{ route('trace.document', $header->evidence_number) }}" 
                                                       class="text-primary text-decoration-none fw-bold" 
                                                       title="Klik untuk menelusuri dokumen asal">
                                                       <i class="fa-solid fa-link fa-sm me-1"></i> {{ $header->evidence_number }}
                                                    </a>
                                                    @if($header->source_doc_no)
                                                        <br><small class="text-success fw-bold">
                                                            <a href="{{ route('trace.document', $header->source_doc_no) }}" 
                                                               class="text-success text-decoration-none" 
                                                               title="Klik untuk menelusuri dokumen operasional asli">
                                                               <i class="fa-solid fa-file-invoice fa-sm me-1"></i> {{ $header->source_doc_no }}
                                                            </a>
                                                        </small>
                                                    @endif
                                                </td>
                                                <td rowspan="{{ $detailsCount + 1 }}" class="align-top pt-3 fw-bold text-dark" style="background-color: #f8fafc;">@linkify($header->description)</td>
                                            @endif
                                            <td>
                                                <div class="{{ $det->position == 'KREDIT' ? 'ms-4' : '' }}">
                                                    <span class="{{ $det->position == 'KREDIT' ? 'text-muted' : 'fw-bold text-dark' }}">{{ $det->account ? $det->account->account_name : 'AKUN TIDAK ADA' }}</span>
                                                    <span class="text-muted small ms-1 font-monospace">[{{ $det->account_code }}]</span>
                                                </div>
                                            </td>
                                            <td class="text-end fw-bold {{ $det->position == 'DEBET' ? 'text-success' : 'text-muted' }}">{{ $det->position == 'DEBET' ? number_format($det->amount, 2, ',', '.') : '-' }}</td>
                                            <td class="text-end pe-3 fw-bold {{ $det->position == 'KREDIT' ? 'text-danger' : 'text-muted' }}">{{ $det->position == 'KREDIT' ? number_format($det->amount, 2, ',', '.') : '-' }}</td>
                                            
                                            @if($index == 0)
                                                <td rowspan="{{ $detailsCount + 1 }}" class="text-center align-top pe-4 pt-3" style="background-color: #f8fafc;">
                                                    <div class="btn-group">
                                                        <button type="button" onclick="showEntityLog('{{ $header->evidence_number }}')" class="btn btn-sm btn-outline-info shadow-sm" title="Jejak Log Aktivitas"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                                        <a href="{{ route('jurnal.edit', $primaryId) }}" class="btn btn-sm btn-outline-primary shadow-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                                                        <button type="button" onclick="if(confirm('Hapus jurnal ini?')) document.getElementById('delete-form-{{ $primaryId }}').submit();" class="btn btn-sm btn-outline-danger shadow-sm"><i class="fa-solid fa-trash-can"></i></button>
                                                    </div>
                                                </td>
                                            @endif
                                        </tr>
                                    @endforeach
                                    
                                    <tr class="bg-light border-bottom border-secondary">
                                        <td class="text-end fw-bold text-muted small text-uppercase">{{ __('erp.total_colon') }}</td>
                                        <td class="text-end fw-bold text-success">Rp {{ number_format($totDebet, 2, ',', '.') }}</td>
                                        <td class="text-end pe-3 fw-bold text-danger">Rp {{ number_format($totKredit, 2, ',', '.') }}</td>
                                    </tr>
                                    
                                    <tr style="border-bottom: 2px solid #cbd5e1;"><td colspan="8" class="p-0"></td></tr>
                                @endif
                            @empty
                                <tr><td colspan="8" class="text-center py-5 text-muted">{{ __('erp.no_data_found') }}</td></tr>
                            @endforelse
                        @endif
                    </tbody>
                </table>
            </div>
            @if(isset($journals))
            <div class="card-footer bg-white border-top p-3 d-flex justify-content-end">{{ $journals->links() }}</div>
            @endif
        </div>
    </form>

    @if(isset($journals))
        @foreach($journals as $header)
            <form id="delete-form-{{ $header->journal_id ?? $header->id }}" action="{{ route('jurnal.destroy', $header->journal_id ?? $header->id) }}" method="POST" style="display: none;">
                @csrf @method('DELETE')
            </form>
        @endforeach
    @endif
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function() {
        $('#btnSyncTemp').click(function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fa-solid fa-spinner fa-spin me-2"></i> Menyinkronkan...');
            
            $.ajax({
                url: "{{ route('jurnal.sync_temp') }}",
                type: 'POST',
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Sinkronisasi Dimulai',
                        text: 'Sinkronisasi sedang berjalan di latar belakang server. Anda bisa menutup halaman ini atau lanjut bekerja.',
                        confirmButtonColor: '#3085d6'
                    });
                    btn.prop('disabled', false).html('<i class="fa-solid fa-rotate me-2"></i> Sync ke Temp (Background)');
                },
                error: function() {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Terjadi kesalahan saat memulai sinkronisasi.',
                        confirmButtonColor: '#d33'
                    });
                    btn.prop('disabled', false).html('<i class="fa-solid fa-rotate me-2"></i> Sync ke Temp (Background)');
                }
            });
        });
    });
</script>
@endsection
