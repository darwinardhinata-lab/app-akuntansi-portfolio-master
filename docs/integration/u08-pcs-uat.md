# U08/UI-06 — Paket UAT manusia: import Bahan Penolong PCS

**Status: DEFERRED — belum dijalankan atau disetujui. Produksi: NO-GO.** Jangan gunakan fixture Finance 8912/8913 untuk skenario ini. Saat UAT dijadwalkan, gunakan target nonproduksi terisolasi dengan data sintetis dan akun uji yang disediakan untuk sesi tersebut.

## Persiapan

- Siapkan dua aktor dalam scope MGI: pembaca dengan akses panel dan `material.view`, serta pengimpor yang juga memiliki `material.create`.
- Unduh template CSV Bahan Penolong dari aplikasi. Pertahankan header pada record 6 dan delimiter titik koma. Tambahkan dua kode baru yang diawali nol pada record 7 dan 8, nama, unit literal `PCS`, dan opsional meter per gulung `12.75`. Jangan mengonversi kode menjadi angka.
- Siapkan salinan terpisah untuk kode lama, kode berulang, unit `ROLL`, dan meter `12.345`. Gunakan kode baru untuk setiap skenario kecuali bila sengaja menguji duplikat atau retry.

## Skenario dan hasil yang diharapkan

| Skenario | Langkah dan hasil yang diharapkan | Aktual |
| --- | --- | --- |
| Alur utama | Panel ERP → Master Bahan Penolong → Preview Import. Unggah CSV, validasi, klik **Simpan**, lalu kembali ke lookup. Kedua kode muncul utuh; unit `PCS`, akun `114004`, stok dan HPP awal nol. | Belum diisi |
| Validasi | Preview file negatif. Nomor baris dan alasan terlihat, dan tidak ada master baru. | Belum diisi |
| Izin | Pembaca dapat preview tetapi tidak dapat menyimpan. Cabut `material.create` pengimpor setelah preview pada fixture uji: Simpan ditolak dan tidak ada data baru. | Belum diisi |
| Bentrok dan retry | Simpan kode yang sama melalui sesi pengimpor kedua setelah preview pertama. Simpan pada preview pertama ditolak seluruhnya. Unggah ulang batch yang sudah tersimpan; tidak ada kode ganda. | Belum diisi |
| Efek samping | Bandingkan saldo stok, HPP dan jurnal sebelum/sesudah. Master lama dan saldo tidak berubah. | Belum diisi |

Bukti teknis sebelumnya: commit implementasi `df20438`; enam grup skenario Chrome/MariaDB disposable mencakup Simpan **CSV**, rollback saat insert kedua gagal, dan bentrok insert paralel. XLSX diperiksa oleh regresi preview, bukan Simpan browser. Bukti teknis bukan persetujuan UAT.

**Tanggal/aktor/target:** belum diisi. **Temuan dan sign-off:** belum diisi.