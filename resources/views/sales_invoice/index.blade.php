@extends('layouts.app') {{-- Sesuaikan dengan nama master layout Anda --}}

@section('top_bar_left')
    <x-breadcrumb :links="['Penjualan' => '#', 'Faktur Penjualan' => null]" />
@endsection

@section('content')
<div class="container-fluid px-4 py-3">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-3">
        <div>
            <h4 class="fw-bold text-dark mb-0"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Modul Faktur Penjualan (Invoices)</h4>
            <p class="text-muted small mb-0">Arsip dokumen sah pengubah stok gudang dan dasar posting keuangan.</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-warning fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0" id="btnSyncTemp">
                <i class="fa-solid fa-cloud-arrow-down me-1"></i> Sync ke Temp
            </button>
            <button type="submit" form="filterForm" name="export" value="excel" class="btn btn-success fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-file-excel me-1"></i> Export Excel
            </button>
            <a href="{{ route('invoice.create') }}" class="btn btn-primary fw-bold px-3 shadow-sm flex-grow-1 flex-md-grow-0">
                <i class="fa-solid fa-plus me-1"></i> Buat Faktur Manual
            </a>
        </div>
    </div>

    <div class="card p-3 mb-4 shadow-sm border-0 bg-white" style="border-radius: 12px;">
        <div class="mb-2 text-primary fw-bold small"><i class="fa-solid fa-filter me-1"></i> Filter Analitik Pencarian</div>
        <form action="{{ route('invoice.index') }}" method="GET" id="filterForm" class="row g-2 align-items-end">
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Mulai Tgl</label>
                <input type="date" name="start_date" class="form-control form-control-sm" value="{{ request('start_date', $start_date) }}">
            </div>
            <div class="col-12 col-sm-6 col-md-2">
                <label class="form-label small fw-bold text-muted mb-1">Sampai Tgl</label>
                <input type="date" name="end_date" class="form-control form-control-sm" value="{{ request('end_date', $end_date) }}">
            </div>
            <div class="col-12 col-sm-12 col-md-6">
                <label class="form-label small fw-bold text-muted mb-1">Pencarian Nomor / Keterangan</label>
                <div class="input-group input-group-sm">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="search" class="form-control border-start-0 ps-0" placeholder="Cari No. Faktur / Pelanggan..." value="{{ request('search', $search) }}">
                </div>
            </div>
            <div class="col-12 col-sm-12 col-md-2 d-flex gap-2">
                <button type="submit" class="btn btn-sm btn-primary fw-bold flex-grow-1"><i class="fa-solid fa-search"></i> Cari</button>
                <a href="{{ route('invoice.index') }}" class="btn btn-sm btn-danger fw-bold" title="Reset Filter"><i class="fa-solid fa-sync"></i></a>
            </div>
        </form>
    </div>

    <ul class="nav nav-pills mb-3 bg-white p-2 rounded border shadow-sm" id="pills-tab" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-bold px-4" id="pills-daftar-tab" data-bs-toggle="pill" data-bs-target="#pills-daftar" type="button" role="tab"><i class="fa-solid fa-table-list me-1"></i> Daftar Arsip Faktur</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-bold px-4 btn-analytics" id="pills-pivot-tab" data-bs-toggle="pill" data-bs-target="#pills-pivot" type="button" role="tab" style="color: #4f46e5;"><i class="fa-solid fa-chart-pie me-1"></i> Analisa Pivot (Analytic Matrix)</button>
        </li>
    </ul>

    <div class="tab-content" id="pills-tabContent">
        
        <div class="tab-pane fade show active" id="pills-daftar" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3">
                <div class="table-responsive bg-white rounded-3">
                    <table class="table table-hover align-middle mb-0 text-nowrap" style="font-size: 0.85rem;">
                        <thead class="table-dark">
                            <tr>
                                <th class="ps-4 py-3">Tanggal Faktur</th>
                                <th class="py-3">No. Faktur (Invoice)</th>
                                <th class="py-3">Ref. Pesanan (SO)</th>
                                <th class="py-3">Nama Pelanggan</th>
                                <th class="py-3">Asal Toko / Gudang</th>
                                <th class="text-end py-3">Total Nilai</th>
                                <th class="text-center py-3">Status Bayar</th>
                                <th class="text-center pe-4 py-3">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($invoices as $inv)
                            <tr>
                                <td class="ps-4 fw-medium">{{ date('d M Y', strtotime($inv->transaction_date)) }}</td>
                                 <td class="fw-bold text-success"><a href="{{ route('invoice.show', $inv->id) }}" class="text-success text-decoration-none">{{ $inv->invoice_number }}</a></td>

                                <td class="fw-bold text-success">{{ $inv->invoice_number }}</td>
                                <td class="fw-bold text-primary">{{ $inv->salesOrder ? $inv->salesOrder->so_number : '-' }}</td>
                                <td class="fw-bold text-dark">{{ $inv->contact_name }}</td>
                                <td><span class="badge bg-light text-secondary border"><i class="fa-solid fa-warehouse me-1"></i>{{ $inv->salesOrder->location_name ?? 'Pusat' }}</span></td>
                                <td class="text-end fw-bold text-dark">Rp {{ number_format($inv->grand_total, 0, ',', '.') }}</td>
                                <td class="text-center">
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger px-2 py-1 small fw-bold">{{ $inv->payment_status }}</span>
                                </td>
                                <td class="text-center pe-4">
                                    <div class="btn-group">
                                        <button type="button" onclick="showEntityLog('{{ $inv->invoice_number }}')" class="btn btn-sm btn-outline-secondary" title="Jejak Log Aktivitas"><i class="fa-solid fa-clock-rotate-left"></i></button>
                                        <a href="{{ route('invoice.show', $inv->id) }}" class="btn btn-sm btn-outline-primary" title="Lihat Rincian Faktur"><i class="fa-solid fa-eye"></i></a>
                                        <form action="{{ route('invoice.destroy', $inv->id) }}" method="POST" class="m-0" onsubmit="return confirm('Hapus faktur ini? Stok dan Jurnal akan dibatalkan.')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Hapus" style="border-top-left-radius: 0; border-bottom-left-radius: 0;"><i class="fa-solid fa-trash"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            @empty
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">Belum ada faktur yang diterbitkan atau kecocokan filter tidak ditemukan.</td>
                            </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <div class="card-footer bg-white p-3 d-flex justify-content-end">{{ $invoices->links() }}</div>
            </div>
        </div>

        <div class="tab-pane fade" id="pills-pivot" role="tabpanel">
            <div class="card border-0 shadow-sm rounded-3 mb-3">
                <div class="card-header bg-light border-bottom p-3">
                    <div class="row g-2 align-items-center">
                        <div class="col-md-3">
                            <small class="fw-bold text-muted d-block mb-1">Dimensi Baris (Rows)</small>
                            <select id="pivotRow" class="form-select form-select-sm border-primary fw-bold" onchange="renderPivotMatrix()">
                                <option value="lokasi">Asal Toko / Lokasi Gudang</option>
                                <option value="sku">Kode Produk (SKU)</option>
                                <option value="pelanggan">Nama Pelanggan (Customer)</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <small class="fw-bold text-muted d-block mb-1">Dimensi Kolom (Columns)</small>
                            <select id="pivotCol" class="form-select form-select-sm border-primary fw-bold" onchange="renderPivotMatrix()">
                                <option value="bulan">Periode Waktu (Bulan Transaksi)</option>
                                <option value="lokasi">Asal Toko / Lokasi Gudang</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <small class="fw-bold text-muted d-block mb-1">Metrik Nilai (Values)</small>
                            <select id="pivotVal" class="form-select form-select-sm border-primary fw-bold" onchange="renderPivotMatrix()">
                                <option value="total_omset">Total Nilai Omset Penjualan (Rupiah)</option>
                                <option value="total_qty">Kuantitas Barang Terjual (Pcs)</option>
                            </select>
                        </div>
                        <div class="col-md-3 text-md-end pt-3">
                            <span class="badge bg-info text-dark border p-2 small"><i class="fa-solid fa-bolt"></i> Real-time Aggregated Grid</span>
                        </div>
                    </div>
                </div>
                
                <div class="card-body bg-white p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped align-middle mb-0" id="pivotResultTable" style="font-size: 0.85rem;">
                            </table>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    // Oper data aggregasi dari controller ke JavaScript Engine
    const rawData = @json($analyticData);

    function renderPivotMatrix() {
        const rowDim = document.getElementById('pivotRow').value; 
        const colDim = document.getElementById('pivotCol').value; 
        const valDim = document.getElementById('pivotVal').value; 

        // Cegah user memilih baris dan kolom yang sama
        if (rowDim === colDim) {
            alert("Dimensi Baris dan Kolom tidak boleh sama!");
            return;
        }

        // Ambil list unik untuk Baris dan Kolom secara dinamis
        let uniqueRows = [...new Set(rawData.map(item => item[rowDim]))].sort();
        let uniqueCols = [...new Set(rawData.map(item => item[colDim]))].sort();

        let table = document.getElementById('pivotResultTable');
        table.innerHTML = ""; 

        // 1. MEMBUAT HEADER TABEL PIVOT
        let headerHtml = `<thead class="table-secondary text-uppercase fw-bold"><tr>`;
        headerHtml += `<th class="ps-3 py-3" style="width: 25%;">${rowDim.replace('_', ' ')}</th>`;
        
        uniqueCols.forEach(col => {
            headerHtml += `<th class="text-end py-3">${formatColumnHeader(col, colDim)}</th>`;
        });
        headerHtml += `<th class="text-end pe-3 py-3 bg-dark text-white">GRAND TOTAL</th>`;
        headerHtml += `</tr></thead>`;

        // 2. MEMBUAT ISI BARIS DATA MATRIX
        let bodyHtml = `<tbody>`;
        let colTotals = {}; 
        uniqueCols.forEach(c => colTotals[c] = 0);
        let absoluteGrandTotal = 0;

        uniqueRows.forEach(rowKey => {
            bodyHtml += `<tr>`;
            bodyHtml += `<td class="ps-3 fw-bold text-dark">${rowKey}</td>`;
            
            let rowGrandTotal = 0;

            uniqueCols.forEach(colKey => {
                // 👇 PERBAIKAN BUG: Gunakan Filter & Reduce untuk MENJUMLAHKAN (SUM) seluruh data yang cocok 👇
                let matchingItems = rawData.filter(item => item[rowDim] === rowKey && item[colDim] === colKey);
                let value = matchingItems.reduce((sum, item) => sum + parseFloat(item[valDim] || 0), 0);
                // 👆 ---------------------------------------------------------------------------------------- 👆

                rowGrandTotal += value;
                colTotals[colKey] += value;

                bodyHtml += `<td class="text-end fw-medium">${formatValue(value, valDim)}</td>`;
            });

            absoluteGrandTotal += rowGrandTotal;
            bodyHtml += `<td class="text-end fw-bold bg-secondary bg-opacity-10 text-primary">${formatValue(rowGrandTotal, valDim)}</td>`;
            bodyHtml += `</tr>`;
        });

        // 3. MEMBUAT BARIS TOTAL DI PALING BAWAH
        bodyHtml += `<tr class="table-dark fw-bold border-top border-dark">`;
        bodyHtml += `<td class="ps-3">TOTAL KESELURUHAN</td>`;
        uniqueCols.forEach(colKey => {
            bodyHtml += `<td class="text-end">${formatValue(colTotals[colKey], valDim)}</td>`;
        });
        bodyHtml += `<td class="text-end pe-3 text-warning">${formatValue(absoluteGrandTotal, valDim)}</td>`;
        bodyHtml += `</tr>`;
        
        bodyHtml += `</tbody>`;
        
        table.innerHTML = headerHtml + bodyHtml;
    }

    // Helper formatter agar tampilan angka cantik
    function formatValue(num, type) {
        if (num === 0) return '-'; // Tampilkan strip jika kosong agar tabel bersih
        if (type === 'total_omset') {
            return 'Rp ' + num.toLocaleString('id-ID');
        }
        return num.toLocaleString('id-ID') + ' Pcs';
    }

    // Helper formatter khusus untuk merapikan teks Header Kolom
    function formatColumnHeader(colVal, colType) {
        if (colType === 'bulan') {
            let split = colVal.split('-');
            if (split.length === 2) {
                let months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Ags', 'Sep', 'Okt', 'Nov', 'Des'];
                let mIdx = parseInt(split[1]) - 1;
                return months[mIdx] + ' ' + split[0];
            }
        }
        return colVal; // Kembalikan teks asli jika bukan bulan (misal nama toko)
    }

    // Jalankan render otomatis saat tab analytic diklik pertama kali
    document.addEventListener("DOMContentLoaded", function() {
        document.getElementById('pills-pivot-tab').addEventListener('shown.bs.tab', function () {
            renderPivotMatrix();
        });
        
        // Pancing render awal jika tab pivot kebetulan jadi tab default (aktif dari awal)
        if(document.getElementById('pills-pivot-tab').classList.contains('active')) {
            renderPivotMatrix();
        }
    });

    document.getElementById('btnSyncTemp').addEventListener('click', function() {
        Swal.fire({
            title: 'Tarik Data Faktur?',
            text: "Data faktur dari Dashboard akan ditarik ke tabel inv_temp (Fase 1).",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Ya, Tarik Sekarang!',
            cancelButtonText: 'Batal'
        }).then((result) => {
            if (result.isConfirmed) {
                Swal.fire({
                    title: 'Memulai Proses...',
                    text: 'Sedang memicu job di background...',
                    allowOutsideClick: false,
                    didOpen: () => { Swal.showLoading(); }
                });

                fetch("{{ route('invoice.sync_temp') }}", {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Content-Type': 'application/json'
                    }
                })
                .then(res => res.json())
                .then(data => {
                    if(data.success) {
                        Swal.fire('Berhasil!', data.message, 'success');
                    } else {
                        Swal.fire('Error!', data.message || 'Terjadi kesalahan.', 'error');
                    }
                })
                .catch(err => {
                    Swal.fire('Error!', 'Gagal menghubungi server.', 'error');
                });
            }
        });
    });
</script>
@endsection