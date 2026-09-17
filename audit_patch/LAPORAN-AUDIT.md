# Laporan Audit — Kesesuaian Flowchart Produksi PT MGI vs `app-akuntansi`
**Tanggal:** 16 September 2026
**Sumber:** `FLOWCHART_Produksi_PT__MGI-3.pdf` (12 halaman) vs snapshot kode `app-akuntansi-portfolio_16-9-26_3.txt` (532 file)
**Ruang lingkup investigasi lanjutan:** Modul `app/Modules/Customs` (CEISA H2H) — dibedah baris per baris setelah ditemukan sebagai titik friksi terbesar terhadap flowchart.

---

## 1. Ringkasan Eksekutif

Perbandingan awal menyimpulkan **struktur, logika, dan alur kerja kode TIDAK SAMA** dengan flowchart. Investigasi lanjutan ke modul Customs (dipicu karena modul inilah yang paling relevan dengan halaman 1, 4, 5, 6, 11 flowchart — semua terkait proses kepabeanan Kawasan Berikat) menemukan bahwa modul tersebut **bukan sekadar tidak lengkap secara cakupan, tapi juga rusak secara teknis (tidak bisa jalan sama sekali di luar `createDraft()`)**. Total ditemukan **11 temuan**, 6 di antaranya berstatus **CRITICAL / blocker fatal**.

| Severity | Jumlah |
|---|---|
| CRITICAL | 6 |
| HIGH | 3 |
| MEDIUM | 2 |

---

## 2. Temuan Level Struktur Bisnis (Manufacturing Module)

### T1 — Model bisnis produksi tidak sama dengan flowchart (CRITICAL, di luar cakupan perbaikan kode)
Flowchart = CMT (Cut-Make-Trim): bahan baku kain **dibeli jadi**, dijahit oleh **staf internal** (Leader/QC/Operator/Mekanik perusahaan), lalu dikemas & diekspor. Tidak ada tahap rajut/dyeing.

Kode aktual (`app/Modules/Manufacturing`) memodelkan rantai:
```
Yarn → KnitOrder → GreyFabricReceipt → ProcessingOrder(dyeing/finishing, ke Supplier)
→ FabricReceipt → CuttingOrder → StitchingOrder(SUBKONTRAK ke Supplier/"maklun") → FinishingStage
```
`StitchingOrder` memiliki `supplier_id`, `stitching_rate`, `total_stitching_cost` — menjahit dimodelkan sebagai **pekerjaan yang dimaklunkan ke pihak luar**, bukan proses internal seperti di flowchart.

**Ini bukan bug kode** — ini pertanyaan model bisnis yang harus dijawab oleh pemilik proses sebelum kode "diperbaiki", karena memperbaikinya berarti me-redesign ulang seluruh modul Manufacturing (akun WIP, hutang maklun, dsb). **Direkomendasikan TIDAK disentuh pada iterasi perbaikan ini** — perlu keputusan bisnis eksplisit. Detail lengkap ada di Lampiran A.

### T2 — SOP Jarum Patah & SOP Aval/Scrap tidak punya modul pendukung (MEDIUM, di luar cakupan)
Tidak ditemukan entitas untuk broken-needle tracking maupun kategorisasi scrap/aval. Hanya ada label laporan `mutation_reject` di `lang/*/customs.php`. Perlu modul baru — dijadwalkan sebagai fase terpisah.

---

## 3. Temuan Level Teknis — Modul Customs (CEISA H2H)

Semua temuan di bawah **diperbaiki pada Tahap B** laporan ini (lihat §5).

### T3 — Bug fatal: urutan parameter TIDAK COCOK antara Controller dan Service (CRITICAL)
```php
// Controller memanggil:
createDraft($sourceConfig['type'], $sourceType, $sourceId, auth()->id());
// Service mendefinisikan:
createDraft(string $sourceType, int $sourceId, string $documentType, ?int $createdBy)
```
**Root cause:** parameter tertukar posisi. **Dampak:** `TypeError` setiap kali user membuat dokumen PIB/PEB dari PO/PI/SO/Invoice — fitur 100% tidak bisa dipakai.

### T4 — Konvensi `source_type` tidak konsisten antara Controller dan Model/Test (CRITICAL)
`CustomsDocument::source()` dan test suite (`CustomsDocumentFoundationTest`) mengharapkan `source_type` berisi **nama tabel jamak** (`'purchase_orders'`), tapi `CustomsDocumentController::create()` mengirim nilai **tunggal** dari key route (`'purchase_order'`). Bahkan setelah T3 diperbaiki, resolusi dokumen sumber via `source()` akan **selalu gagal** (`null`) karena `'purchase_order' !== 'purchase_orders'`.

