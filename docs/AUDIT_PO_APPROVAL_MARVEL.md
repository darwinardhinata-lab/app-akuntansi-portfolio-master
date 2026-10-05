# Tahap 4: PO Approval

## 1. Scope dan keputusan

Material PO berasal dari Material PR APPROVED. Tahap ini menambahkan otorisasi,
segregation of duties (SoD), reject, edit/revise, detail, dan histori append-only.
Approval PO tidak memposting jurnal atau mengubah stok/ledger/COA.

Untuk tim kecil, SoD yang disetujui hanya melarang approver menjadi pembuat atau
submitter PO. Pembuat PR sumber boleh approve PO bila berizin dan bukan pembuat/
submitter PO. Contoh dua orang: A membuat PR, B approve PR dan membuat/submit PO,
A approve PO. Kontrol ini memisahkan pelaku per dokumen, bukan sepanjang rantai.

## 2. Schema dan pemisahan status

Kolom lifecycle existing dipakai kembali: approval_status, fulfillment_status,
submitted_by/at, approved_by/at, rejected_by/at, rejection_reason, dan created_by.
Tidak ada duplikasi kolom tersebut.

- approval_status string(20): DRAFT -> SUBMITTED -> APPROVED atau REJECTED.
- status legacy enum: DRAFT, APPROVED, PARTIAL, RECEIVED, CANCELED.
  SUBMITTED/REJECTED tidak ditulis ke enum ini; sebelum approval tetap DRAFT,
  saat approve menjadi APPROVED, reject/revise menggunakan DRAFT.
- fulfillment_status tetap terpisah dari approval; edit/revise memerlukan OPEN.
- Kolom baru revision_no unsigned integer default 0.
- Tabel baru mfg_material_purchase_order_histories: id, order_id (FK PO restrict
  on delete), action CREATED/SUBMITTED/APPROVED/REJECTED/EDITED/REVISED,
  from_status nullable, to_status, revision_no unsigned integer, actor_id nullable
  (FK users restrict on delete), reason text nullable, created_at; tanpa updated_at.
- histories() diurutkan ID naik dan mempunyai relasi actor.

## 3. Otorisasi default deny

MaterialOrderAuthorization menggunakan allowlist global yang terpisah dari PR:

| Env | Config | Hak |
|---|---|---|
| PO_VIEW_USER_IDS | platform.po_view_user_ids | Lihat daftar/detail |
| PO_CREATE_USER_IDS | platform.po_create_user_ids | Buat, submit milik sendiri, edit/revise |
| PO_APPROVE_USER_IDS | platform.po_approve_user_ids | Approve/reject dengan SoD |

Semua default kosong. View diterima bila masuk salah satu allowlist view/create/
approve. ADMIN tidak mendapat bypass; tidak ada grant produksi otomatis atau
nilai produksi dalam .env.example. Allowlist PR tidak memberi hak PO otomatis.

Controller memeriksa izin sebelum validasi/service (HTTP 403); service mengecek
ulang izin/status pada record yang dikunci dan melempar AuthorizationException.
Actor controller berasal dari autentikasi; field actor, created_by, status, dan
revision_no dari payload tidak menjadi identitas/hak. API service menerima actor-ID
dari pemanggil internal tepercaya.

## 4. Transisi, edit/revise, dan reservasi PR

- Create memerlukan izin create, PR APPROVED, supplier RAW_MATERIAL aktif,
  master material aktif, harga nonnegatif, dan quantity yang tidak melebihi sisa
  detail PR yang sesuai. PO awal DRAFT/revisi 0; histori CREATED ditulis.
- Submit hanya pembuat berizin create, DRAFT, minimal satu detail; histori SUBMITTED.
- Approve/reject hanya approver berizin pada SUBMITTED, berbeda dari pembuat dan
  submitter. Reject wajib alasan, maksimal 2000 karakter setelah trim.
- Edit hanya pembuat berizin create pada DRAFT. Header yang boleh berubah:
  po_date dan remarks; supplier dan referensi sumber PR tetap. Semua detail
  diganti atomik, subtotal/grand_total dihitung ulang, tax_amount existing tetap.
