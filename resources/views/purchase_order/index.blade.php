@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Pembelian' => '#', 'Purchase Orders' => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">Purchase Orders (Pembelian)</h3>
            <p class="text-muted small mb-0">Daftar pesanan pembelian dari Supplier.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            
            <button type="button" class="btn btn-info text-white fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" data-bs-toggle="modal" data-bs-target="#importPO">
                <i class="fa-solid fa-file-import me-1"></i> Import CSV
            </button>

            <a href="{{ route('po.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Manual
            </a>
            
            <form action="{{ route('po.sync_temp') }}" method="POST" class="d-inline">
                @csrf
                <button type="submit" class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" onclick="return confirm('Mulai sinkronisasi PO dari Dashboard? Proses ini berjalan di background.')">
                    <i class="fa-solid fa-sync me-1"></i> Sync Dashboard
                </button>
            </form>
        </div>
    </div>

    @if(session('success')) 
        <div class="alert alert-success fw-bold shadow-sm"><i class="fa-solid fa-circle-check me-2"></i>{{ session('success') }}</div> 
    @endif
    
    @if(session('error')) 
        <div class="alert alert-danger fw-bold shadow-sm"><i class="fa-solid fa-triangle-exclamation me-2"></i>{{ session('error') }}</div> 
    @endif

    <div class="modal fade" id="importPO" tabindex="-1">
        <div class="modal-dialog">
            <form action="{{ route('po.import') }}" method="POST" enctype="multipart/form-data" class="modal-content">
                @csrf
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold">Import CSV Pembelian</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body bg-light">
                    <div class="alert alert-info py-2 small mb-3 border-info">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fa-solid fa-info-circle me-1"></i> Gunakan format CSV standar Jubelio.</span>
                            <a href="{{ route('po.template') }}" class="btn btn-sm btn-light border-primary text-primary fw-bold shadow-sm">
                                <i class="fa-solid fa-download me-1"></i> Download Template
                            </a>
                        </div>
                    </div>

                    <label class="fw-bold">File CSV Purchase Orders Details</label>
                    <input type="file" name="file_csv" accept=".csv" class="form-control mt-2" required>
                </div>
                <div class="modal-footer">
                    <button type="submit" class="btn btn-primary fw-bold w-100">Upload & Sinkronisasi</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> Filter Analitik Pencarian</div>
        <form action="{{ route('po.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Mulai Tgl</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Sampai Tgl</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date') }}">
            </div>
            <div class="col-12 col-sm-6 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Status / Kategori</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Semua Status</option>
                    <option value="APPROVED" {{ request('status') == 'APPROVED' ? 'selected' : '' }}>APPROVED (Pending)</option>
                    <option value="PARTIAL" {{ request('status') == 'PARTIAL' ? 'selected' : '' }}>PARTIAL (Sebagian)</option>
                    <option value="RECEIVED" {{ request('status') == 'RECEIVED' ? 'selected' : '' }}>RECEIVED (Komplit)</option>
                </select>
            </div>
            <div class="col-12 col-sm-12 col-md-3">
                <label class="form-label small fw-bold text-muted mb-1">Pencarian Nomor / Keterangan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Ketik kata kunci..." value="{{ request('search') }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('po.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <div class="card border-0 shadow-sm rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                <thead class="table-dark text-uppercase">
                    <tr>
                        <th class="ps-4 py-3">Tgl PO</th>
                        <th class="py-3">No. Pembelian</th>
                        <th class="py-3">Supplier / Vendor</th>
                        <th class="text-center py-3">Total Item</th>
                        <th class="text-end py-3">Grand Total (Rp)</th>
                        <th class="text-center py-3">Status</th>
                        <th class="text-end pe-4 py-3">Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($orders as $o)
                    @php
                        // FIX N+1: cek via array yang sudah di-preload di Controller
                        $noTransaksiPP = str_replace('PO-', '', $o->po_number);
                        $isUangMuka = isset($uangMukaSet[$noTransaksiPP]);
                        $kreditAkun = $isUangMuka ? 'Uang Muka Pembelian (11305)' : 'Hutang Dagang (22000)';
                    @endphp
                    <tr>
                        <td class="ps-4 fw-medium">{{ date('d M Y', strtotime($o->transaction_date)) }}</td>
                        <td class="fw-bold text-primary">
                            {{ $o->po_number }}
                            @if($isUangMuka)
                                <i class="fa-solid fa-money-check-dollar text-success ms-1" title="Terkoneksi Payment Plan"></i>
                            @endif
                        </td>
                        <td class="fw-bold text-dark">
                            {{ $o->contact_name }} <br>
                            <span class="badge bg-info text-dark mt-1">{{ $o->location_name }}</span>
                        </td>
                        <td class="text-center fw-bold">{{ $o->details->count() }} Jenis</td>
                        <td class="text-end fw-bold text-success">Rp {{ number_format($o->grand_total, 0, ',', '.') }}</td>
                        <td class="text-center">
                            @if($o->status == 'RECEIVED')
                                <span class="badge bg-success px-2 py-1">RECEIVED</span>
                            @elseif($o->status == 'PARTIAL')
                                <span class="badge bg-info text-dark px-2 py-1">PARTIAL</span>
                            @else
                                <span class="badge bg-warning text-dark px-2 py-1">APPROVED</span>
                            @endif
                        </td>
                        <td class="text-end pe-4">
                            <div class="d-flex justify-content-end align-items-center gap-1">
                                @if($o->status != 'RECEIVED')
                                    <button type="button" class="btn btn-sm btn-success fw-bold shadow-sm me-2" data-bs-toggle="modal" data-bs-target="#receiveModal{{ $o->id }}">
                                        <i class="fa-solid fa-file-invoice-dollar me-1"></i> Terima / Tagihan
                                    </button>
                                @endif

                                <button type="button" onclick="showEntityLog('{{ $o->po_number }}')" class="btn btn-sm btn-outline-info shadow-sm" title="Jejak Log Aktivitas"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                
                                <a href="{{ route('po.edit', $o->id) }}" class="btn btn-sm btn-outline-primary shadow-sm" title="Edit PO"><i class="fa-solid fa-pen"></i></a>
                                <form action="{{ route('po.destroy', $o->id) }}" method="POST" class="m-0 d-inline" onsubmit="return confirm('Batalkan dan Hapus PO ini secara permanen?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger shadow-sm" title="Hapus PO"><i class="fa-solid fa-trash-can"></i></button>
                                </form>
                            </div>

                            @if($o->status != 'RECEIVED')
                            <div class="modal fade text-start" id="receiveModal{{ $o->id }}" tabindex="-1">
                                <div class="modal-dialog modal-xl modal-dialog-centered">
                                    <form action="{{ route('po.receive', $o->id) }}" method="POST" class="modal-content border-0 shadow-lg">
                                        @csrf
                                        <div class="modal-header bg-success text-white py-3">
                                            <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2"></i> Buat Tagihan & Terima Barang</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body bg-light p-4">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <small class="text-muted fw-bold text-uppercase d-block">Nomor Pembelian</small>
                                                    <span class="fs-6 fw-bold text-primary">{{ $o->po_number }}</span>
                                                </div>
                                                <div class="col-md-3">
                                                    <small class="text-muted fw-bold text-uppercase d-block">No. Tagihan Supplier *</small>
                                                    <input type="text" name="bill_number" class="form-control form-control-sm fw-bold" placeholder="Misal: INV-SUP-01" required>
                                                </div>
                                                <div class="col-md-3 text-md-end">
                                                    <small class="text-muted fw-bold text-uppercase d-block">Tgl Tagihan & Jatuh Tempo</small>
                                                    <div class="d-flex gap-1 justify-content-end">
                                                        <input type="date" name="receive_date" class="form-control form-control-sm fw-bold w-auto" value="{{ date('Y-m-d') }}" title="Tanggal Tagihan" required>
                                                        <input type="date" name="due_date" class="form-control form-control-sm fw-bold w-auto" title="Jatuh Tempo (Opsional)">
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <div class="alert alert-info py-2 small mb-3 border-info">
                                                <i class="fa-solid fa-info-circle me-1"></i> Sistem mendeteksi ini sebagai transaksi <strong>{{ $isUangMuka ? 'UANG MUKA' : 'HUTANG DAGANG' }}</strong>. Jurnal akan otomatis dibuat:<br>
                                                <span class="text-success fw-bold">DEBET:</span> Persediaan Barang (11200)<br>
                                                <span class="text-danger fw-bold">KREDIT:</span> {{ $kreditAkun }}
                                            </div>

                                            <div class="alert alert-warning py-2 small mb-3 border-warning text-dark">
                                                <i class="fa-solid fa-triangle-exclamation text-warning me-1"></i> <strong>PENTING:</strong> Isi kolom <b>Terima Skrg</b> sesuai dengan barang fisik yang lolos QC/kondisi baik. Barang reject/cacat jangan dimasukkan agar Jurnal Aset tetap akurat. Jika ada barang datang lebih dari pesanan (Over-Receipt), Anda bisa mengisi melebihi angka pesanan.
                                            </div>

                                            <div class="table-responsive bg-white border rounded">
                                                <table class="table table-sm align-middle mb-0" style="font-size: 0.85rem;">
                                                    <thead class="table-light text-muted text-uppercase" style="font-size: 0.75rem;">
                                                        <tr>
                                                            <th class="ps-3 py-2">Item Code / SKU</th>
                                                            <th>Deskripsi Barang</th>
                                                            <th class="text-end">Hrg Satuan (Rp)</th>
                                                            <th class="text-center">Total Pesan</th>
                                                            <th class="text-center">Sisa Blm Datang</th>
                                                            <th class="text-center pe-3" width="15%">Terima Skrg (Aktual)</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        @foreach($o->details as $det)
                                                            @php $sisaQty = $det->qty - $det->qty_received; @endphp
                                                            <tr class="{{ $sisaQty <= 0 ? 'bg-light text-muted' : '' }}">
                                                                <td class="ps-3 fw-bold">{{ $det->item_code }}</td>
                                                                <td class="text-wrap" style="min-width: 200px;">{{ $det->description }}</td>
                                                                <td class="text-end font-monospace">{{ number_format($det->price, 0, ',', '.') }}</td>
                                                                <td class="text-center fw-bold">{{ $det->qty }}</td>
                                                                <td class="text-center fw-bold {{ $sisaQty > 0 ? 'text-danger' : 'text-success' }}">{{ $sisaQty }}</td>
                                                                <td class="pe-3 py-2">
                                                                    @if($sisaQty > 0)
                                                                        <input type="number" name="items[{{ $det->id }}]" class="form-control form-control-sm text-center fw-bold border-success text-success" value="{{ $sisaQty }}" min="0">
                                                                    @else
                                                                        <span class="badge bg-success w-100 py-2"><i class="fa-solid fa-check"></i> KOMPLIT</span>
                                                                    @endif
                                                                </td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-white py-3">
                                            <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Batal</button>
                                            <button type="submit" class="btn btn-success fw-bold px-4 shadow-sm">
                                                <i class="fa-solid fa-boxes-packing me-1"></i> Simpan & Generate Jurnal Stok
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            @endif

                        </td>
                    </tr>
                    @empty
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">Belum ada transaksi pembelian. Silakan import CSV atau Buat Manual.</td>
                    </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div class="card-footer bg-white">{{ $orders->links() }}</div>
    </div>
</div>
@endsection