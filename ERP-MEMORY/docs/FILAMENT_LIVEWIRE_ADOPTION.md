# FILAMENT_LIVEWIRE_ADOPTION.md — Panduan Adopsi Filament + Livewire

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Prompt_Cline_Integrasi_Filament_Livewire_20261027.txt` (27 Oktober 2026)

---

## 1. PILIHAN ARSITEKTUR

- Laravel existing + Filament Panel Builder + Livewire versi kompatibel
- Filament 5 / Livewire 4 adalah kandidat
- **Filament adalah adapter presentasi** — Domain, PostingService, GRN, policy dan master existing tetap otoritas
- Struktur: Filament Resource/Page/Action atau HTTP Controller → application use case → domain + authorization + persistence transaction → event/outbox → PostingService
- Livewire action bukan bypass keamanan (berjalan pada server). Setiap mutation memverifikasi actor ERP, company binding, role, resource, state/version, nominal dan invariant pada jalur use case yang sama.

---

## 2. PERSIAPAN DEPENDENSI DAN PANEL

| Langkah | Detail |
|---------|--------|
| Baca checkpoint, AGENTS.md dan status Git aktual | Pertahankan perubahan pengguna dan penghapusan lama template_saldo_awal.csv |
| Periksa composer.json/lock, package.json/lock | PHP CLI vs web, BCMath dan extension yang diperlukan |
| Dry-run dependency resolution sebelum instalasi | Review diff lockfile. Jangan `composer update` global |
| Install Filament/Livewire pada workspace pengembangan | Jangan mengubah database operasional atau `.env` pengguna |
| Verifikasi bootstrap/package discovery | Tidak memerlukan DB produksi |
| Buat panel terpisah di path yang tidak berbenturan | Usulan `/erp-panel` dengan ID `erp` |
| Verifikasi route aktual sebelum menetapkan path | UI existing tetap dapat diakses |
| Tambahkan flag config `PLATFORM_FILAMENT_ENABLED` | Default `false`, entri di `.env.example` |
| Flag membatasi GET panel dan update/upload/export Livewire | Cukup menyembunyikan menu TIDAK CUKUP |
| Uji flag on pada dev/test | |

---

## 3. AUTH DAN AUTHORIZATION

| Aturan | Detail |
|--------|--------|
| Reuse web/session guard, user dan company membership ERP | Tidak membuat user admin tambahan |
| Tidak membuat auth database, role atau tenant master paralel | |
| Jangan mengandalkan akses permisif local environment | Atau gelar ADMIN sebagai izin workflow |
| Implementasikan `canAccessPanel` atau adapter setara | Ditambah policy aksi |
| Verifikasi membership/active status setiap request reaktif | Dan saat mutasi, termasuk setelah user dinonaktifkan |
| Reuse permission Platform | Tidak memasang plugin RBAC lain atau wildcard permission |
| Batasi hak panel, master, laporan dan aksi finansial secara terpisah | |

---

## 4. ISOLASI ASSET

| Aturan | Detail |
|--------|--------|
| Isolasi CSS/JS Filament/Tailwind pada panel | Layout ERP lama memakai Bootstrap |
| Jangan menambahkan dua framework CSS ke semua halaman | |
| Hindari Alpine ganda dan handler dobel | |
| Jangan menjalankan scaffold yang menimpa layout existing | |

---

## 5. DASHBOARD

| Aturan | Detail |
|--------|--------|
| Tampilkan ringkasan dengan sumber/query nyata | Status pengajuan, tugas actor, jatuh tempo, dokumen bermasalah dan tautan sumber |
| Jangan menciptakan saldo, data demo atau nilai nol | Sumber belum terhubung = status belum tersedia |
| Pisahkan IDR/USD, bank/petty, planned/actual | Historical/operational |
| Angka dan drill-down menggunakan scope/filter/permission yang sama | |
| Cache scoped dan berumur jelas | Otorisasi serta dana tersedia selalu diperiksa ulang saat aksi |

---

## 6. MASTER PERTAMA

| Aturan | Detail |
|--------|--------|
| Pilih satu master nonfinansial existing yang aman | Misalnya unit atau divisi |
| Jangan membuat master duplikat hanya untuk demo | |
| Bangun satu irisan lengkap: list/detail/create/update | Validation, authorization, service calls, lookup dan error handling |
| Delete hanya jika policy/domain mengizinkan | Dan tidak merusak referensi |
| Disable bukan pengganti guard server | |

---

## 7. RESOURCES/TABLES

| Aturan | Detail |
|--------|--------|
| Gunakan pola yang teruji | Material/varian, party supplier/customer, gudang/rak dan unit |
| Daftar PO/GRN/stok/AP/AR | Table/filter/search/pagination/drill-down |
| Reuse query service, relationships dan existing domain types | |
| Jangan mengubah primary key, akun, saldo, precision ukuran atau normal balance | Demi generator Resource |

---

## 8. APPROVAL / ACTIONS

| Aturan | Detail |
|--------|--------|
| Filament Action memanggil application use case yang juga digunakan WorkflowController | |
| Aktor berasal dari sesi terverifikasi | Bukan parameter form |
| `expected_version` dan mutation ID disampaikan secara konsisten | Retry tidak menggandakan keputusan/event |
| Tombol tanpa implementasi backend aman | Ditampilkan disabled dengan alasan atau belum ditampilkan |
| Jangan mengubah state melalui `model->update()` | |
| Bulk approval/posting/deletion belum diaktifkan | Sampai semantics per-item, SoD, atomicity dan retry diuji |

---

## 9. AKUNTANSI MATRIKS

| Aturan | Detail |
|--------|--------|
| Pertahankan UI akun → kode bantu → posisi → nominal dan kontrak regression | |
| Panel menyediakan link atau Custom Page yang aman bila dibutuhkan | |
| Jangan mengganti input dengan generic repeater tanpa pembuktian behavior | Keyboard dan precision |
| Seluruh posting memakai `PostingService` yang sama | |

---

## 10. PAYMENT PLAN V33

| Aturan | Detail |
|--------|--------|
| Pertahankan UI vanilla JS v33, flow, form cetak dan kontrak API selama acceptance | |
| Sediakan navigasi dari panel ke halaman Blade dedicated | Menggunakan sesi ERP yang sama |
| Filament dapat menyediakan ringkasan/inbox | Memanggil use case yang sama; jangan membuat workflow kedua |
| Jangan biarkan Livewire melakukan morphing DOM yang juga dikelola workspace.js | |
| Halaman tersendiri adalah default | Bila embed diperlukan, tetapkan ownership DOM dan lifecycle secara eksplisit |
| Tidak perlu membangun ulang 11 menu v33 dengan Filament | |

---

## 11. PRINT / IMPORT / EXPORT

| Aturan | Detail |
|--------|--------|
| Template cetak perusahaan yang sudah ditetapkan tetap dipakai | |
| Tables/Actions Filament tidak otomatis menggantikan importer aman v33 atau ekspor ERP | |
| Reuse parser, limits, staging, preview, atomic commit, role scope dan totals | |
| Jangan menggunakan import CRUD bawaan untuk membuat paid/approved/posted | Atau menulis jurnal langsung |
| Attachment FileUpload tidak boleh melewati private storage | SHA-256, stage permission, size/count limit dan archive policy |

---

## 12. BACKEND BERSAMA PEMBANGUNAN PANEL

| Area | Detail |
|------|--------|
| Accounting idempotency | Source link, immutable posted, linked reversal dan period lock runtime |
| Payment reservation | Lock rekening, cash, invoice/kontrak residual dan top-up sesuai shared invariant |
| Hold/pending/failed tidak otomatis melepas dana | Pelepasan memerlukan bukti no-debit |
| Paid mengonsumsi reservation sekali | |
| Allocation AP/DP/progress/final/retention | Berbasis ID dan residual atomik |
| Jangan mengulang beban/VAT dari invoice yang sudah diposting | |
| Posting memakai persisted stable payment event ID | Canonical hash dan unique link |
| Paid tetap paid saat journal gagal | Posted hanya setelah journal_id valid |
| Crash/retry setelah journal commit sebelum ACK diuji | |
| Lengkapi API yang diperlukan | Request/data/action/settings/report/history/attachment/comments/notifications/transfer |
| Port seluruh 49 referensi UI yang diinventaris | Termasuk helper fetch, binary upload, href download/image/print, sign-in/out dan URL |
| Legacy cutover | Reader lama tersedia, writer tunggal dengan source ownership/migration_complete |

---

## 13. KEAMANAN DAN QUERY

| Aturan | Detail |
|--------|--------|
| Page access, model policy, query scope dan mutation authorization diuji terpisah | Hidden button bukan kontrol |
| Resource record lookup, relation manager, select search, global search, stats, notification, export dan download | Wajib scope yang sama |
| Public properties Livewire, record IDs, role, company ID, price/status, source account dan event ref | Dianggap input tidak tepercaya |
| Server mengambil ulang record dan authoritative totals dalam transaksi | |
| Setup guard/rate limit/session timeout/CSRF berlaku pada API, Livewire update dan upload | |
| Role/payment permission ditentukan policy existing | |
| Error 401/403/409/419/422/429 dapat dipahami UI | Tidak menyimpan state palsu |
| Parameter sorting/filter dan expression tidak boleh raw SQL dari input | |
| Gunakan server pagination, eager loading dan query terukur | Chart total tidak menjumlah currencies berbeda |
| Tiga bahasa ID/EN/ZH | Label/navigation sesuai MenuRegistry yang ada, bukan menu duplikat |

---

## 14. ACCEPTANCE YANG WAJIB DIBUKTIKAN

| No | Kriteria |
|----|----------|
| 1 | Dependency resolver, composer validate, build asset dan PHP syntax benar |
| 2 | Panel off menutup akses termasuk reactive actions; panel on dengan session ERP, active member dan permission sesuai role |
| 3 | Feature/Livewire tests: unauthorized mount/action, ID tampering, company asing, role dicabut setelah mount, stale version, retry, relation lookup dan export tidak bocor |
| 4 | Satu master irisan lengkap memanggil service dan audit yang benar; posted data tidak dapat diubah lewat Resource/bulk/inline mutation |
| 5 | Dashboard stats sama dengan query sumber/drill-down dan membedakan currency, route, historical serta transaksi aktual |
| 6 | Golden PHP/Node 66 tetap lulus termasuk full projection |
| 7 | Browser desktop/mobile 360/390/768: panel, filter/modal, keyboard/fokus, login/expiry, layout Bootstrap lama, v33 Blade, attachment, multi-page print, tiga bahasa; tidak Alpine ganda atau duplicate submission |
| 8 | MySQL dua proses/transaksi untuk reservation dana/qty/AP, duplicate post, deadlock/retry; SQLite tidak menggantikannya |
# FILAMENT_LIVEWIRE_ADOPTION.md — Panduan Adopsi Filament + Livewire

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Prompt_Cline_Integrasi_Filament_Livewire_20261027.txt` (27 Oktober 2026)

