# AUDIT REPORT (Round 2) — Modul Customs (CEISA H2H) Fase 1-B
**Status:** ✅ Semua 7 CRITICAL + H2/H3/H4/M1/M2 dari audit pertama **terkonfirmasi benar-benar diperbaiki**, diverifikasi lewat kode dan test HTTP sungguhan. Ditemukan **5 isu baru** — semua MEDIUM/LOW, tidak ada yang fatal.

---

## ✅ Verifikasi Perbaikan (semua dicek langsung di kode, bukan cuma percaya komentar)

| # | Temuan Fase 1 | Status | Bukti |
|---|---|---|---|
| C1 | Urutan argumen `createDraft()` terbalik | ✅ Benar diperbaiki | Controller sekarang manggil `(sourceType, sourceId, documentType, actorId)` sesuai signature. `test_create_draft_via_http_route_succeeds` lewat rute HTTP sungguhan dan assert row DB benar. |
| C2 | `canBeSubmitted()`/`scopeSubmitted()` tidak ada | ✅ Benar diperbaiki | Ditambahkan ke model, plus bonus konstanta `STATUS_*` dan `isEditable()` yang konsisten dipakai. |
| C3 | 5 method `CustomsDocumentService` hilang | ✅ Benar diperbaiki | Semua diimplementasi persis pola `lockForUpdate()+transaction+guard status` yang saya berikan. |
| C4 | Nama method `CustomsAuditLogger` salah panggil | ✅ Benar diperbaiki | `logRequest()`/`logResponse()` dipanggil dengan nama & urutan parameter yang benar. |
| C5 | `hash()` dikasih array | ✅ Benar diperbaiki | `json_encode()` ditambahkan sebelum `hash()`. |
| C6 | `$this->correlationId` uninitialized | ✅ Benar diperbaiki | Di-assign sebelum dipakai. |
| C7 | View `show`/`print` hilang | ✅ Benar diperbaiki | 3 view dibuat (`show`, `edit`, `print`), route `edit` sekarang benar-benar render form, bukan `show()`. |
| H2 | Webhook publik tanpa verifikasi, pura-pura sukses | ✅ Benar diperbaiki | Sekarang return `501 NOT_IMPLEMENTED`, dipindah ke dalam guard `customs.enabled`. |
| H3 | Gate `customs.submit` tidak ada | ✅ Benar diperbaiki | Ditambahkan di `AppServiceProvider`, dengan catatan jujur bahwa proyek ini tidak punya sistem RBAC baku — permisif sementara dengan TODO, persis sesuai instruksi. |
| H4 | Test suite tidak menembus Controller/HTTP | ✅ Benar diperbaiki | `CustomsDocumentControllerTest` sekarang genuinely lewat `$this->get()`/`post()` ke route sungguhan, termasuk regression test eksplisit untuk C1/C7/H2. Ini kualitas test yang bagus. |
| M1 | Config `signing.api_secret` hilang | ✅ Benar diperbaiki | Ditambahkan ke `config/customs.php` + `.env.example`. |
| M2 | `logError()` tidak masuk DB | ✅ Benar diperbaiki | Sekarang juga `CustomsDocumentLog::create()`. |
| Task A | 8 endpoint laporan di luar scope | ✅ Dihapus bersih | `CustomsReportController.php`, view `customs/reports/`, dan route-nya semua hilang. |

Kualitas eksekusi babak ini jauh lebih baik dari Fase 1 — instruksi diikuti dengan presisi, dan yang terpenting: **klaim "sudah diperbaiki" didukung test yang benar-benar menembus HTTP**, bukan cuma komentar `// FIX`.

---

## 🟡 Temuan Baru — MEDIUM

### N1. Gate `customs.void` didefinisikan tapi tidak pernah dipakai
`AppServiceProvider` mendefinisikan `Gate::define('customs.void', ...)`, tapi `CustomsDocumentController::void()` **tidak pernah memanggil `Gate::allows('customs.void')`** — beda dengan `submit()` yang sudah benar memanggil gate-nya. Akibatnya endpoint void saat ini terbuka untuk semua user login tanpa melalui gate yang sudah dibuat khusus untuknya. Perbaikan satu baris.

