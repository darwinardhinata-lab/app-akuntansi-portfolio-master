# CUTOVER_ROLLBACK.md — UAT, Migrasi, Cutover dan Rollback

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (bab 21-24)

---

## 1. CRITERIA GO / NO-GO

| No | Kriteria | Bukti yang Diperlukan |
|----|----------|----------------------|
| 1 | Seluruh P0 ditutup | P0 checklist |
| 2 | UAT kritis lulus | UAT sign-off |
| 3 | Restore DB+blob terbukti | Hasil restore staging |
| 4 | Saldo dan quantities direkonsiliasi | Laporan rekonsiliasi |
| 5 | Rekening sumber disahkan | Mandat rekening |
| 6 | Personel role sudah ditugaskan | Daftar PIC |

---

## 2. MIGRATION RUNBOOK

| Fase | Langkah | Detail |
|------|---------|--------|
| 1 | Inventaris DB/blob | Source counts, SHA-256 dan cut-off |
| 2 | Migrate raw → crosswalk → validated → target | Accepted/quarantine/excluded counters |
| 3 | Pindahkan open transactions dan histories | Secara eksplisit; historical completed tidak menjadi request operasional |
| 4 | Jalankan semua test A/P/O/S/U | Simpan evidence aktual |
| 5 | Verifikasi zero unexplained differences | Count/value/qty differences |
| 6 | Rekonsiliasi AP/AR/stock/GL | Semua direkonsiliasi |
| 7 | Attachment hashes cocok | SHA-256 verifikasi |

**Aturan:** Jangan memakai fixture sebagai data produksi. ZIP source bukan database/attachment backup.

---

## 3. CUTOVER PAKET

| Komponen | Detail |
|----------|--------|
| Release notes | Daftar fitur dan route nyata |
| Migration order | Urutan migrasi tabel |
| Feature flags | Per modul, default off sampai gate lulus |
| Smoke commands | Perintah verifikasi setelah deploy |
| Deployment checklist | Checklist lengkap |
| Backup evidence | Bukti backup sebelum cutover |
| Monitoring | Monitoring post-deploy |
| Rollback decision tree | Pohon keputusan rollback |

**Urutan cutover:** freeze → backup → deploy backward-compatible → delta migration → reconcile → canary → monitor

---

## 4. ROLLBACK

| Aturan | Detail |
|--------|--------|
| Rollback tidak boleh restore lama tanpa replay/reconciliation delta | Setelah ada transaksi baru |
| Sajikan diff dan instruksi konkret | Untuk persetujuan operasional |
| Jangan mengubah server produksi | Tanpa otorisasi |
| Jangan menjalankan migrate:fresh/reset/drop/truncate | Pada database pengguna |
| Jangan mengubah .env/mandat/secret/master produksi | |

---

## 5. QUERY READ-ONLY UNTUK MONITORING

| Query | Tujuan |
|-------|--------|
| Journal duplicate | Deteksi jurnal ganda |
| Outbox backlog | Deteksi antrean outbox |
| Unlinked paid | Pembayaran tanpa journal link |
| Reserved funds | Dana yang masih ter-reservasi |
| Negative stock | Stok negatif |
| Orphan blob | Attachment tanpa referensi |
| Report mismatch | Laporan tidak cocok dengan sumber |

---

## 6. FILE TERKAIT

- `docs/TEST_EVIDENCE.md` — Bukti pengujian
- `docs/BUG_LOG.md` — Bug dan blocker
- `docs/GIT_WORKFLOW.md` — Alur Git dan konvensi commit
# CUTOVER_ROLLBACK.md — UAT, Migrasi, Cutover dan Rollback

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (bab 21–24, Lampiran C)

---

## 1. UAT (USER ACCEPTANCE TESTING)

### 1.1 Tujuan
Siapkan migration runbook reproduktif dan jalankan dry run staging dari backup yang diotorisasi. ZIP source bukan database/attachment backup. Jangan memakai fixture sebagai data produksi.

### 1.2 Implementasi UAT
| No | Aktivitas | Detail |
|----|-----------|--------|
| 1 | Inventaris DB/blob | Source counts, SHA-256 dan cut-off |
| 2 | Migrasi data | Migrate raw → crosswalk → validated → target dengan accepted/quarantine/excluded counters |
| 3 | Pindahkan transaksi | Open transactions dan histories secara eksplisit; historical completed tidak menjadi request operasional |
| 4 | Jalankan test | Semua test A/P/O/S/U dalam laporan dan simpan evidence aktual |
| 5 | Rekonsiliasi | AP/AR/stock/GL direkonsiliasi; attachment hashes cocok |