---

## 1. PILIHAN ARSITEKTUR

Filament 5 / Livewire 4 menjadi kandidat sesuai dokumentasi saat ini. Cline wajib mengecek PHP/extensions, composer.lock, Laravel, Tailwind dan dependency resolution lokal sebelum memilih versi final.

**Aturan utama:**
- Jangan menurunkan Laravel atau mengubah backend agar cocok dengan komponen UI
- Jika ada konflik dependensi, diagnosis lalu pilih jalur kompatibel yang didukung dan catat alasan
- Backend independen tetap dilanjutkan
- Filament adalah adapter presentasi, bukan pengganti domain/backend

**Struktur alur:**
```
Filament Resource/Page/Action atau HTTP Controller
  → application use case
  → domain + authorization + persistence transaction
  → event/outbox
  → PostingService
```

Livewire action bukan bypass keamanan karena berjalan pada server. Setiap mutation memverifikasi actor ERP, company binding, role, resource, state/version, nominal dan invariant pada jalur use case yang sama.

---

## 2. PERSIAPAN DEPENDENSI DAN PANEL

### 2.1 Dependency
- Baca checkpoint, AGENTS.md dan status Git aktual
- Pertahankan perubahan pengguna/B01 dan penghapusan lama `template_saldo_awal.csv`
- Jangan reset/clean atau rewrite file unrelated
- Periksa `composer.json`/`lock`, `package.json`/`lock`, PHP CLI vs web, BCMath dan extension yang diperlukan
- Gunakan dokumentasi resmi versi yang benar dan dry-run dependency resolution sebelum instalasi terarah
- Review diff lockfile
- Jangan menjalankan `composer update` global atau `composer run setup`

