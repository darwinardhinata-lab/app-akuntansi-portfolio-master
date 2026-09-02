@extends('layouts.app')

@section('top_bar_left')
    <a href="{{ route('jurnal.index') }}" class="btn btn-sm btn-white border fw-bold shadow-sm text-secondary me-3" style="border-radius: 8px;">
        <i class="fa-solid fa-arrow-left me-1"></i> Kembali
    </a>
    <x-breadcrumb :links="['Akuntansi' => '#', 'Jurnal Umum' => route('jurnal.index'), 'Buat Baru' => null]" />
@endsection

@section('content')
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" />

<style>
    .journal-wrapper { font-family: 'Inter', sans-serif; color: #334155; max-width: 1100px; margin: 0 auto; }
    .card-modern { background: #ffffff; border: none; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.04); padding: 30px; }
    .form-label { font-size: 0.85rem; font-weight: 600; color: #64748b; text-transform: uppercase; }
    .input-header { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px 15px; }
    
    .table-clean th { border-bottom: 2px solid #e2e8f0; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; padding: 15px 10px; }
    .table-clean td { vertical-align: middle; padding: 10px 5px; border-bottom: 1px solid #f1f5f9; }
    
    .input-transparent { width: 100%; border: 1px solid #e2e8f0; background: #fff; padding: 8px 10px; font-size: 0.95rem; border-radius: 6px; }
    .input-transparent:focus { border-color: #3b82f6; outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1); }
    
    .btn-tambah { border: 1px dashed #cbd5e1; background: #f8fafc; color: #64748b; width: 100%; padding: 12px; border-radius: 8px; font-weight: 600; transition: 0.2s; }
    .btn-tambah:hover { background: #f1f5f9; color: #3b82f6; border-color: #93c5fd; }
    
    .summary-box { background: #f8fafc; border-radius: 12px; padding: 20px; border: 1px solid #e2e8f0; }
    .btn-hapus { background: #fee2e2; color: #ef4444; border: none; border-radius: 6px; width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; transition: 0.2s; }
    .btn-hapus:hover:not(:disabled) { background: #ef4444; color: white; }
    .btn-hapus:disabled { opacity: 0.5; cursor: not-allowed; }
</style>

<div class="journal-wrapper mt-4 mb-5">
    <div class="mb-4">
        <h3 class="fw-bold mb-1 text-dark">Jurnal Umum Baru</h3>
        <p class="text-muted small">Catat transaksi akuntansi secara manual</p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger shadow-sm border-0 rounded-3">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('jurnal.store') }}" method="POST" id="formJurnal">
        @csrf
        
        <div class="card-modern mb-4">
            <div class="row g-4">
                <div class="col-md-3">
                    <label class="form-label">Tanggal</label>
                    <input type="date" name="transaction_date" class="form-control input-header" value="{{ date('Y-m-d') }}" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">No. Bukti</label>
                    <input type="text" name="evidence_number" class="form-control input-header" placeholder="Misal: BKM-01">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Deskripsi Jurnal</label>
                    <input type="text" name="description" class="form-control input-header" placeholder="Contoh: Pembayaran listrik bulan ini" required>
                </div>
            </div>
        </div>

        <div class="card-modern">
            <div class="table-responsive erp-journal-lines">
                <table class="table table-clean mb-0">
                    <thead>
                        <tr>
                            <th width="35%">KODE AKUN</th>
                            <th width="25%">KODE BANTU (OPSIONAL)</th>
                            <th width="15%">POSISI</th>
                            <th width="20%" class="text-end">NOMINAL (Rp)</th>
                            <th width="5%" class="text-center"></th>
                        </tr>
                    </thead>
                    <tbody id="baris-jurnal">
                        <tr>
                            <td data-label="Kode Akun">
                                <select name="details[0][account_code]" class="input-transparent select-account" required>
                                    <option value="">Pilih Akun...</option>
                                    @foreach($accounts as $akun)
                                        <option value="{{ $akun->account_code }}">{{ $akun->account_code }} - {{ $akun->account_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Kode Bantu">
                                <select name="details[0][helper_code]" class="input-transparent select-helper">
                                    <option value="">- Kosong -</option>
                                    @foreach($helpers as $helper)
                                        <option value="{{ $helper->helper_code }}">{{ $helper->helper_code }} - {{ $helper->entity_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td data-label="Posisi">
                                <select name="details[0][position]" class="input-transparent position-select" required>
                                    <option value="DEBET" selected>DEBET</option>
                                    <option value="KREDIT">KREDIT</option>
                                </select>
                            </td>
                            <td data-label="Nominal (Rp)">
                                <input type="number" name="details[0][amount]" class="input-transparent amount-input text-end fw-bold" value="0" min="0" required>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn-hapus" disabled>×</button>
                            </td>
                        </tr>
                        
                        <tr>
                            <td>
                                <select name="details[1][account_code]" class="input-transparent select-account" required>
                                    <option value="">Pilih Akun...</option>
                                    @foreach($accounts as $akun)
                                        <option value="{{ $akun->account_code }}">{{ $akun->account_code }} - {{ $akun->account_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="details[1][helper_code]" class="input-transparent select-helper">
                                    <option value="">- Kosong -</option>
                                    @foreach($helpers as $helper)
                                        <option value="{{ $helper->helper_code }}">{{ $helper->helper_code }} - {{ $helper->entity_name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <select name="details[1][position]" class="input-transparent position-select" required>
                                    <option value="DEBET">DEBET</option>
                                    <option value="KREDIT" selected>KREDIT</option>
                                </select>
                            </td>
                            <td>
                                <input type="number" name="details[1][amount]" class="input-transparent amount-input text-end fw-bold" value="0" min="0" required>
                            </td>
                            <td class="text-center align-middle">
                                <button type="button" class="btn-hapus" disabled>×</button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <button type="button" class="btn-tambah mt-3" id="btn-tambah">+ Tambah Baris Jurnal</button>

            <div class="summary-box mt-4 d-flex justify-content-between align-items-center">
                <div id="statusIndicator" class="badge rounded-pill p-2 px-3 bg-danger text-white">✕ TIDAK SEIMBANG</div>
                <div class="d-flex gap-4 align-items-center">
                    <div class="text-end">
                        <small class="fw-bold text-muted">TOTAL DEBET</small>
                        <div id="textDebet" class="fw-bold text-success fs-5">0</div>
                    </div>
                    <div class="text-end">
                        <small class="fw-bold text-muted">TOTAL KREDIT</small>
                        <div id="textKredit" class="fw-bold text-dark fs-5">0</div>
                    </div>
                    <button type="submit" class="btn btn-primary fw-bold px-4 py-2" id="btnSimpan" disabled>Simpan Jurnal</button>
                </div>
            </div>
        </div>
    </form>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
    $(document).ready(function() {
        let rowCount = 2; // Mulai dari index 2 karena 0 dan 1 sudah dipakai
        
        // Fungsi inisialisasi Select2
        function initSelect2() {
            $('.select-account, .select-helper').select2({ 
                theme: 'bootstrap-5', 
                width: '100%',
                placeholder: "Pilih..."
            });
        }

        // Fungsi Hitung Total & Validasi Balance
        function hitungTotal() {
            let totalD = 0;
            let totalK = 0;

            $('.amount-input').each(function() {
                let amt = parseFloat($(this).val()) || 0;
                let pos = $(this).closest('tr').find('.position-select').val();
                
                if (pos === 'DEBET') {
                    totalD += amt;
                } else {
                    totalK += amt;
                }
            });

            // Format angka ke format Rupiah (Ribuan)
            $('#textDebet').text(new Intl.NumberFormat('id-ID').format(totalD));
            $('#textKredit').text(new Intl.NumberFormat('id-ID').format(totalK));
            
            // Validasi Seimbang (Balance)
            if (totalD === totalK && totalD > 0) {
                $('#statusIndicator').text('✓ SEIMBANG').addClass('bg-success').removeClass('bg-danger');
                $('#btnSimpan').prop('disabled', false); // Aktifkan tombol simpan
            } else {
                $('#statusIndicator').text('✕ TIDAK SEIMBANG').addClass('bg-danger').removeClass('bg-success');
                $('#btnSimpan').prop('disabled', true); // Matikan tombol simpan
            }
        }

        // --- SOLUSI TOMBOL TAMBAH BARIS ---
        $('#btn-tambah').click(function() {
            // Ambil baris pertama sebagai template
            let templateRow = $('#baris-jurnal tr:first');
            
            // Hancurkan sementara Select2 di baris template agar tidak error saat di-clone
            templateRow.find('.select2-hidden-accessible').select2('destroy');
            
            // Lakukan Cloning
            let newRow = templateRow.clone();
            
            // Perbarui atribut 'name' agar index array-nya berubah (menjadi details[2], details[3], dst)
            newRow.find('select, input').each(function() {
                let name = $(this).attr('name');
                if (name) {
                    let newName = name.replace(/\[\d+\]/, `[${rowCount}]`);
                    $(this).attr('name', newName);
                }
                // Reset nilai inputan number menjadi 0
                if ($(this).attr('type') === 'number') {
                    $(this).val(0);
                } else {
                    $(this).val(''); // Reset select ke kosong
                }
            });

            // Aktifkan tombol hapus untuk baris baru
            newRow.find('.btn-hapus').prop('disabled', false);
            
            newRow.find('td').eq(0).attr('data-label', 'Kode Akun');
            newRow.find('td').eq(1).attr('data-label', 'Kode Bantu');
            newRow.find('td').eq(2).attr('data-label', 'Posisi');
            newRow.find('td').eq(3).attr('data-label', 'Nominal (Rp)');

            $('#baris-jurnal').append(newRow);

            initSelect2();
            
            rowCount++;
            hitungTotal();
        });

        // Trigger hitung total saat angka atau posisi diubah
        $(document).on('input change', '.amount-input, .position-select', hitungTotal);
        
        // Fungsi tombol hapus baris
        $(document).on('click', '.btn-hapus', function() {
            $(this).closest('tr').remove();
            hitungTotal();
        });
        
        // Jalankan saat pertama kali halaman dimuat
        initSelect2();
        hitungTotal();
    });
</script>
@endsection