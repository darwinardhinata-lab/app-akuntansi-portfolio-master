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

## 6. Receipt/MRN: celah yang belum ditutup

Inspeksi terbatas membuktikan MaterialReceiptController menampilkan PO berdasarkan
status legacy APPROVED/PARTIAL. MaterialReceiptService memeriksa sisa quantity,
tetapi belum memeriksa approval_status APPROVED pada pemanggilan langsung.
Filter form bukan kontrol server-side yang cukup.

**Desain G belum diterapkan:** jalur tersebut menangani COA, stok/ledger, dan
posting jurnal. Sesuai aturan, service receipt tidak diubah; patch guard receipt
memerlukan persetujuan manual terpisah. Dengan demikian fitur approval PO ini
belum boleh diklaim mengamankan receipt terhadap PO belum APPROVED. Penutupan
celah tersebut merupakan prasyarat keamanan rollout jalur penerimaan barang.

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