- Edit melepas reservasi qty_ordered PR milik PO tersebut lalu menerapkan detail
  baru dalam transaksi yang sama. Reservasi PO lain tetap mengurangi sisa PR;
  baris duplikat tetap dibatasi jumlah sisa keseluruhan. PR sumber harus APPROVED.
- Revise hanya pembuat berizin create dari REJECTED; alasan 10-1000 karakter
  setelah trim. Hasil DRAFT, revision_no +1, snapshot siklus lama dibersihkan;
  REJECTED lama tetap utuh dan REVISED merekam alasan baru. Reservasi tetap ada.
- Edit/revise ditolak bila fulfillment bukan OPEN, status legacy PARTIAL/RECEIVED/
  CANCELED, atau detail memiliki qty_received > 0. PO tanpa referensi PR lengkap
  tidak dapat diedit; tidak ada rekonstruksi linkage legacy secara otomatis.
- Semua mutasi dan insert history berada dalam transaksi yang sama dengan row
  lock. Insert history gagal membatalkan header/detail/status/reservasi PR.

## 5. UI, legacy, dan batas append-only

Detail PO menampilkan supplier, tanggal, approval/fulfillment, revisi, catatan,
nominal, material, qty diterima, dan ID detail PR sumber. Tabel History berisi
waktu, aksi, dari -> ke, aktor, alasan, revisi. Form edit mempertahankan semua
baris serta old input. Tombol mengikuti izin/SoD/status; UI bukan pengaman utama.

PO lama tidak di-backfill. Bila histori kosong, tampil catatan "PO dibuat sebelum
pencatatan histori". Aksi baru terhadap PO legacy hanya mencatat aksi tersebut,
bukan merekonstruksi masa lalu. EDITED mencatat aksi/status, bukan snapshot penuh
versi header/detail.

Model history menolak event updating/deleting (termasuk update/save/delete).
Immutability bukan absolut: raw SQL, bulk query yang melewati event, dan admin DB
masih dapat mengubah record. Belum ada trigger DB; tetap backlog rollout.

## 6. Receipt/MRN: temuan baseline Tahap 4

Inspeksi terbatas membuktikan MaterialReceiptController menampilkan PO berdasarkan
status legacy APPROVED/PARTIAL. MaterialReceiptService memeriksa sisa quantity,
tetapi belum memeriksa approval_status APPROVED pada pemanggilan langsung.
Filter form bukan kontrol server-side yang cukup.

**Pada akhir Tahap 4, desain G belum diterapkan:** jalur tersebut menangani COA, stok/ledger, dan
posting jurnal. Sesuai aturan, service receipt tidak diubah; patch guard receipt
memerlukan persetujuan manual terpisah. Dengan demikian fitur approval PO ini
belum boleh diklaim mengamankan receipt terhadap PO belum APPROVED. Penutupan
celah tersebut merupakan prasyarat keamanan rollout jalur penerimaan barang.

Temuan baseline di atas ditangani setelah persetujuan manual pada Tahap 5,
sebagaimana bagian 9. Batas penerimaan non-PO tetap berlaku.

## 7. Migration dan risiko rollout

Dua migration PO baru dibuat, belum dijalankan staging/operasional:

- 2026_10_05_090001_create_material_purchase_order_histories_table.php
- 2026_10_05_090002_add_revision_no_to_material_purchase_orders_table.php

Dua migration PR Tahap 3 juga belum dijalankan operasional oleh Cline:

- 2026_10_03_090001_create_material_purchase_request_histories_table.php
- 2026_10_03_090002_add_revision_no_to_material_purchase_requests_table.php

Schema diuji hanya melalui RefreshDatabase SQLite in-memory. Operator wajib
menerapkan keempat migration secara manual dalam urutan timestamp sebelum
deploy/aktivasi kode yang menggunakannya. Tanpa schema baru, baca detail atau
mutasi PR/PO dapat gagal. Cline tidak menjalankan migrate operasional atau staging.