### 2.2 Instalasi
- Install Filament/Livewire pada workspace pengembangan yang telah ditetapkan
- Jangan mengubah database operasional atau `.env` pengguna
- Verifikasi bootstrap/package discovery tidak memerlukan DB produksi

### 2.3 Panel
- Buat panel terpisah pada path yang tidak berbenturan, usulan `/erp-panel` dengan ID `erp`
- Verifikasi route aktual sebelum menetapkan path
- UI existing tetap dapat diakses
- Tambahkan flag config untuk panel, usulan `PLATFORM_FILAMENT_ENABLED` default `false` dan entri `.env.example`
- Flag membatasi GET panel dan update/upload/export Livewire terkait
- Tidak cukup hanya menyembunyikan menu
- Uji flag on pada dev/test

### 2.4 Auth dan Company Scope
- Reuse web/session guard, user dan company membership ERP
- Tidak membuat user admin tambahan, auth database, role atau tenant master paralel
- Jangan mengandalkan akses permisif local environment atau gelar ADMIN sebagai izin workflow
- Implementasikan `canAccessPanel` atau adapter setara sesuai versi, ditambah policy aksi
- Verifikasi membership/active status pada setiap request reaktif dan saat mutasi, termasuk setelah user dinonaktifkan saat panel masih terbuka
- Reuse permission Platform, tidak memasang plugin RBAC lain atau membuat wildcard permission
- Batasi hak panel, master, laporan dan aksi finansial secara terpisah