### T5 — 5 dari 6 method inti `CustomsDocumentService` TIDAK ADA (CRITICAL — paling parah)
Controller & Job memanggil method berikut yang **tidak pernah didefinisikan** di `CustomsDocumentService`:
- `submit()` — dipanggil dari `CustomsDocumentController::submit()`
- `retry()` — dipanggil dari `CustomsDocumentController::retry()`
- `void()` — dipanggil dari `CustomsDocumentController::void()`
- `updateFromPayload()` — dipanggil dari `CustomsDocumentController::update()`
- `updateStatusFromResponse()` — dipanggil dari `SubmitCustomsDocumentJob::handle()`

**Dampak:** `Error: Call to undefined method` (fatal PHP Error, bukan exception biasa) di setiap tombol Submit/Retry/Void/Update. Modul hanya bisa **membuat draft**, sisanya lumpuh total.

### T6 — Method model yang dipanggil tapi tidak ada (CRITICAL)
- `$document->canBeSubmitted()` dipanggil di `SubmitCustomsDocumentJob`, tidak ada di `CustomsDocument`.
- `CustomsDocument::submitted()` (local scope) dipanggil di `PollCustomsStatusJob`, tidak ada di model.
**Dampak:** fatal error yang sama seperti T5, terjadi begitu queue worker memproses job.

### T7 — Property belum diinisialisasi diakses di `SubmitCustomsDocumentJob` (CRITICAL)
```php
private string $correlationId;   // dideklarasikan, TIDAK PERNAH di-assign
...
CustomsAuditLogger::logInboundResponse(..., $this->correlationId, ...); // diakses di sini
```
`generateCorrelationId()` dipanggil dan hasilnya langsung dilempar sebagai argumen fungsi lain — **tidak pernah disimpan ke `$this->correlationId`**. PHP 8 typed property yang diakses sebelum diinisialisasi akan melempar `Error: Typed property must not be accessed before initialization`.

### T8 — `CustomsAuditLogger` dipanggil dengan nama method yang tidak ada (CRITICAL)
`SubmitCustomsDocumentJob` memanggil `CustomsAuditLogger::logOutboundRequest()` dan `::logInboundResponse()` — method yang benar-benar ada di class tersebut bernama `logRequest()` dan `logResponse()`. Undefined static method call → fatal error.

### T9 — Submit & cek status ke CEISA adalah data palsu, client asli menganggur (HIGH)
`SubmitCustomsDocumentJob::callCeisaApi()` **selalu** mengembalikan `SUBMIT_SUCCESS` + nomor AJU acak (`uniqid()`), tanpa pernah memanggil `CeisaH2HClient` yang sudah dibangun lengkap (HTTP client + HMAC signer, dengan guard `customs.enabled` yang sengaja dibuat aman). `PollCustomsStatusJob::checkStatus()` hanya `Log::info()` — tidak pernah memanggil `CeisaH2HClient::checkStatus()`. Ini membuat seluruh proses "Submit ke Petugas BC" dan "Cek Status di Portal CEISA" pada flowchart halaman 5 **tidak pernah benar-benar terjadi**.

### T10 — `config('customs.signing.api_secret')` tidak terhubung ke `.env` (HIGH)
`CeisaSignatureService` membaca `config('customs.signing.api_secret')`, tapi `config/customs.php` tidak pernah mendefinisikan key ini (hanya `signing.method`). Di lingkungan nyata (bukan test, yang men-set config secara manual), nilai ini akan **selalu `null`** → signing selalu gagal dengan pesan "CEISA_API_SECRET tidak diatur" meskipun sudah diisi di `.env`.

### T11 — `PollCustomsStatusJob` tidak pernah dijadwalkan (HIGH)
`routes/console.php` menjadwalkan 6 job sinkronisasi dashboard lain via `Schedule::job(...)->hourlyAt(30)`, tapi **tidak ada satupun entry untuk `PollCustomsStatusJob`** meskipun config `customs.polling_interval_minutes` sudah disediakan. Bahkan jika T5–T9 diperbaiki, status dokumen tidak akan pernah diperbarui otomatis karena job-nya tidak pernah dieksekusi.

