@extends('layouts.app')

@section('top_bar_left')
    <x-breadcrumb :links="['Akuntansi' => '#', 'Subledger AP' => null]" />
@endsection

@section('content')
<div class="container-fluid px-4 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-file-invoice text-danger me-2"></i>Manajemen Hutang Pembelian</h4>
            <p class="text-muted small mb-0">Buku Besar Pembantu Hutang, pemantauan tagihan supplier, pelunasan, dan retur material.</p>
        </div>
    </div>

    <ul class="nav nav-tabs mb-4 border-bottom">
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'tagihan' ? 'active bg-danger text-white border-bottom-0' : 'text-muted' }}" 
               href="{{ route('reports.ap_subledger', ['tab' => 'tagihan', 'account_code' => $accountCode ?? '']) }}">
                <i class="fa-solid fa-receipt me-1"></i> Tagihan (Belum Lunas)
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'pembayaran' ? 'active bg-success text-white border-bottom-0' : 'text-muted' }}" 
               href="{{ route('reports.ap_subledger', ['tab' => 'pembayaran', 'account_code' => $accountCode ?? '']) }}">
                <i class="fa-solid fa-money-check-dollar me-1"></i> Riwayat Pelunasan
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link fw-bold px-4 {{ $tab == 'retur' ? 'active bg-warning text-dark border-bottom-0' : 'text-muted' }}" 
               href="{{ route('reports.ap_subledger', ['tab' => 'retur', 'account_code' => $accountCode ?? '']) }}">
                <i class="fa-solid fa-reply-all me-1"></i> Retur Pembelian
            </a>
        </li>
    </ul>

    {{-- Alert Filter Aktif jika Masuk dari Drill-down Hyperlink Akun --}}
    @if(isset($accountCode) && $accountCode)
        @php
            // Menarik nama akun langsung dari database
            $accountName = \App\Models\Account::where('account_code', $accountCode)->value('account_name') ?? 'Nama Akun Tidak Ditemukan';
        @endphp
        <div class="alert alert-danger py-2 shadow-sm fw-bold small mb-3 d-flex justify-content-between align-items-center" style="border-radius: 8px; background-color: #fdf2f2; border-color: #f8d7da;">
            <span class="text-danger"><i class="fa-solid fa-filter me-2"></i> Menampilkan rincian khusus Akun: <span class="badge bg-danger text-white fw-bold fs-6">{{ $accountCode }} - {{ $accountName }}</span></span>
            <a href="{{ route('reports.ap_subledger', ['tab' => $tab]) }}" class="btn btn-sm btn-outline-danger bg-white fw-bold">Hapus Filter <i class="fa-solid fa-circle-xmark ms-1"></i></a>
        </div>
    @endif

    <div class="card shadow-sm border-0 rounded-3">
        <div class="table-responsive bg-white rounded-3">
            <table class="table table-hover align-middle mb-0 text-nowrap">
                <thead class="table-dark text-uppercase" style="font-size: 0.85rem;">
                    <tr>
                        <th class="ps-4 py-3">Tanggal</th>
                        <th class="py-3">No. Bukti Jurnal / PO</th>
                        <th class="py-3">Deskripsi Transaksi / Supplier</th>
                        @if($tab == 'tagihan')
                            <th class="text-end py-3">Total Nilai Tagihan</th>
                            <th class="text-end pe-4 py-3 text-danger">Sisa Hutang Kita</th>
                        @else
                            <th class="text-end pe-4 py-3">Nominal Transaksi (Rp)</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    @php $data = $tab == 'tagihan' ? $unpaidBills : ($tab == 'pembayaran' ? $payments : $returns); @endphp
                    
                    @forelse($data as $row)
                        <tr>
                            <td class="ps-4">{{ \Carbon\Carbon::parse($row->transaction_date)->format('d/m/Y') }}</td>
                            <td class="fw-bold text-primary">
                                <a href="javascript:void(0)" class="text-decoration-none text-primary" onclick="showJournalDetail('{{ $row->evidence_number }}')" title="Klik untuk lihat detail mutasi jurnal">
                                    {{ $row->evidence_number }}
                                </a>
                            </td>
                            <td style="max-width: 350px; overflow: hidden; text-overflow: ellipsis;">{{ $row->notes }}</td>
                            
                            @if($tab == 'tagihan')
                                <td class="text-end fw-bold text-muted">Rp {{ number_format($row->total_invoice, 0, ',', '.') }}</td>
                                <td class="text-end pe-4 fw-bold text-danger">Rp {{ number_format($row->remaining_balance, 0, ',', '.') }}</td>
                            @else
                                <td class="text-end pe-4 fw-bold {{ $tab == 'pembayaran' ? 'text-success' : 'text-warning text-dark' }}">
                                    Rp {{ number_format($row->amount, 0, ',', '.') }}
                                </td>
                            @endif
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center py-5 text-muted">
                                <i class="fa-solid fa-folder-open fs-2 mb-2 d-block text-light"></i>
                                Belum ada riwayat transaksi pada kategori ini.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @if (method_exists($data, 'links'))
        <div class="mt-4 d-flex justify-content-center">
            {{ $data->appends(request()->query())->links() }}
        </div>
    @endif
</div>

<!-- Modal Detail Jurnal -->
<div class="modal fade" id="journalDetailModal" tabindex="-1" aria-labelledby="journalDetailModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow">
      <div class="modal-header bg-danger text-white border-bottom-0 rounded-top">
        <h5 class="modal-title fw-bold" id="journalDetailModalLabel"><i class="fa-solid fa-file-invoice me-2"></i>Detail Transaksi</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4" id="journalDetailModalBody">
        <div class="text-center py-4">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted small">Mengambil rincian mutasi jurnal...</p>
        </div>
      </div>
      <div class="modal-footer bg-light border-top-0 rounded-bottom">
        <button type="button" class="btn btn-secondary fw-bold shadow-sm" data-bs-dismiss="modal">Tutup</button>
      </div>
    </div>
  </div>
</div>

@endsection

@push('scripts')
<script>
function showJournalDetail(evidenceNumber) {
    const modalBody = document.getElementById('journalDetailModalBody');
    modalBody.innerHTML = `
        <div class="text-center py-4">
            <div class="spinner-border text-danger" role="status">
                <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 text-muted small">Mengambil rincian mutasi jurnal...</p>
        </div>
    `;
    
    const modalElement = document.getElementById('journalDetailModal');
    let myModal = bootstrap.Modal.getInstance(modalElement);
    if (!myModal) {
        myModal = new bootstrap.Modal(modalElement, { keyboard: true });
    }
    myModal.show();

    fetch("{{ route('jurnal.detail.ajax') }}?evidence_number=" + encodeURIComponent(evidenceNumber))
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                modalBody.innerHTML = data.html;
            } else {
                modalBody.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>' + data.message + '</div>';
            }
        })
        .catch(error => {
            modalBody.innerHTML = '<div class="alert alert-danger"><i class="fa-solid fa-triangle-exclamation me-2"></i>Terjadi kesalahan sistem saat mengambil data jurnal.</div>';
        });
}
</script>
@endpush