### 2.5 Asset Isolation
- Isolasi CSS/JS Filament/Tailwind pada panel
- Layout ERP lama memakai Bootstrap; jangan menambahkan dua framework CSS ke semua halaman
- Hindari Alpine ganda dan handler dobel
- Jangan menjalankan scaffold yang menimpa layout existing

---

## 3. PENERAPAN UI DAN PEMBAGIAN MODUL

### 3.1 Dashboard Filament
- Tampilkan ringkasan yang sudah memiliki sumber/query nyata: status pengajuan, tugas actor, jatuh tempo, dokumen bermasalah dan tautan sumber
- Jangan menciptakan saldo, data demo atau nilai nol yang menyiratkan sumber sudah tersedia
- Sumber belum terhubung diberi status "belum tersedia"
- Pisahkan IDR/USD, bank/petty, planned/actual serta historical/operational
- Angka dan drill-down menggunakan scope/filter/permission yang sama
- Cache scoped dan berumur jelas; otorisasi serta dana tersedia selalu diperiksa ulang saat aksi

### 3.2 Master Pertama
- Pilih satu master nonfinansial existing yang aman setelah discovery singkat (misalnya unit atau divisi)
- Jangan membuat master duplikat hanya untuk demo
- Bangun satu irisan lengkap: list/detail/create/update dengan validation, authorization, service calls, lookup dan error handling
- Bila unit belum ada, kerjakan prasyarat master yang memang tercantum dalam rencana lalu gunakan model tersebut
- Delete hanya jika policy/domain mengizinkan dan tidak merusak referensi; disable bukan pengganti guard server

### 3.3 Resources / Tables
- Gunakan pola yang teruji untuk material/varian, party supplier/customer, gudang/rak dan unit
- Daftar PO/GRN/stok/AP/AR memakai table/filter/search/pagination/drill-down
- Reuse query service, relationships dan existing domain types
- Jangan mengubah primary key, akun, saldo, precision ukuran atau normal balance demi generator Resource

### 3.4 Approval / Actions
- Filament Action memanggil application use case yang juga digunakan WorkflowController
- Aktor berasal dari sesi terverifikasi, bukan parameter form
- `expected_version` dan mutation ID disampaikan secara konsisten; retry tidak menggandakan keputusan/event
- Tombol yang belum memiliki implementasi backend aman ditampilkan disabled dengan alasan atau belum ditampilkan
- Jangan mengubah state langsung melalui `model->update()`
- Bulk approval/posting/deletion tidak diaktifkan sampai semantics per-item, SoD, atomicity dan retry diuji