### Catatan mitigasi yang sudah ada (bukan temuan, tapi perlu dicatat)
`routes/customs.php` membungkus seluruh route (kecuali webhook) dalam `if (config('customs.enabled'))`, dan `config/customs.php` men-default `enabled => false`. Artinya **seluruh bug di atas saat ini tidak dapat diakses lewat web** pada instalasi default — risiko operasional langsung = rendah, tapi modul tetap harus diperbaiki sebelum flag ini dinyalakan.

---

## 4. Cakupan Perbaikan Iterasi Ini

| Temuan | Diperbaiki sekarang? | Keterangan |
|---|---|---|
| T3–T11 (bug teknis Customs) | ✅ Ya | Semua bug blocker & fatal error — aman diperbaiki tanpa menyentuh COA |
| T1 (model bisnis Manufacturing) | ❌ Tidak | Perlu keputusan bisnis, bukan bug kode |
| T2 (SOP jarum patah/scrap) | ❌ Tidak | Modul baru, fase terpisah |
| Penambahan 7 jenis dokumen BC (BC 2.7/2.6.2/4.1/4.7/2.6.1/2.5) | ❌ Tidak | Butuh entitas "dokumen mutasi internal" baru yang belum ada di ERP — diusulkan sebagai fase 2 |
| Laporan mutasi BC (8 laporan, saat ini placeholder kosong) | ❌ Tidak | Butuh spesifikasi query per jenis laporan dari tim — fase 2 |
| Modul NCR (non-conformance) & hold barang | ❌ Tidak | Fitur baru, fase 2 |

Tidak ada perubahan pada modul COA/akuntansi inti — semua fix di bawah murni memperbaiki bug PHP di layer service/job/controller modul Customs, sehingga **tidak memerlukan approval manual COA** sesuai kesepakatan kerja.

---

## 5. Rencana Eksekusi Perbaikan (dieksekusi di §Tahap B chat & lampiran patch)

Urutan perbaikan (setiap langkah diverifikasi dengan `php -l` / cek statis sebelum lanjut ke langkah berikutnya, sesuai pola audit-then-fix):

1. `CustomsDocument.php` — tambah konstanta status + `canBeSubmitted()` + `isEditable()` + `scopeSubmitted()`
2. `CustomsDocumentController.php` — perbaiki T3 + T4 sekaligus (urutan parameter & konvensi `source_type` jamak)
3. `CustomsDocumentService.php` — tambah 5 method yang hilang (T5), termasuk state-machine status
4. `SubmitCustomsDocumentJob.php` — perbaiki T6 (bagian job), T7, T8, T9 (wire ke `CeisaH2HClient` asli)
5. `PollCustomsStatusJob.php` — perbaiki T6 (bagian job), T9 (wire ke `CeisaH2HClient::checkStatus()`)
6. `config/customs.php` — perbaiki T10
7. `routes/console.php` — perbaiki T11

File hasil perbaikan lengkap + diff disertakan di folder `fixes/` sebagai deliverable terpisah dari laporan ini.

---

## 6. Hasil Eksekusi Perbaikan (Tahap B — Selesai Dijalankan)

Semua 7 langkah pada §5 sudah dieksekusi sebagai **diff bertarget** (bukan rewrite penuh), dengan komentar `// FIX (Txx): ...` berbahasa Indonesia di setiap perubahan, dan divalidasi satu-per-satu dengan `php -l` sebelum lanjut ke file berikutnya (PHP 8.3 CLI dipasang khusus untuk validasi ini).

| # | File | Temuan yang diperbaiki | `php -l` |
|---|---|---|---|
| 1 | `app/Modules/Customs/Models/CustomsDocument.php` | T6 (bagian model) — tambah konstanta `STATUS_*`, `canBeSubmitted()`, `isEditable()`, `scopeSubmitted()` | ✅ Bersih |
| 2 | `app/Modules/Customs/Http/Controllers/CustomsDocumentController.php` | T3 (urutan parameter) + T4 (konvensi `source_type` jamak) | ✅ Bersih |
| 3 | `app/Modules/Customs/Services/CustomsDocumentService.php` | T5 — tambah `submit()`, `retry()`, `void()`, `updateFromPayload()`, `updateStatusFromResponse()` + state-machine status | ✅ Bersih |
| 4 | `app/Modules/Customs/Jobs/SubmitCustomsDocumentJob.php` | T6 (bagian job), T7 (property belum diinisialisasi), T8 (nama method logger salah), T9 (wire ke `CeisaH2HClient::submit()` asli) | ✅ Bersih |
| 5 | `app/Modules/Customs/Jobs/PollCustomsStatusJob.php` | T6 (bagian job), T9 (wire ke `CeisaH2HClient::checkStatus()` asli) | ✅ Bersih |
| 6 | `config/customs.php` | T10 — hubungkan `signing.api_secret` ke `env('CEISA_API_SECRET')` | ✅ Bersih |
| 7 | `routes/console.php` | T11 — jadwalkan `PollCustomsStatusJob` tiap 15 menit, hanya aktif jika `customs.enabled` | ✅ Bersih |

