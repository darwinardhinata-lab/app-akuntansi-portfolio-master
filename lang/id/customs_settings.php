<?php

return [
    'save' => 'Simpan pengaturan',
    'title' => 'Pengaturan Bea Cukai',
    'mode' => 'Mode integrasi',
    'internal' => 'Tanpa H2H — data internal sistem',
    'h2h' => 'Gunakan H2H CEISA — terkunci, belum siap',
    'locked' => 'Aktivasi H2H dikunci sampai API resmi, payload, status, dan ownership perusahaan selesai diverifikasi. Tidak ada pengiriman ke CEISA dari mode internal.',
    'auto_sync' => 'Sinkronisasi otomatis ketujuh laporan dari transaksi internal',
    'scope' => 'Ketujuh laporan diisi dari penerimaan, pengiriman, ledger persediaan, hasil produksi, register aset, dan kejadian reject/sisa. Draft diperbarui saat dibuat, dibuka, diekspor, dan difinalisasi. Sinkronisasi membangun ulang seluruh baris draft; perubahan manual/import akan diganti data sumber. FINAL/DIUNGGAH tidak berubah. Nomor pabean tidak dibuat otomatis. Histori pelepasan aset dan pengeluaran reject belum tersedia pada sistem sumber.',
    'saved' => 'Pengaturan Bea Cukai berhasil disimpan.',
    'sync' => 'Sinkronisasi ulang dari sistem',
];