### 3.5 Akuntansi Matriks
- Pertahankan UI akun → kode bantu → posisi → nominal dan kontrak regression
- Panel menyediakan link atau Custom Page yang aman bila dibutuhkan
- Jangan mengganti input tersebut dengan generic repeater tanpa pembuktian behavior, keyboard dan precision
- Seluruh posting memakai PostingService yang sama

### 3.6 Payment Plan v33
- Pertahankan UI vanilla JS v33, flow, form cetak dan kontrak API selama acceptance
- Sediakan navigasi dari panel ke halaman Blade dedicated menggunakan sesi ERP yang sama
- Filament dapat menyediakan ringkasan/inbox yang memanggil use case yang sama; jangan membuat workflow kedua
- Jangan biarkan Livewire melakukan morphing DOM yang juga dikelola workspace.js
- Halaman tersendiri adalah default; bila embed diperlukan, tetapkan ownership DOM dan lifecycle secara eksplisit dan uji navigasi/mount/unmount
- Tidak perlu membangun ulang 11 menu v33 dengan Filament untuk memenuhi adopsi ini

### 3.7 Print / Import / Export
- Template cetak perusahaan yang sudah ditetapkan tetap dipakai
- Tables/Actions Filament tidak otomatis menggantikan importer aman v33 atau ekspor ERP
- Reuse parser, limits, staging, preview, atomic commit, role scope dan totals
- Jangan menggunakan import CRUD bawaan untuk membuat paid/approved/posted atau menulis jurnal langsung
- Attachment FileUpload tidak boleh melewati private storage, SHA-256, stage permission, size/count limit dan archive policy

---

## 4. BACKEND BERSAMA PEMBANGUNAN PANEL

Tidak berhenti pada dashboard dan satu Resource. Setelah irisan pertama lolos test relevan, lanjutkan cakupan integrasi:

- Accounting idempotency, source link, immutable posted, linked reversal dan period lock runtime
- Payment reservation: lock rekening, cash, invoice/kontrak residual dan top-up sesuai shared invariant dengan urutan global konsisten. Hold/pending/failed tidak otomatis melepas dana. Pelepasan memerlukan bukti no-debit; paid mengonsumsi reservation sekali
- Allocation AP/DP/progress/final/retention berbasis ID dan residual atomik. Jangan mengulang beban/VAT dari invoice yang sudah diposting
- Posting memakai persisted stable payment event ID, canonical hash dan unique link. paid tetap paid saat journal gagal; posted hanya setelah journal_id valid
- Lengkapi request/data/action/settings/report/history/attachment/comments/notifications/transfer API yang diperlukan, privat storage dan audit
- Port seluruh 49 referensi UI yang diinventaris, termasuk helper fetch, binary upload, href download/image/print, sign-in/out dan URL yang dibangkitkan server
- Legacy cutover: reader lama tersedia, writer tunggal dengan source ownership/migration_complete. Paid belum posted tetap kewajiban rekonsiliasi, bukan hilang dalam arsip
- Purchasing/GRN/QC/invoice/AP, inventory/opname/transfer, BOM/MRP/WO/routing, sales/AR/receipt, subkontrak/mekanik/scrap, EXIM/import/reporting sesuai mapping
- Gunakan Filament untuk layar standar baru; Livewire custom bila interaksi khusus memerlukannya
- Semua tetap menggunakan service operasional ERP

---

## 5. KEAMANAN DAN QUERY FILAMENT/LIVEWIRE