User existing kehilangan akses PO sampai operator menetapkan allowlist manual;
config cache perlu diselaraskan oleh operator saat rollout. FK restrict mencegah
penghapusan PO/user yang dirujuk history. Allowlist global bukan isolasi company/
factory. Verifikasi concurrency MySQL belum dilakukan; verifier terisolasi tidak
dijalankan. Push branch, merge main, dan deploy tidak dilakukan.

## 8. Verifikasi akhir dan backlog

- Langkah 6: 41 targeted test passed, 572 assertions; termasuk 21 test PO.
- Test mencakup history/append-only, izin/ADMIN/ownership, SoD fleksibel, reason,
  edit/revise, rollback enam operasi, reservasi PO lain/baris duplikat, approval
  tanpa perubahan stok/ledger/jurnal, actor HTTP, form edit, history dan legacy.
- Lint, Pint class/test, diff check, view:cache lulus; 10 route PO tersedia.
- Langkah 8: targeted PR/PO 57 passed, 849 assertions.
- Suite penuh `php artisan test --stop-on-failure`: 329 passed, 10860 assertions;
  dijalankan sekali pada Langkah 8.
- Lint 15 PHP berubah lulus; Pint --test 8 class/test lulus, bukan routes;
  diff check bersih, view:cache berhasil, dan 10 route PO terdaftar.
- Fixture MySQL diberi izin dan actor PO eksplisit serta assertion history PO;
  verifier tidak dijalankan dan tidak termasuk suite default Unit/Feature.
- Matching PO/GRN/Bill, Factory, Purchase Type, approval bertingkat, backfill,
  trigger DB, dan isolasi company/factory belum diimplementasikan.

## 9. Tahap 5: Guard Receipt

### 9.1 Guard yang disetujui dan jalur terlindungi

Setelah review diff dan persetujuan manual, MaterialReceiptService::createAndPost
memeriksa referensi PO di awal transaksi, sebelum `$now`, resolusi COA, generator
nomor, atau SQL mutasi. DocumentSequence::generateSecure sendiri hanya membaca/
lock; mutasi stok pertama tetap berada pada MaterialCostHelper::receiveStock.

- Header `po_id` dan seluruh PO yang dirujuk `items.*.po_detail_id` diperiksa.
- Detail tidak ditemukan atau berbeda PO dari header ditolak dengan exception.
- ID PO unik diurutkan numerik, kemudian PO di-load dengan lockForUpdate.
- approval_status wajib MaterialPurchaseOrder::APPROVED; status legacy wajib
  APPROVED atau PARTIAL, mengikuti definisi PO terbuka existing.
- DRAFT/SUBMITTED/REJECTED, PO tidak ditemukan, dan status tidak terbuka ditolak
  dengan pesan Indonesia sebelum mutasi. Receipt merujuk beberapa PO ditolak
  seluruhnya bila salah satu belum APPROVED.
- Service langsung dan HTTP MaterialReceiptController::store terlindungi melalui
  service yang sama. Tidak ditemukan command penerimaan PO lain dalam direktori
  app/Modules/Manufacturing dan app/Console pada inventaris terbatas.

**Guard tidak mengubah jurnal/stok/COA.** Logika posting, pemilihan akun,
perhitungan MAC, update stok/ledger, dan quantity received existing tidak diubah.
Controller menampilkan exception melalui pesan error existing, bukan bypass.

### 9.2 Jalur yang sengaja tidak dilindungi oleh approval PO

- **Receipt TANPA referensi PO tetap diizinkan (baseline).** Ini celah kontrol
  procurement yang diketahui dan memerlukan keputusan bisnis terpisah, bukan
  diklaim tertutup oleh guard ini. Menghilangkan semua referensi PO dari payload
  tetap menjadikan penerimaan sebagai MRN non-PO.
- **Import material receipt TIDAK melindungi PO:** format existing tidak mempunyai
  field PO/header-detail. Import membuat MRN non-PO; tidak ada perluasan format
  atau klaim import PO terlindungi. Test membuktikan kondisi tersebut.
- **Void receipt sengaja tidak diguard.** Pembalikan receipt existing tidak boleh
  terhalang approval PO saat ini; logika void tetap unchanged.
