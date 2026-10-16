<?php

// FIX: multicurrency — seluruh label modul dan kesalahan bisnis dapat diterjemahkan.
return [
    'account_currency' => 'Mata uang akun', 'account_currency_base' => 'Mata uang dasar (kosong)',
    'account_currency_used' => 'Mata uang akun yang sudah memiliki jurnal tidak dapat diubah atau dikosongkan.',
    'account_currency_invalid' => 'Pilih mata uang aktif selain mata uang dasar perusahaan.',
    'account_currency_control' => 'Mata uang asing tidak diperbolehkan untuk akun kontrol.',
    'account_currency_cash_only' => 'Mata uang asing hanya diperbolehkan untuk tipe Cash & Bank.',
    // FIX: multicurrency — label formulir jurnal dan saldo awal valas.
    'override_reason' => 'Alasan override kurs',
    'equivalent' => 'Ekuivalen mata uang dasar (preview)',
    'title' => 'Master Mata Uang', 'rates' => 'Kurs Mata Uang', 'code' => 'Kode',
    'name' => 'Nama', 'symbol' => 'Simbol', 'decimals' => 'Desimal', 'show' => 'Tampil',
    'active' => 'Aktif', 'base' => 'Dasar', 'save' => 'Simpan', 'delete' => 'Hapus',
    'date' => 'Tanggal mulai', 'until' => 'Berlaku sampai', 'rate' => 'Kurs per 1 unit valas',
    'type' => 'Tipe', 'source' => 'Sumber', 'created' => 'Dibuat oleh / tanggal',
    'updated' => 'Diubah oleh / waktu', 'already_used' => 'Sudah dipakai',
    'from' => 'Dari tanggal', 'to' => 'Sampai tanggal', 'filter' => 'Filter',
    'import' => 'Impor CSV', 'add' => 'Tambah kurs', 'manual' => 'Manual',
    'spot' => 'Spot', 'tax' => 'Pajak', 'average' => 'Rata-rata',
    'quoted_unit' => 'Jumlah unit kuotasi', 'quoted_rate' => 'Nilai asli kuotasi',
    'quoted_help' => 'Kurs per 1 unit dihitung server saat disimpan: nilai asli ÷ jumlah unit.',
    'kmk_help' => 'Isi nilai asli dan jumlah unit dari lampiran KMK beserta rentang berlaku. Untuk JPY per 100 yen, isi jumlah unit 100; server melakukan normalisasi.',
    'bi_help' => 'Input kurs BI per 1 unit valas. Nilai JPY per 100 yen harus dibagi 100. Pemilihan kurs jual/beli/tengah mengikuti kebijakan finance; belum ada sinkronisasi otomatis.',
    'csv_help' => 'CSV: currency_code,rate_date,rate,type; opsional source,valid_until. TAX wajib valid_until. Desimal memakai titik, tanpa pemisah ribuan. Maksimal 1.000 baris.',
    'saved' => 'Data mata uang/kurs berhasil disimpan.', 'missing' => 'Kurs yang berlaku tidak tersedia.',
    'invalid' => 'Mata uang, nilai kurs, atau pasangan sumber/tipe tidak valid.',
    'duplicate' => 'Kurs duplikat atau rentang berlaku kurs pajak bertumpang tindih.',
    'used' => 'Kurs yang sudah dipakai tidak boleh diubah atau dihapus.',
    'range' => 'Rentang tanggal harus berurutan dan maksimal tiga bulan kalender.',
    'csv_invalid' => 'Struktur atau isi CSV tidak valid.', 'empty' => 'Belum ada data.',
];