Pengecekan silang tambahan yang dilakukan setelah semua fix:
- `php -l` ulang atas **seluruh isi folder** `app/Modules/Customs/` + `config/customs.php` + `routes/console.php` + `routes/customs.php` — semua bersih, tidak ada regresi sintaks.
- Grep pemanggilan method `$document->...()` di semua Controller & Blade view modul Customs — hanya `load()` (bawaan Eloquent) yang tersisa, tidak ada lagi pemanggilan method custom yang belum terdefinisi.

### Yang SENGAJA tidak diubah (guard rail keamanan tetap dipertahankan)
- `CeisaH2HClient::submit()`/`checkStatus()` **tetap** melempar exception jika `config('customs.enabled')` masih `false` — guard ini tidak di-bypass. Setelah fix, job akan **gagal jujur** (tercatat sebagai error, retry sesuai backoff) selama endpoint resmi DJBC belum dikonfigurasi, alih-alih berpura-pura sukses seperti sebelumnya (T9).
- `routes/customs.php` tetap membungkus seluruh UI dalam `if (config('customs.enabled'))` — module tetap tidak dapat diakses lewat web sampai admin secara sadar mengaktifkannya.
- Endpoint CEISA (`/api/v1/submit/...`, `/api/v1/status`) di `CeisaH2HClient` masih placeholder `TODO` menunggu spesifikasi resmi DJBC — **tidak diisi asal-asalan**, karena menebak endpoint resmi lembaga pemerintah berisiko salah kirim data produksi ke alamat yang salah.

### Verifikasi lanjutan yang direkomendasikan (di luar kemampuan container ini)
Container audit ini tidak memiliki database/MySQL untuk menjalankan `php artisan test`. Sebelum merge ke branch utama, jalankan minimal:
```bash
php artisan test --filter=CustomsDocumentFoundationTest
php artisan route:list --name=customs
```
Uji manual tambahan yang disarankan: buat draft PIB dari sebuah Purchase Order lewat UI (setelah `CEISA_ENABLED` dinyalakan di environment staging), lalu cek `source_type` yang tersimpan di tabel `cst_customs_documents` benar-benar `'purchase_orders'` (bukan `'purchase_order'`).

---

## Lampiran A — Detail Temuan Perbandingan Flowchart (12 Halaman) vs Kode (Ringkasan dari Audit Awal)

| Flowchart | Kode Aktual | Status |
|---|---|---|
| CMT: beli kain jadi → potong → jahit internal → kemas → ekspor | Yarn→Knit→Dyeing→Cutting→Stitching(maklun)→Finishing | ❌ Beda model bisnis |
| 9 jenis dokumen BC (3 kategori: masuk/keluar-nonekspor/ekspor) | Hanya PIB & PEB (2 jenis) | ❌ 7 jenis tidak ada |
| Jendela waktu SPPD 15:30/11:00 | Tidak ada business rule waktu | ❌ Tidak ada |
| Verifikasi dokumen-fisik-sistem, koreksi data | Tidak ada state/endpoint untuk ini | ❌ Tidak ada |
| NCR (buat/close) saat barang NOK | Tidak ada entitas NCR | ❌ Tidak ada |
| QC 100% inspection packing, QC Buyer | Hanya `CuttingCheck`/`FinishingStage` parsial | ⚠️ Sebagian |
| SOP Jarum Patah | Tidak ada | ❌ Tidak ada |
| SOP Aval/Scrap (kategorisasi, jual) | Hanya label laporan, tanpa modul | ❌ Tidak ada |
| Marker/pola potong | `CuttingOrder.marker_efficiency` | ✅ Ada |
| Barcode/labeling hasil produksi | Modul `BarcodeLabel` | ✅ Ada |
| Jurnal WIP → Barang Jadi | `WorkOrderService::complete()` | ✅ Ada, rapi |
| PPIC kirim kebutuhan → PO material | `MaterialPurchaseOrder` + validasi qty | ✅ Ada |

*(Lampiran ini merangkum audit yang sudah disampaikan sebelumnya di percakapan; detail baris-kode ada di riwayat chat.)*
