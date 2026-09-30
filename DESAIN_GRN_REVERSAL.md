# Desain keputusan: Reversal GRN audit-safe (branch `feat/grn-reversal`)

Status: **DRAFT untuk persetujuan — belum ada kode, belum ada perubahan COA/migration.**
Dasar: ringkasan A3 (commit 2f464d7) + kode legacy dari snapshot A2. Source A3 belum saya baca;
poin bertanda (verifikasi A3) harus dicek terhadap `GrnReceivingService` sebelum implementasi.

## 0. Temuan yang harus dijawab SEBELUM reversal (D00)

**Dua akun persediaan berbeda di jalur GRN vs jalur lain.**
- GRN mendebit `114001` (Barang Jadi) dan mengkredit `211001`.
- Penjualan (`SalesOrderService`) mengkredit `config('coa.persediaan')` untuk HPP; default env `11200`.
- Manufaktur barang jadi mendebit `inventory_account_code` produk atau `coa.persediaan_barang_jadi` (`11200`).

Dampak finansial: bila `COA_PERSEDIAAN` di `.env` MGI masih `11200`, stok masuk lewat GRN menambah `114001`
tetapi penjualan hanya mengurangi `11200` → `114001` menumpuk, `11200` bisa negatif, neraca salah
walau kartu stok benar. Hal serupa untuk hutang: pembayaran/Payment Plan yang mendebit `22000`
tidak akan melunasi `211001`.

Jawab dulu (tanpa mengubah COA sampai Anda setuju):
1. Nilai runtime `COA_PERSEDIAAN`, `COA_HUTANG`, `COA_MFG_FG` di `.env` MGI?
2. Apakah `11200/22000` ada di COA 208 akun MGI, atau COA MGI memang hanya `114001/211001`?
3. Pilihan: (a) samakan config legacy ke `114001/211001` via `.env`, atau (b) GRN kembali ke config legacy.
   Keduanya menyentuh COA/mapping → **butuh persetujuan manual Anda**.

## 1. Perilaku yang diusulkan

Prinsip: dokumen posted tidak dihapus; koreksi = dokumen baru yang saling menaut.

| Aspek | Usulan |
|---|---|
| Entitas | `purchase_receipt_reversals` (atau status `REVERSED` + kolom `reversal_of_id`) mengacu GRN sumber |
| Jurnal | Jurnal pembalik baru: debit/kredit dibalik, `source_doc_no` = nomor GRN, tanggal = tanggal reversal |
| Stok | `inventory_ledgers` entri `OUT` bertanda reversal (bukan hapus baris IN); qty PO `qty_received` dikurangi |
| Nilai stok | Rata-rata bergerak dihitung ulang dari ledger; bila stok sudah terjual sebagian → tolak (lihat D03) |
| Bill | Bill terkait status `VOID`/`REVERSED` bila belum ada pembayaran; tautan `journal_id` tetap, tambah `reversal_journal_id` |
| PO | Status kembali `APPROVED`/`PARTIAL` sesuai sisa GRN posted lain (jangan hardcode `APPROVED`) |
| Idempotensi | Kunci unik `company + GRN + REVERSAL`; payload hash beda untuk kunci sama ditolak; retry mengembalikan hasil lama |
| Atomik | Satu `DB::transaction`: kunci PO+GRN+Bill+Produk (`lockForUpdate`), guard status, ledger, jurnal, status |
| Routing | GRN_V1 hanya boleh direverse lewat jalur baru; `GrnProtection` tetap menolak void legacy |

## 2. Keputusan yang butuh jawaban Anda

| ID | Pertanyaan | Usulan default |
|---|---|---|
| D01 | Periode tertutup: tolak reversal, atau posting jurnal pembalik di periode berjalan? | Posting di periode berjalan dengan referensi tanggal GRN asal (butuh ada tabel/flag periode — saat ini belum ada, verifikasi A3) |
| D02 | Bill sudah dibayar/sebagian | Tolak sampai pembayaran dibalik lebih dulu |
| D03 | Stok sudah terjual sebagian (stok < qty GRN) | Tolak reversal; koreksi lewat retur pembelian |
| D04 | Reversal parsial (sebagian qty GRN) | V1: hanya reversal penuh satu GRN |
| D05 | Siapa boleh reversal | Permission baru `grn.reverse` diberikan eksplisit (tidak otomatis ke ADMIN) |
| D06 | Alasan wajib? | Ya, teks alasan + user + waktu tersimpan |
| D07 | Legacy `voidReceipt` | Dibiarkan (sudah aman dari collision oleh Fix 1); desain reversal legacy terpisah |

## 3. Rencana uji (SQLite + MySQL konkurensi)

1. GRN 40 lalu 60 pada PO 100; reversal GRN 1 → PO `PARTIAL`, stok 60, jurnal net = GRN 2 saja.
2. Reversal dua kali (retry sama) → hasil sama, tidak ada jurnal kedua.
3. Reversal paralel dua proses → satu berhasil, satu ditolak/idempoten.
4. Reversal saat stok sudah terjual → ditolak, tanpa efek samping.
5. Bill sudah dibayar → ditolak.
6. Kegagalan di tengah transaksi → rollback penuh (stok, jurnal, status utuh).
7. Jurnal pembalik seimbang dan memakai akun GRN (114001/211001) persis kebalikan jurnal asal.
8. Rekonsiliasi: Σ ledger = stok produk; Σ jurnal akun persediaan = nilai ledger.
9. Regresi: 161 tes + 20 MySQL tetap lulus.

## 4. Urutan kerja (audit-then-fix, satu langkah satu uji)

1. Jawab D00 (COA) → keputusan tertulis.
2. Fix 2 (baca A3 dulu): `description` jurnal, default `legacy_sync_enabled`, `.env.example`.
3. Jawab D01–D07.
4. Migration additive (nullable, tanpa backfill) → uji → reversal service → controller/route/permission → UI.
