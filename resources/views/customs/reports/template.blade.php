@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="[__('erp.bc_cust') => route('customs.index'), $title => null]" />
@endsection

@section('content')
<div class="container-fluid px-0">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-dark">{{ $title }}</h3>
            <p class="text-muted small mb-0">Laporan Pertanggungjawaban Mutasi & IT Inventory Kawasan Berikat / TPB (Bea Cukai)</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" class="btn btn-outline-success fw-bold px-3 shadow-sm" onclick="alert('Export Excel siap dikonfigurasi.')">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <button type="button" class="btn btn-outline-secondary fw-bold px-3 shadow-sm" onclick="window.print()">
                <i class="fa-solid fa-print me-1"></i> Cetak
            </button>
        </div>
    </div>

    {{-- Filter Periode & Dokumen --}}
    <div class="card shadow-sm border-0 mb-3">
        <div class="card-body py-2">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-3">
                    <label class="small text-muted mb-1">Dari Tanggal</label>
                    <input type="date" name="date_from" class="form-control form-control-sm" value="{{ request('date_from', date('Y-m-01')) }}">
                </div>
                <div class="col-md-3">
                    <label class="small text-muted mb-1">Sampai Tanggal</label>
                    <input type="date" name="date_to" class="form-control form-control-sm" value="{{ request('date_to', date('Y-m-d')) }}">
                </div>
                <div class="col-md-4">
                    <label class="small text-muted mb-1">Pencarian Barang / Dokumen</label>
                    <input type="text" name="search" class="form-control form-control-sm" placeholder="Kode / Nama Barang / No. Pendaftaran..." value="{{ request('search') }}">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-sm btn-primary w-100">
                        <i class="fa-solid fa-filter me-1"></i> Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- Data Table Placeholder --}}
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered table-striped table-hover align-middle mb-0 text-nowrap" style="font-size: 13px;">
                    <thead class="bg-primary text-white text-center align-middle">
                        @if($reportType === 'inbound')
                            <tr>
                                <th>No</th>
                                <th>Jenis Dokumen</th>
                                <th>Nomor Pendaftaran</th>
                                <th>Tanggal Pendaftaran</th>
                                <th>No. Bukti Penerimaan</th>
                                <th>Tanggal</th>
                                <th>Pengirim / Pemasok</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Jumlah</th>
                                <th>Mata Uang</th>
                                <th>Nilai Barang</th>
                            </tr>
                        @elseif($reportType === 'outbound')
                            <tr>
                                <th>No</th>
                                <th>Jenis Dokumen</th>
                                <th>Nomor Pendaftaran</th>
                                <th>Tanggal Pendaftaran</th>
                                <th>No. Bukti Pengeluaran</th>
                                <th>Tanggal</th>
                                <th>Penerima / Pembeli</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Jumlah</th>
                                <th>Mata Uang</th>
                                <th>Nilai Barang</th>
                            </tr>
                        @elseif($reportType === 'wip')
                            <tr>
                                <th>No</th>
                                <th>No. SPK / WO</th>
                                <th>Tanggal Mulai</th>
                                <th>Kode Barang (WIP)</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Jumlah WIP</th>
                                <th>Tahap Proses</th>
                                <th>Status Produksi</th>
                            </tr>
                        @elseif($reportType === 'activity_log')
                            <tr>
                                <th>No</th>
                                <th>Waktu Aktivitas</th>
                                <th>Aktor / Pengguna</th>
                                <th>Aksi</th>
                                <th>No. Dokumen / Referensi</th>
                                <th>IP Address</th>
                                <th>Keterangan</th>
                            </tr>
                        @else
                            {{-- Laporan Mutasi (Raw, Finished, Capital, Reject) --}}
                            <tr>
                                <th>No</th>
                                <th>Kode Barang</th>
                                <th>Nama Barang</th>
                                <th>Satuan</th>
                                <th>Saldo Awal</th>
                                <th>Pemasukan</th>
                                <th>Pengeluaran</th>
                                <th>Penyesuaian (Adj)</th>
                                <th>Saldo Buku</th>
                                <th>Stock Opname</th>
                                <th>Selisih</th>
                                <th>Keterangan</th>
                            </tr>
                        @endif
                    </thead>
                    <tbody>
                        <tr>
                            <td colspan="13" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fa-2x mb-2 d-block text-secondary"></i>
                                Belum ada data mutasi kepabeanan pada periode terpilih.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