- Validasi lengkap matching supplier/material/header/detail dan kewajiban setiap
  baris mempunyai detail PO bukan bagian patch ini. Matching PO/GRN/Bill tetap
  backlog. Guard memeriksa referensi yang diberikan, bukan mengarang linkage.

### 9.3 Receipt sebagian/penuh dan batas lifecycle existing

Test sukses memakai alur nyata createRequest -> submitRequest -> approveRequest
-> createOrderFromRequest -> submitOrder -> approveOrder -> receipt, dengan
actor berbeda dan izin eksplisit. approveOrder mengisi status legacy APPROVED.

Receipt pertama 4 dari 10 dan receipt kedua sisa 6 berhasil; status PO setelah
keduanya tetap APPROVED. Service receipt existing hanya increment qty_received,
tidak mengubah status menjadi PARTIAL/RECEIVED atau menutup fulfillment. Receipt
tambahan dengan detail PO yang sama ditolak guard sisa quantity existing dengan
pesan "melebihi sisa PO". Jangan menganggap guard status menutup PO setelah
penerimaan penuh. Tanpa detail PO, kontrol sisa quantity tidak berlaku pada baris
tersebut; kewajiban linkage detail tetap keputusan terpisah.

Revise terhadap PO yang sudah memiliki qty_received > 0 tetap ditolak oleh
aturan Tahap 4. PO REJECTED yang direvisi secara sah kembali DRAFT dan wajib
disubmit/approve ulang sebelum receipt berreferensi PO.

### 9.4 Rollout legacy: opsi A, tanpa auto-approve

PO lama non-APPROVED akan ditolak menerima barang setelah guard aktif. Pilihan
yang digunakan adalah **A: proses PO lama yang masih terbuka melalui workflow
submit -> approve baru**, dengan aktor berizin dan SoD; tidak ada auto-approve,
backfill status, atau grant otomatis. REJECTED perlu revise dengan alasan dahulu.
PO legacy tanpa pembuat/identitas yang valid memerlukan penanganan operator
terpisah; kode tidak mengarang pembuat atau bypass SoD.

Query berikut **hanya usulan SELECT read-only, tidak dijalankan oleh Cline**.
Definisi open menggunakan fulfillment OPEN dan status DRAFT/APPROVED/PARTIAL,
serta masih ada sisa detail yang belum diterima:

```sql
SELECT COUNT(*) AS open_po_non_approved
FROM mfg_material_purchase_orders AS po
WHERE po.fulfillment_status = 'OPEN'
  AND po.status IN ('DRAFT', 'APPROVED', 'PARTIAL')
  AND (po.approval_status IS NULL OR po.approval_status <> 'APPROVED')
  AND EXISTS (
      SELECT 1
      FROM mfg_material_purchase_order_details AS detail
      WHERE detail.po_id = po.id
        AND detail.qty > COALESCE(detail.qty_received, 0)
  );
```

Tidak ada migration baru pada Tahap 5. Empat migration PR/PO Tahap 3–4 tetap
wajib diterapkan manual dan diverifikasi operator sebelum aktivasi kode terkait;
migration staging/operasional, push, merge, deploy tidak dilakukan oleh Cline.
Concurrency lock MySQL belum diuji; guard detail-source lookup bukan matching
menyeluruh. Tetap review semua batas non-PO/linkage sebelum rollout.

### 9.5 Verifikasi akhir Tahap 5

- Langkah 4: 45 targeted tests passed, 645 assertions, termasuk 8 guard tests.
- Penolakan service memeriksa snapshot receipt/detail/ledger/jurnal, PO/detail PO,
  saldo/MAC dan tidak ada SQL mutasi sebelum penolakan guard.
- Posting sukses end-to-end dibandingkan baseline MaterialReceiptCoaMappingTest;
  alur receipt sebagian/sisa, import non-PO, revisi, mismatch/missing detail,
  dan campuran dua PO diuji.
- Lint tiga PHP lulus; Pint model/test lulus. Pint --test service melaporkan style
  existing; tidak diformat ulang agar patch sensitif hanya guard yang disetujui.