### 1.3 Gate UAT
| No | Syarat | Bukti |
|----|--------|-------|
| 1 | Zero unexplained count/value/qty differences | Laporan perbandingan sebelum/sesudah |
| 2 | AP/AR/stock/GL reconciled | Rekonsiliasi report |
| 3 | Attachment hashes cocok | SHA-256 verification |
| 4 | Role nyata UAT disahkan | Tanda tangan UAT operasional, UX dan keamanan |
| 5 | Restore DB/blob berhasil | Hasil dry run restore staging |

**Jika ditemukan blocker, laporkan spesifik. Jangan menghapus data untuk membuat test lolos.**

---

## 2. MIGRASI

### 2.1 Urutan Migrasi
1. Freeze source data
2. Backup DB dan blob
3. Deploy backward-compatible changes
4. Delta migration (additive only)
5. Reconcile counts and values
6. Canary testing
7. Monitor

### 2.2 Aturan Migrasi
- Semua migration additive dan backward-compatible
- Jangan menjalankan `migrate:fresh`/`reset`/`drop`/`truncate` pada database pengguna
- Historical completed tidak menjadi request operasional
- Open transactions dipindahkan secara eksplisit

---

## 3. CUTOVER (PAKET FINAL AKTIVASI)

### 3.1 Checklist Cutover
| No | Item | Status |
|----|------|--------|
| 1 | Release notes | |
| 2 | Migration order | |
| 3 | Feature flags (default off) | |
| 4 | Smoke commands | |
| 5 | Deployment checklist | |
| 6 | Backup evidence | |
| 7 | Monitoring | |
| 8 | Rollback decision tree | |

### 3.2 Query Read-Only untuk Verifikasi
Setelah cutover, jalankan query berikut untuk verifikasi:
- Journal duplicate
- Outbox backlog
- Unlinked paid
- Reserved funds
- Negative stock
- Orphan blob
- Report mismatch

### 3.3 PIC dan Stop Criteria
- Tetapkan PIC untuk setiap fase
- Tetapkan stop criteria
- Jangan invent SLA yang belum disepakati

---

## 4. ROLLBACK

### 4.1 Rollback Decision Tree
```
Apakah ada transaksi baru setelah cutover?
├── YA → Rollback tanpa replay/reconciliation delta DILARANG
│   └── Gunakan forward fix atau compensating entry
└── TIDAK → Rollback dapat dilakukan dengan restore backup
```

### 4.2 Aturan Rollback
- Setelah ada transaksi baru, rollback tidak boleh restore lama tanpa replay/reconciliation delta
- Sajikan diff dan instruksi konkret untuk persetujuan operasional
- Jangan mengubah server produksi tanpa otorisasi

### 4.3 Kondisi Rollback
Stop dan rollback jika:
- Ada selisih tak terjelaskan antara count/nilai/qty sebelum dan sesudah migrasi
- AP/AR/stock/GL tidak direkonsiliasi
- Attachment hashes tidak cocok
- Role nyata UAT belum disahkan
- Restore DB/blob gagal

---

## 5. GO/NO-GO CRITERIA

Go ke production hanya jika seluruh syarat berikut terpenuhi:

| No | Syarat | Bukti yang Diperlukan |
|----|--------|----------------------|
| 1 | Seluruh P0 ditutup | Dokumen penutupan P0 |
| 2 | UAT kritis lulus | Tanda tangan UAT |
| 3 | Restore DB+blob terbukti | Hasil dry run restore staging |
| 4 | Saldo dan quantities direkonsiliasi | Laporan rekonsiliasi |
| 5 | Rekening sumber disahkan | Keputusan mandat rekening (IDR 3 / USD 1) |
| 6 | Personel role sudah ditugaskan | Daftar PIC dan role operasional |
| 7 | Rollback decision tree tersedia | `docs/CUTOVER_ROLLBACK.md` diisi lengkap |

**Catatan:** Checkpoint bukan akhir pekerjaan. Laporan akhir harus memisahkan implemented dan acceptance-verified.