### N2. `createDraft()` men-dispatch `PollCustomsStatusJob` tanpa alasan — scope tambahan yang tidak diminta lagi
```php
// Di CustomsDocumentService::createDraft(), setelah dokumen DRAFT dibuat:
PollCustomsStatusJob::dispatch($document->id);
```
Ini **tidak ada di Task D manapun di prompt Fase 1-B** — ini penambahan baru oleh Cline. Secara teknis tidak fatal (`PollCustomsStatusJob` tidak punya constructor yang menerima parameter, jadi `$document->id` yang dioper begitu saja diabaikan PHP secara diam-diam — tidak crash), tapi job ini isinya query GLOBAL "semua dokumen berstatus SUBMITTED/UNDER_REVIEW", jadi dispatch-nya sama sekali tidak berguna untuk dokumen yang baru saja dibuat sebagai DRAFT (dokumen itu bahkan tidak masuk kriteria query job tersebut). Efeknya: setiap kali user bikin draft, satu job queue terbuang percuma tanpa melakukan apa pun yang relevan. Ini persis pola yang sudah saya minta dihindari eksplisit di §0 poin 3 — perlu dihapus.

### N3. `CustomsDocument::source()` — accessor mati/salah map, karena mismatch konvensi penamaan
Model men-decode `source_type` dengan mapping **nama tabel jamak** (`'purchase_orders'`, `'sales_orders'`, dst.), tapi alur pembuatan draft yang sungguhan (`CustomsDocumentController::create()`) menyimpan `source_type` sebagai **slug tunggal dari URL** (`'purchase_order'`, `'sales_order'`, dst. — tanpa "s") — dikonfirmasi langsung oleh `test_create_draft_via_http_route_succeeds` yang meng-assert `'source_type' => 'purchase_order'`. Akibatnya `$document->source()` akan **selalu return `null`** untuk dokumen manapun yang dibuat lewat alur normal, karena key mapping-nya tidak pernah cocok.
**Untungnya:** tidak ada view yang memanggil `->source()` saat ini (`show.blade.php` cuma menampilkan `source_type`/`source_id` mentah), jadi ini bug laten yang belum menggigit — tapi harus diperbaiki sebelum ada fitur yang benar-benar mengandalkan method ini (misalnya "lihat PO asal" di halaman detail).

---

## ⚪ Temuan Baru — Kebersihan Repo (bukan bug, tapi harus dibereskan sebelum merge)

### N4. File scratch/debug tertinggal di root proyek: `_rebuild_customs_service.php`
Berisi **fragment PHP yang rusak/tidak valid** (heredoc terpotong, campuran kode lama) — jelas artefak proses iteratif Cline saat menulis ulang service, tidak pernah dihapus. **Wajib dihapus** sebelum branch ini disentuh lebih jauh — kalau sampai ter-commit dan ter-load somehow, ini akan jadi masalah.

### N5. Folder `audit_patch/` berisi 6 file duplikat + 1 `.patch` file
Salinan basi dari `customs.php`, `CustomsDocument.php`, `CustomsDocumentController.php`, `CustomsDocumentService.php`, `PollCustomsStatusJob.php`, `SubmitCustomsDocumentJob.php` — sepertinya staging area Cline selama proses patching yang tidak dibersihkan. Tidak masuk autoload (di luar `app/`), jadi tidak fatal secara fungsional, tapi ini sampah yang membingungkan reviewer mana yang jadi "sumber kebenaran". **Wajib dihapus.**

---

## Rekomendasi

Tidak ada CRITICAL baru — modul ini sudah fungsional dan aman untuk terus dikembangkan. Tapi sebelum merge ke branch kerja utama, saya sarankan **satu putaran cleanup kecil** (bukan audit besar lagi) untuk N1–N5 di atas — lampiran prompt Fase 1-C terpisah untuk ini.

Setelah itu, modul Fase 1 benar-benar selesai dan siap untuk Fase 2 (integrasi sungguhan) begitu spesifikasi resmi CEISA H2H dari akun yang sudah Anda daftarkan bisa dibagikan detailnya (format signing, endpoint sandbox, mapping status).