- Langkah 6: targeted 45 passed, 645 assertions; suite penuh
  `php artisan test --stop-on-failure` 337 passed, 10951 assertions, sekali.
- Diff Tahap 5 dan staged diff check bersih; tidak ada migration baru.
- Pint --test keseluruhan tidak hijau: service tetap melaporkan style, sedangkan
  model/test lulus. Tidak menjalankan autofix service karena berpotensi mengubah
  banyak baris posting/stok di luar guard yang disetujui. Batas style tetap terbuka.
- Fixture MySQL tidak memanggil receipt sehingga tidak perlu diubah; verifier
  tidak dijalankan. Query SELECT rollout tetap hanya teks, tidak dieksekusi.

### 9.6 Review dan backlog wajib sebelum matching

- Celah over-receipt (sedang): receipt dengan po_id tetapi baris tanpa
  po_detail_id dapat melewati kontrol sisa quantity. Backlog wajib sebelum
  matching PO/GRN/Bill: Tahap 6 kecil untuk mewajibkan linkage setiap baris ke
  detail PO bila header po_id ada, dengan gerbang persetujuan manual baru.
  Review ini bukan persetujuan untuk mengubah perilaku receipt sekarang.
- Status PO tetap APPROVED setelah quantity terpenuhi. Laporan PO open tidak
  boleh bergantung pada status saja; gunakan sisa quantity detail. Pertimbangkan
  juga baris legacy tanpa detail/quantity linkage saat rekonsiliasi matching.
- Style Pint service receipt tetap terbuka; jangan melakukan format ulang
  file sensitif sebagai bagian perbaikan linkage tanpa scope yang disetujui.

### 9.7 Urutan rollout dari review operator

Checklist ini dikerjakan operator, bukan dieksekusi oleh Cline:

1. Push branch fitur (bukan main), lalu backup database penuh di luar server;
   rollback utama memakai restore terverifikasi, bukan migrate:rollback.
2. Restore salinan staging dengan nama baru; jangan menganggap pergantian nama
   otomatis menjamin keamanan. Periksa target koneksi dan guard database.
3. Terapkan migration berurutan timestamp dan verifikasi schema. Menurut review
   operator ada 10 migration: enam paket 2026_10_02_040000 sampai 090000, dua PR
   2026_10_03_090001/090002, dua PO 2026_10_05_090001/090002. Daftar enam paket
   sebelumnya belum diverifikasi ulang dalam pencatatan review ini.
4. Review konfigurasi setiap verifier MySQL sebelum menjalankan: fixture
   MaterialProcurementMysqlTest membuat database terisolasi sendiri, bukan
   otomatis memakai database staging hanya karena APP_ENV atau DB_DATABASE.
   Verifier tidak dijalankan oleh Cline; concurrency MySQL masih belum terbukti.
5. Operator mengisi allowlist PR/PO dan correction/reversal bila diperlukan,
   menjaga SoD; flag maklun issue/receipt tetap false. Tidak ada grant otomatis.
6. Hitung dampak legacy memakai SELECT read-only bagian 9.4 dan proses opsi A:
   submit -> approve, revise dahulu bila REJECTED; identitas legacy kosong
   memerlukan keputusan operator, bukan bypass.
7. Smoke test staging siklus PR -> PO -> receipt sebagian/sisa dengan akun
   berbeda, jurnal seimbang dan stok tepat; uji negatif self-approval, PO DRAFT,
   serta user tanpa izin. Jangan aktifkan kode baru sebelum schema terverifikasi.
8. Produksi hanya melalui jendela perawatan dan prosedur operator; ulangi
   verifikasi schema/config/legacy, clear config/view sesuai checklist operator.
   Merge main hanya lewat Pull Request setelah staging lulus.

Urutan pengembangan berikutnya: operator menyelesaikan persiapan/staging/smoke
test (A–E), Tahap 6 linkage detail melalui persetujuan manual baru, kemudian
matching PO/GRN/Bill dengan persetujuan per langkah bila menyentuh jurnal/COA.
Tidak ada push, backup/restore, migration, perubahan .env, deploy, atau merge
yang dilakukan sebagai bagian pencatatan review ini.