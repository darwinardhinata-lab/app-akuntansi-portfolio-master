<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Portal Pengajuan Dana — Internal Employee</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: #f1f5f9;
            background-image: radial-gradient(#cbd5e1 1px, transparent 1px);
            background-size: 20px 20px;
            color: #334155;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
        }

        .main-card {
            background: #ffffff;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(15, 23, 42, 0.05), 0 1px 3px rgba(15, 23, 42, 0.05);
            overflow: hidden;
            max-width: 1100px;
            width: 100%;
            display: flex;
            flex-direction: row;
        }

        /* LEFT PANEL - BRANDING */
        .brand-panel {
            background: linear-gradient(135deg, #4f46e5 0%, #3b82f6 100%);
            color: #ffffff;
            padding: 3.5rem;
            width: 40%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            top: -50px;
            left: -50px;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            filter: blur(40px);
        }

        .brand-title { font-weight: 800; font-size: 2.2rem; line-height: 1.2; margin-bottom: 1rem; position: relative; z-index: 1;}
        .brand-subtitle { font-size: 1rem; opacity: 0.9; line-height: 1.6; position: relative; z-index: 1; }
        
        .feature-list { list-style: none; padding: 0; margin-top: 2rem; position: relative; z-index: 1;}
        .feature-list li { margin-bottom: 1.2rem; display: flex; align-items: center; gap: 12px; font-size: 0.95rem; font-weight: 500; opacity: 0.95;}
        .feature-list i { background: rgba(255,255,255,0.2); padding: 8px; border-radius: 8px; width: 34px; text-align: center; }

        /* RIGHT PANEL - FORM */
        .form-panel {
            padding: 3rem;
            width: 60%;
            background: #ffffff;
        }

        .form-label { font-weight: 700; color: #1e293b; font-size: 0.82rem; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;}
        
        .form-control, .form-select {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 0.95rem;
            color: #0f172a;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .form-control:focus, .form-select:focus {
            background-color: #ffffff;
            border-color: #4f46e5;
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.1);
            outline: none;
        }

        .form-control::placeholder { color: #94a3b8; font-weight: 400; }

        .input-group-text {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-right: none;
            border-radius: 12px 0 0 12px;
            color: #64748b;
            font-weight: 700;
        }
        .input-group .form-control { border-left: none; padding-left: 0; }
        .input-group .form-control:focus { border-left: 1px solid #4f46e5; padding-left: 16px;}

        .btn-submit {
            background: #4f46e5;
            color: white;
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 1.05rem;
            border: none;
            width: 100%;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(79, 70, 229, 0.2);
        }

        .btn-submit:hover {
            background: #4338ca;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
        }

        /* Responsive Breakpoints */
        @media (max-width: 991px) {
            .main-card { flex-direction: column; }
            .brand-panel { width: 100%; padding: 2.5rem 2rem; }
            .form-panel { width: 100%; padding: 2rem; }
            .feature-list { display: none; }
        }
    </style>
</head>
<body>

<div class="main-card">
    
    <div class="brand-panel">
        <div>
            <div class="mb-4">
                <div class="d-inline-flex align-items-center justify-content-center bg-white text-primary rounded-3 shadow-sm mb-3" style="width: 52px; height: 52px; font-size: 1.6rem;">
                    <i class="fa-solid fa-paper-plane"></i>
                </div>
            </div>
            <h1 class="brand-title">Portal Pengajuan Dana Internal</h1>
            <p class="brand-subtitle">Sistem formulir terintegrasi otomatis untuk permohonan pencairan kas operasional, reimbursement, dan pembayaran ke vendor.</p>
            
            <ul class="feature-list">
                <li><i class="fa-solid fa-bolt"></i> Tersinkronisasi langsung ke Dashboard Finance</li>
                <li><i class="fa-solid fa-shield-halved"></i> Audit trail aman dan terdokumentasi</li>
                <li><i class="fa-solid fa-clock-rotate-left"></i> Pemrosesan instan dan transparan</li>
            </ul>
        </div>
        <div class="mt-4 pt-4 border-top border-light border-opacity-25" style="font-size: 0.85rem; font-weight: 600; opacity: 0.8;">
            &copy; {{ date('Y') }} ERP Accounting System.
        </div>
    </div>

    <div class="form-panel">
        
        @if(session('success'))
            <div class="alert alert-success border-0 shadow-sm rounded-3 mb-4 p-3 d-flex align-items-center gap-3" style="background-color: #ecfdf5; color: #065f46;">
                <i class="fa-solid fa-circle-check fs-2 text-success"></i>
                <div>
                    <h6 class="fw-bold mb-1">Pengajuan Terekam!</h6>
                    <p class="mb-0 small">{{ session('success') }}</p>
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="alert alert-danger border-0 shadow-sm rounded-3 mb-4 p-3" style="background-color: #fef2f2; color: #991b1b;">
                <i class="fa-solid fa-triangle-exclamation me-2"></i> {{ session('error') }}
            </div>
        @endif

        <form action="{{ route('payment.public_store') }}" method="POST" enctype="multipart/form-data">
            @csrf

            <div class="row g-3">
                
                <div class="col-md-6">
                    <label class="form-label">Tgl Pengajuan <span class="text-danger">*</span></label>
                    <input type="date" name="tgl_pengajuan" class="form-control" value="{{ date('Y-m-d') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Tgl Transaksi <span class="text-danger">*</span></label>
                    <input type="date" name="tgl_transaksi" class="form-control" value="{{ date('Y-m-d') }}" required>
                    <small class="text-muted" style="font-size: 0.8rem;">Tanggal transaksi yang akan muncul di jurnal</small>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Jatuh Tempo Bayar <span class="text-muted text-lowercase" style="font-weight: 500;">(Opsional)</span></label>
                    <input type="date" name="jatuh_tempo" class="form-control" value="{{ old('jatuh_tempo') }}">
                </div>

                <div class="col-md-6">
                    <label class="form-label">Pemohon (PIC) <span class="text-danger">*</span></label>
                    <input type="text" name="penerima_pj" class="form-control" placeholder="Nama Lengkap Karyawan" value="{{ old('penerima_pj') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Divisi / Bagian <span class="text-danger">*</span></label>
                    <select name="id_divisi" class="form-select" required>
                        <option value="">Pilih Divisi...</option>
                        @foreach($divisi as $div)
                            <option value="{{ $div->id_divisi }}" {{ old('id_divisi') == $div->id_divisi ? 'selected' : '' }}>{{ $div->nama_divisi }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Kategori Pengeluaran <span class="text-danger">*</span></label>
                    <select name="kategori_payment" class="form-select" required>
                        <option value="">Pilih Kategori...</option>
                        <option value="PEMBELIAN & OPERASIONAL">PEMBELIAN & OPERASIONAL</option>
                        <option value="PEMBELIAN PERSEDIAAN (UANG MUKA)">PEMBELIAN PERSEDIAAN (UANG MUKA)</option>
                        <option value="PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)">PEMBELIAN PERSEDIAAN (PEMBAYARAN HUTANG)</option>
                        <option value="ASET">ASET</option>
                        <option value="PRIVE">PRIVE (Pribadi)</option>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Toko / Vendor Tujuan <span class="text-danger">*</span></label>
                    <input type="text" name="vendor_toko" class="form-control" placeholder="Nama Toko atau Supplier" value="{{ old('vendor_toko') }}" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">No. Rekening / VA <span class="text-muted text-lowercase" style="font-weight: 500;">(Opsional)</span></label>
                    <input type="text" name="rekening_va" class="form-control" placeholder="Nama Bank - Nomor - Atas Nama" value="{{ old('rekening_va') }}">
                </div>

                <div class="col-12">
                    <label class="form-label">Keperluan Pengajuan <span class="text-danger">*</span></label>
                    <textarea name="keterangan" class="form-control" rows="2" placeholder="Jelaskan secara rinci detail item dan tujuan penggunaan dana..." required>{{ old('keterangan') }}</textarea>
                </div>

                <div class="col-12">
                    <label class="form-label">Total Nominal <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">Rp</span>
                        <input type="text" id="nominal_mask" class="form-control form-control-lg fw-bold text-primary" placeholder="0" required>
                        <input type="hidden" name="nominal" id="nominal_asli" required>
                    </div>
                </div>

                <div class="col-12">
                    <label class="form-label">Upload Bukti / Nota <span class="text-muted text-lowercase" style="font-weight: 500;">(Opsional)</span></label>
                    <input type="file" name="bukti_file" class="form-control" accept=".jpg,.jpeg,.png,.pdf">
                    <small class="text-muted mt-1 d-block" style="font-size: 0.8rem;"><i class="fa-solid fa-paperclip"></i> Format yang diizinkan: JPG, PNG, PDF (Maks. 5 MB)</small>
                </div>

                <div class="col-12 mt-2">
                    <div class="p-3 rounded-3 border" style="background-color: #f8fafc; border-color: #cbd5e1;">
                        <label class="form-label text-primary"><i class="fa-solid fa-lock me-1"></i> Otorisasi Keamanan <span class="text-danger">*</span></label>
                        <input type="password" name="pin_perusahaan" inputmode="numeric" pattern="[0-9]*" class="form-control" placeholder="Masukkan PIN Perusahaan" required>
                        <small class="text-muted mt-1 d-block" style="font-size: 0.8rem;">PIN verifikasi karyawan internal.</small>
                    </div>
                </div>

                <div class="col-12 mt-4">
                    <button type="submit" class="btn-submit">
                        <i class="fa-solid fa-paper-plane me-2"></i> Kirim Form Pengajuan
                    </button>
                </div>
            </div>
        </form>
    </div>

</div>

<script>
    const nominalMask = document.getElementById('nominal_mask');
    const nominalAsli = document.getElementById('nominal_asli');

    nominalMask.addEventListener('input', function(e) {
        let bilangan = this.value.replace(/[^0-9]/g, '');
        nominalAsli.value = bilangan;
        
        if (bilangan) {
            let number_string = bilangan.toString(),
                sisa   = number_string.length % 3,
                rupiah = number_string.substr(0, sisa),
                ribuan = number_string.substr(sisa).match(/\d{3}/gi);
                
            if (ribuan) {
                let separator = sisa ? '.' : '';
                rupiah += separator + ribuan.join('.');
            }
            this.value = rupiah;
        } else {
            this.value = '';
        }
    });
</script>
</body>
</html>