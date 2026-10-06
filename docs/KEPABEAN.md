# Dashboard Kepabean dan Dokumen Pabean

Menu berada pada bagian Kepatuhan → Bea Cukai. Route lokal `/kepabean`
dan `/kepabean/documents` tersedia saat CUSTOMS_REPORTS_ENABLED atau
CEISA_ENABLED aktif. Keduanya memerlukan login; mengikuti pola akses
modul yang sudah ada, semua pengguna login dapat merekam dokumen.

Data menggunakan `cst_customs_documents`, detail, dan riwayat status
yang sama dengan modul H2H. Tidak ada tabel paralel atau data dummy.

Dashboard menyediakan filter bulan, jumlah dokumen dibuat bulan tersebut,
pengajuan berdasarkan submitted_at, draft yang dibuat pada bulan tersebut,
total keseluruhan, persentase perubahan terhadap bulan sebelumnya, tren
harian, distribusi jenis, jumlah per jenis, dan lima dokumen terbaru.
VOIDED tidak dihitung dalam statistik. Pembanding nol tidak menghasilkan
persentase perubahan palsu.

Daftar menyediakan pencarian nomor aju, nomor pendaftaran, nomor internal,
dan status; filter jenis/status; muat ulang; pagination 15 baris; preview;
tambah dan edit draft beserta rincian barang. Jenis yang didukung: BC 4.0,
2.3, 2.5, 2.6.1, 2.6.2, 2.7, 3.0, 4.1 serta PIB/PEB historis.

Hapus adalah pengarsipan draft menjadi VOIDED, bukan hard delete. Dokumen
dan barang tetap disimpan untuk audit, dapat dicari lewat filter VOIDED.
Edit/hapus memakai transaksi dan row lock serta hanya menerima DRAFT.
Total nilai dihitung ulang dari nilai setiap baris, bukan input header.
Nomor internal dihasilkan melalui DocumentSequence.

Perekaman lokal tidak mengirim HTTP, mengantrekan job, menghasilkan nomor
aju resmi, atau mengubah status menjadi diserahkan. Nomor aju input adalah
referensi yang dicatat pengguna. Tanggal pendaftaran resmi belum ada dalam
schema lama sehingga tidak disubstitusi dengan tanggal respons.
Pengiriman CEISA tetap menggunakan modul H2H yang terpisah; dukungan penuh
payload resmi semua jenis BC tidak dinyatakan selesai oleh fitur ini.

Tampilan memakai layout aplikasi yang ada (termasuk tema), tidak menyalin
branding lampiran. Master Data CEISA pada screenshot bukan bagian dua menu
yang ditambahkan. Terjemahan ID/EN/ZH tersedia.