- Page access, model policy, query scope dan mutation authorization diuji terpisah
- Hidden button bukan kontrol
- Resource record lookup, relation manager, select search, global search, stats, notification, export dan download wajib scope yang sama
- Public properties Livewire, record IDs, role, company ID, price/status, source account dan event ref dianggap input tidak tepercaya
- Server mengambil ulang record dan authoritative totals dalam transaksi
- Setup guard/rate limit/session timeout/CSRF berlaku pada API, Livewire update dan upload
- Role/payment permission ditentukan policy existing
- Pastikan error 401/403/409/419/422/429 dapat dipahami UI dan tidak menyimpan state palsu
- Parameter sorting/filter dan expression tidak boleh menjadi raw SQL dari input
- Gunakan server pagination, eager loading dan query terukur
- Chart total tidak menjumlah currencies berbeda
- Tiga bahasa ID/EN/ZH, label/navigation sesuai MenuRegistry yang ada, bukan menu duplikat
- Tambahkan `presentation_driver`, `route`, `implementation_status`, `policy` dan `evidence` pada menu-map tanpa menimpa bukti yang lebih baru

---

## 6. ACCEPTANCE YANG WAJIB DIBUKTIKAN

| No | Kriteria |
|----|----------|
| 1 | Dependency resolver, composer validate, build asset dan PHP syntax benar; lockfile version terpilih dicatat |
| 2 | Panel off menutup akses termasuk reactive actions; panel on dengan session ERP, active member dan permission sesuai role |
| 3 | Feature/Livewire tests: unauthorized mount/action, ID tampering, company asing, role dicabut setelah mount, stale version, retry, relation lookup dan export tidak bocor |
| 4 | Satu master irisan lengkap memanggil service dan audit yang benar; posted data tidak dapat diubah lewat Resource/bulk/inline mutation |
| 5 | Dashboard stats sama dengan query sumber/drill-down dan membedakan currency, route, historical serta transaksi aktual |
| 6 | Golden PHP/Node 66 tetap lulus termasuk full projection; hardened tests terpisah dan service/HTTP nyata lulus |
| 7 | Browser desktop/mobile 360/390/768: panel, filter/modal, keyboard/fokus, login/expiry, layout Bootstrap lama, v33 Blade, attachment, multi-page print, tiga bahasa; tidak Alpine ganda atau duplicate submission |
| 8 | MySQL dua proses/transaksi untuk reservation dana/qty/AP, duplicate post, deadlock/retry; SQLite tidak menggantikannya |
| 9 | Rekonsiliasi AP/AR/stock/cash/GL dan restore DB/private objects staging diuji |
| 10 | Jalankan tests relevan per patch; full suite setelah perubahan dependency/core dan sebelum laporan gabungan |

---

## 7. CARA KERJA DAN BATAS

- Lanjutkan pada branch lokal aktual tanpa meminta konfirmasi setiap tahap
- Jangan ulang instalasi bila sudah ada
- Jangan deploy/merge/push atau commit otomatis; ikuti otorisasi Git repository
- Jangan memakai `migrate:fresh`/`reset`/`drop`/`truncate` pada database pengguna
- Jangan mengubah `.env`/mandat/secret/master produksi, atau menyentuh file unrelated
- Migration dev/test additive dan isolated
- Mapping COA belum disahkan membatasi aktivasi terkait; resolver/fixture tetap dapat dikembangkan
- Default policy rekening sesuai referensi v33 sampai mandat baru sah
- Blocker ditetapkan per pekerjaan dengan bukti, bukan alasan menghentikan seluruh scope
- Jika versi Filament tidak dapat dipasang karena lingkungan, simpan hasil diagnosis dan lanjutkan backend independen; jangan mengklaim Filament terpasang
- Feature flag default off pada produksi, on pada acceptance staging
- Jangan memakai fixture sebagai data produksi

---

## 8. DOKUMENTASI DAN HASIL AKHIR

Perbarui:
- `execution-checkpoint.md`
- `test-evidence.md`
- `merged-v33-method.md`
- `decision-log.md`
- `rollback.md`
- `menu-map`

Tambah `docs/integration/filament-livewire-adoption.md` berisi:
- Version, auth/policy adapters, panel route/flag
- Resource-service mapping, asset isolation, UI coverage dan evidence

Laporan akhir satu gabungan: fitur dan route nyata, file/migration berubah, version dependencies, full test results, browser/MySQL/restore evidence, status seluruh requirement/menu dan blockers aktivasi. Pisahkan implemented dan acceptance-verified. Checkpoint bukan akhir pekerjaan.