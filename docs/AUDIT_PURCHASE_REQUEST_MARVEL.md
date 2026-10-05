# Audit Purchase Request Marvel terhadap Material PR Existing

Tanggal audit: 2 Oktober 2026  
Branch acuan: `feat/marvel-mgi-accounting-safeguards`  
Commit acuan: `488836524cb9cb12d39fd744104cde022838659b`

## 1. Tujuan dan batas audit

Audit ini membandingkan inventaris visual pada
`d:\Project ERP\Daftar_Menu_Marvel_AI.xlsx` dengan implementasi Material
Purchase Request yang sudah ada. Audit ini tidak menganggap screenshot sebagai
spesifikasi workflow lengkap dan tidak menjalankan migration operasional.

Acuan workbook sendiri menyatakan bahwa fungsi aplikasi dan isi submenu yang
tidak terlihat belum diuji. Karena itu, field, status, level approval, dan aturan
otorisasi yang tidak tampak tidak boleh dibuat berdasarkan asumsi.

## 2. Kebutuhan yang benar-benar terverifikasi dari workbook

### Navigasi

- `Purchase > Purchase Request`
- `Purchase > Purchase Request Appr...`; nama lengkap diperkirakan
  `Purchase Request Approval`, tetapi belum terkonfirmasi.
- `Report > PR Outstanding`

### Halaman Purchase Request

- Tab `Overview`.
- Tab `PR List`.
- Tab `My PR`.
- Search `PR No`, `Purpose`, dan `Requester`.
- Filter `Factory`, `Purchase Type`, `Approval Status`, dan `Requester`.
- Aksi `Refresh`, `Create Purchase Request`, `Reset`, `Export`, dan `Print`.
- Aksi per baris berupa ikon view, edit, dan print. Arti ikon merupakan
  kesimpulan dari gambar, bukan hasil uji aplikasi Marvel.

Workbook tidak membuktikan struktur item, daftar purchase type, definisi
factory, tingkatan approval, batas nominal, delegation, attachment, budget
check, ataupun aturan revision.

## 3. Implementasi existing

Komponen utama:

- `app\Modules\Manufacturing\Models\MaterialPurchaseRequest.php`
- `app\Modules\Manufacturing\Models\MaterialPurchaseRequestDetail.php`
- `app\Modules\Manufacturing\Http\Controllers\MaterialProcurementController.php`
- `app\Modules\Manufacturing\Services\MaterialProcurementService.php`
- `app\Modules\Manufacturing\Services\WorkOrderMaterialPlanningService.php`
- `resources\views\manufacturing\material_procurement\request_index.blade.php`
- `resources\views\manufacturing\material_procurement\request_create.blade.php`

### Kontrak yang sudah tersedia

- Nomor unik `MPR-YYYYMMDD-*` melalui `DocumentSequence`.
- Header tanggal permintaan, tanggal dibutuhkan, catatan, pembuat, dan sumber
  Work Order opsional.
- Detail khusus `YARN`, `FABRIC`, dan `AUXILIARY` dengan referensi master,
  quantity, UOM, nama snapshot, dan catatan.
- Lifecycle `DRAFT -> SUBMITTED -> APPROVED` atau `REJECTED`.
- Row lock dan transaksi pada submit, approve, reject, dan pembuatan PO.
- Material PR dapat dibuat otomatis dari kekurangan BOM Work Order.
- PO hanya dapat dibuat dari PR `APPROVED`.
- Quantity PO tidak dapat melampaui sisa quantity PR; `qty_ordered` dilacak.
- PR/PO tidak membuat stok atau jurnal.

### Halaman dan endpoint existing

- Daftar global dengan pagination 30 baris.
- Create manual, tetapi form hanya menyediakan satu baris item.
- Submit, approve, dan reject langsung dari halaman daftar.
- Create Material PO dari PR approved.
- Seluruh route berada di grup `auth`; tidak ada middleware permission khusus
  untuk operasi PR.

## 4. Matriks gap

| Kapabilitas | Existing | Target terverifikasi | Gap |
|---|---|---|---|
| Overview | Tidak ada | Ada | Perlu query agregat dan UI |
| PR List | Daftar global sederhana | Ada | Perlu diselaraskan |
| My PR | Tidak ada | Ada | Filter owner server-side diperlukan |
| Search | Tidak ada | No/purpose/requester | Perlu query terkelompok aman |
| Factory filter | Tidak ada field/master factory | Ada | Keputusan model master diperlukan |
| Purchase Type | Tidak ada | Ada | Nilai domain belum diketahui |
| Approval Status | Data ada | Filter tidak ada | Dapat direuse |
| Requester | `created_by` ada | Search/filter requester | Relasi dan UI diperlukan |
| Purpose | Tidak ada | Search purpose | Field dan definisi diperlukan |
| Create | Ada, khusus material dan satu baris | Ada | Form dinamis dan scope perlu diputuskan |
| View | Tidak ada | Terlihat | Perlu detail read-only |
| Edit | Tidak ada | Terlihat | Perlu aturan status dan locking |
| Print | Tidak ada | Terlihat | Perlu view cetak |
| Export | Tidak ada | Terlihat | Dapat mengikuti pola Maatwebsite Excel existing |
| Revision | Tidak ada | Roadmap internal | Workflow belum dispesifikasikan |
| Approval history | Hanya kolom status terakhir | Roadmap internal | Perlu tabel append-only |
| PR approval page | Tidak ada | Menu terindikasi | Nama dan perilaku belum terkonfirmasi |
| PR outstanding | `qty_requested/qty_ordered` ada | Menu ada | Material PR dapat menjadi sumber awal |
| Company/factory scope | Tidak ada pada PR | Factory filter ada | Isolasi data belum tersedia |
| Authorization | Semua user login dapat mutasi | Belum dijelaskan workbook | Risiko P0 sebelum rollout |

## 5. Temuan risiko

### P0 — approve/reject tidak memiliki otorisasi bisnis

Setiap user terautentikasi dapat membuka daftar dan memanggil endpoint submit,
approve, atau reject. Tombol dan server tidak membedakan requester dengan
approver. Ini tidak layak dibawa ke rollout Purchase Request Marvel.

Tabel platform `permissions`, `role_permission`, dan `user_role` sudah ada,
tetapi katalog seeder saat ini hanya berisi permission Party dan helper
`Role::hasPermission()` belum dipakai oleh PR. Implementasi PR harus memakai
policy/gate server-side; menyembunyikan tombol saja tidak cukup.

### P0 — tidak ada ownership/company/factory scope

Material PR tidak memiliki `company_id`, `factory_id`, atau `org_unit_id`.
`created_by` hanya mencatat pembuat dan daftar existing menampilkan seluruh PR.
Tab `My PR` harus selalu difilter server-side. Definisi `Factory` belum tersedia
dalam schema yang diperiksa dan tidak boleh diganti diam-diam dengan Department
tanpa keputusan bisnis.

### P1 — reject memakai alasan tersembunyi tetap

UI daftar mengirim alasan `Ditolak melalui daftar Material PR` tanpa meminta
alasan aktual. Service menerima alasan, tetapi histori hanya berupa snapshot
kolom terakhir.

### P1 — tidak ada edit/revision yang aman

Belum ada aturan apakah draft boleh diubah, siapa yang boleh mengubah, apa yang
terjadi setelah reject, dan apakah perubahan material membatalkan approval.
Implementasi edit sebelum aturan ini disepakati dapat merusak audit trail.

### P1 — form create tidak memadai

UI hanya mengirim satu detail meskipun backend menerima array. Nama item diketik
manual berdampingan dengan referensi master sehingga snapshot dapat tidak cocok
dengan master. Validasi service memastikan master aktif, tetapi belum memastikan
nama dan UOM berasal dari snapshot master yang konsisten.

### P1 — race pada generator shortage Work Order

Generator mengecek keberadaan PR aktif sebelum create, tetapi tidak ada unique
constraint yang menjamin satu PR aktif per Work Order. Dua request bersamaan
masih dapat melewati pengecekan. Ini perlu idempotency/constraint yang sesuai
sebelum generator dipakai luas.

### P2 — lifecycle terlalu ringkas untuk histori

Kolom `submitted_*`, `approved_*`, dan `rejected_*` hanya menyimpan state akhir.
Ia tidak dapat merepresentasikan revision berulang, resubmit, approval bertingkat,
komentar berurutan, atau delegation.

## 6. Keputusan reuse versus pemisahan

### Rekomendasi: hybrid, bukan overwrite

Pertahankan Material PR existing sebagai domain procurement manufaktur dan
integrasi BOM. Jangan mengubah tabel detail tersebut menjadi wadah generik untuk
seluruh Purchase Request Marvel karena:

1. setiap detail terikat salah satu master yarn/fabric/auxiliary;
2. Material PO dan receipt downstream bergantung pada referensi detail itu;
3. quantity ordered sudah menjadi kontrak pemenuhan material;
4. PR umum dapat mencakup jasa, aset, biaya, atau item lain yang belum memiliki
   kontrak master dan downstream yang sama;
5. memaksakan kolom nullable/polymorphic akan melemahkan integritas referensial.

Bangun agregat Purchase Request Marvel umum dengan kontrak header, workflow,
authorization, history, list, dan reporting yang konsisten. Material request
dapat diintegrasikan sebagai subtype/source yang eksplisit, bukan digabung lewat
kolom detail ambigu.

Pilihan implementasi yang disarankan setelah domain dikonfirmasi:

- tabel header umum `purchase_requests`;
- tabel detail umum `purchase_request_details` dengan tipe referensi yang
  dibatasi dan tervalidasi, bukan generic free-form polymorphism tanpa registry;
- tabel append-only `purchase_request_status_histories`;
- linkage eksplisit dari kebutuhan material/Work Order ke PR umum, atau adapter
  satu arah ke kontrak Material PO;
- pertahankan tabel `mfg_material_purchase_*` selama transisi dan jangan
  memigrasikan histori sebelum rekonsiliasi.

Jika bisnis memastikan bahwa Purchase Request Marvel **hanya** untuk yarn,
fabric, dan auxiliary material, generalisasi additive terhadap tabel existing
masih mungkin. Keputusan itu harus tertulis sebelum migration dibuat.

## 7. Rancangan fase implementasi

### Fase 0 — keputusan domain dan security contract

Wajib dikonfirmasi sebelum coding workflow:

1. Apakah PR mencakup material saja atau juga jasa, aset, dan biaya umum?
2. Apa master `Factory`; apakah berbeda dari Company/Department/Section?
3. Daftar resmi `Purchase Type` dan dampaknya ke field/detail/approval.
4. Siapa requester, editor, submitter, approver, dan viewer lintas unit?
5. Apakah self-approval dilarang?
6. Apakah approval bertingkat berdasarkan nominal, factory, purchase type,
   department, atau budget?
7. Arti revision: kembali ke draft, versi baru, atau amendment append-only?
8. Aturan cancel/delete dan kondisi ketika PR sudah mempunyai PO.
9. Definisi `PR Outstanding`: belum ordered, sisa quantity, belum received,
   atau kombinasi status lain?
10. Format print/export dan field wajib, termasuk purpose dan attachment.

### Fase 1 — read model dan UX terverifikasi

- Overview.
- PR List dan My PR dengan pagination serta query parameter yang dipertahankan.
- Search No/Purpose/Requester.
- Filter Factory/Purchase Type/Approval Status/Requester setelah master domain
  tersedia.
- View dan print read-only.
- Export berdasarkan filter yang sama dengan daftar.
- Test isolation My PR, escaping search, pagination, dan authorization view.

Fase ini tidak boleh membuka approve/reject tanpa policy.

### Fase 2 — create/edit/submit/revision

- Form multi-detail dinamis.
- Snapshot nama/UOM dari server, bukan percaya input browser.
- Edit hanya pada state yang disahkan.
- Optimistic concurrency atau row lock untuk mencegah lost update.
- Submit dan revision dengan status history append-only.
- Attachment hanya jika kebutuhan dan kebijakan penyimpanan disetujui.

### Fase 3 — approval

- Halaman approval terpisah jika label workbook dikonfirmasi.
- Permission minimal: `purchase_request.view`, `purchase_request.create`,
  `purchase_request.update`, `purchase_request.submit`,
  `purchase_request.approve`, `purchase_request.reject`,
  `purchase_request.export`, dan `purchase_request.print`.
- Policy memperhitungkan company/factory/unit dan larangan self-approval.
- Alasan reject wajib dan disimpan sebagai event history.
- Perubahan material setelah approval wajib memicu re-approval atau versi baru.

Seeder hanya membuat katalog permission; grant role produksi tidak boleh
diberikan otomatis tanpa keputusan operator.

### Fase 4 — PO dan outstanding

- Konversi detail approved ke PO dengan lock dan pengecekan remaining quantity.
- Pertahankan invariant existing bahwa PR/PO tidak membuat stok atau jurnal.
- Definisikan split supplier dan multiple PO.
- Tambahkan PR Outstanding setelah semantik outstanding disahkan.

## 8. Strategi migration

- Semua perubahan additive; jangan rename/drop tabel existing pada fase awal.
- Tambahkan foreign key dan index untuk filter utama setelah master dipilih.
- Jangan backfill `factory_id`, `purpose`, atau `purchase_type` dengan nilai
  tebakan.
- Histori Material PR lama tetap dapat dibaca sebagai legacy/material subtype.
- Migration diuji pada SQLite dan isolated MySQL sebelum operasional.
- Aktivasi UI baru sebaiknya memakai feature flag default `false` sampai schema,
  permission catalog, grant, dan readiness check selesai.

## 9. Test minimum sebelum rollout

- Requester hanya melihat data yang diperbolehkan dan `My PR` tidak bocor.
- User tanpa permission mendapat 403 pada seluruh endpoint mutasi meskipun
  memanggil URL langsung.
- Requester tidak dapat self-approve jika aturan melarang.
- Submit/approve/reject/revision tahan double-submit dan request bersamaan.
- Approved/rejected tidak dapat diedit lewat mass assignment atau endpoint lama.
- PO tidak melebihi sisa PR pada dua koneksi MySQL bersamaan.
- Filter layar, export, dan print menghasilkan scope data identik.
- History tidak dapat diubah/dihapus melalui jalur aplikasi biasa.
- Lifecycle BOM -> Material PR -> Material PO existing tetap lulus.
- Tidak ada stok atau jurnal pada create/submit/approve PR dan PO.

## 10. Verifikasi audit

Pada baseline commit di atas:

```text
php artisan test tests/Feature/MaterialProcurementLifecycleTest.php
7 tests, 20 assertions: PASS
```

Route terdaftar untuk list/create/store/submit/approve/reject Material PR dan
list/create/store/submit/approve Material PO. Working tree bersih sebelum dokumen
audit ditambahkan.

## 11. Kesimpulan

Material PR existing layak direuse untuk nomor dokumen, transaksi/locking,
validasi master material, linkage BOM, tracking quantity ordered, dan kontrak
PR-approved-to-PO. Ia belum layak diperlakukan sebagai Purchase Request Marvel
secara keseluruhan.

Langkah aman berikutnya bukan langsung membuat seluruh workflow, melainkan
mengesahkan sepuluh keputusan pada Fase 0. Setelah itu Fase 1 dapat dibangun
dengan read model dan UX terverifikasi, sambil menutup celah authorization P0
sebelum endpoint approval dipaparkan sebagai fitur Marvel.

## 12. Otorisasi Tahap 2

Status: rancangan disetujui memakai **Pola B — allowlist env, default kosong
(deny)**. Bagian ini ditulis sebelum ada perubahan kode.

### 12.1 Keputusan pola

Pola B dipilih karena Material PR tidak memiliki `company_id`, route `mfg.*`
tidak memiliki company context, dan preceden codebase untuk aksi sensitif
adalah `MaklunReversalAuthorization` serta `PaymentPlanCorrectionAccess`
(config `platform.*_user_ids` ← env koma-dipisah, default kosong). String role
`ADMIN` **tidak** menjadi bypass; hanya ID yang tercatat eksplisit yang lolos.
Pola A (permission Platform per company) ditunda sampai PR punya company scope.

### 12.2 Konfigurasi (default kosong, tanpa nilai produksi)

| Env | Config key | Makna |
|---|---|---|
| `PR_VIEW_USER_IDS=""` | `platform.pr_view_user_ids` | ID yang boleh melihat PR |
| `PR_CREATE_USER_IDS=""` | `platform.pr_create_user_ids` | ID yang boleh create/store PR |
| `PR_APPROVE_USER_IDS=""` | `platform.pr_approve_user_ids` | ID yang boleh approve/reject PR |

Parsing mengikuti pola existing: env koma-dipisah → array; kosong/tidak diset
→ `[]` (deny semua). Sesuai konfirmasi lanjutan, akses read-only memerlukan
keanggotaan pada salah satu allowlist view/create/approve. Login saja tidak
cukup. My PR tetap dibatasi `created_by`.

### 12.3 Matriks kebijakan (default deny)

| Aksi | Izin | Status | Syarat tambahan |
|---|---|---|---|
| index/overview/list/my-pr/show | allowlist view/create/approve | - | My PR selalu difilter `created_by` server-side |
| create/store | ID di allowlist create | - | - |
| submit | pembuat (`created_by`) dengan akses PR | `DRAFT` | user lain ditolak meski PR DRAFT |
| approve | ID di allowlist approve | `SUBMITTED` | approver ≠ `created_by` dan ≠ pengirim submit (segregation of duties); `approved_by` terisi |
| reject | ID di allowlist approve | `SUBMITTED` | alasan wajib (validasi existing dipertahankan); SoD sama seperti approve |

### 12.4 Dua lapis pemeriksaan

1. **Controller (HTTP 403):** `abort_unless` untuk create/store, submit
   (ownership), dan approve/reject (allowlist + SoD) sebelum memanggil service —
   mengikuti pola `MaklunReversalAuthorization::validate`.
2. **Service (exception):** `MaterialRequestAuthorization` melempar
   `AuthorizationException` dengan aturan yang sama, sehingga pemanggilan
   internal yang melewati controller tetap ditolak.

### 12.5 UI bukan pengaman

Blade hanya merender tombol Submit/Approve/Reject bila user berwenang
(Submit: pembuat + DRAFT; Approve/Reject: allowlist approve + bukan pembuat +
status SUBMITTED). Server tetap menolak pemanggilan URL langsung.

### 12.6 Test wajib (Langkah 3)

1. User tanpa izin: approve/reject/submit/create → 403, data tidak berubah.
2. Pembuat tidak bisa approve PR miliknya sendiri (SoD).
3. User lain (bukan pembuat) tidak bisa submit PR orang lain.
4. Approver berizin non-pembuat bisa approve; status berubah, `approved_by` terisi.
5. Reject tanpa alasan ditolak.
6. Service menolak pemanggil tak berwenang meski controller dilewati.
7. `role = 'ADMIN'` tanpa allowlist eksplisit tetap ditolak.
8. Dua belas test Tahap 1 diperbarui hanya dengan memberi izin eksplisit pada
   user di dalam test (bukan melonggarkan aturan).

### 12.7 Risiko rollout

Saat config masih kosong, user existing kehilangan akses view/create/submit/approve/reject
sampai ID mereka dimasukkan operator ke environment (mandat manual, tanpa
grant otomatis).

### 12.8 Verifikasi implementasi Tahap 2

- Target PR + BOM: 21 passed, 106 assertions.
- Suite penuh `php artisan test --stop-on-failure`: 292 passed, 10102 assertions;
  dijalankan sekali pada Langkah 5.
- Syntax seluruh PHP yang diubah lulus; Pint `--test` lulus untuk 7 class/test;
  routes tidak dijalankan melalui Pint dan tidak diubah pada Tahap 2.
- `git diff --check` bersih; `view:cache` berhasil; 7 route PR terdaftar.
- Fixture BOM/MySQL diberi aktor berizin eksplisit; verifier MySQL terisolasi
  tidak dijalankan dan tidak termasuk suite default Unit/Feature.
- Actor-ID pada API service merupakan identitas dari pemanggil internal
  tepercaya; controller mengambil ID dari autentikasi, bukan payload pengguna.
- Allowlist bersifat global, bukan isolasi company/factory. Tidak ada grant
  produksi otomatis, perubahan `.env` produksi, migration operasional, push,
  merge, atau deploy. Edit/revision, history append-only, Factory, Purchase Type,
  dan approval bertingkat tetap di luar scope.

## 13. Tahap 3: Revision & History

Bagian ini menggantikan batas snapshot/edit/revision yang dicatat pada Tahap 1
dan Tahap 2 untuk fitur yang dijelaskan di bawah. Temuan awal tetap dipertahankan
sebagai catatan baseline, bukan deskripsi perilaku terbaru.

### 13.1 Desain A–F dan implementasi

**A — Histori append-only:** tabel `mfg_material_purchase_request_histories`
berisi `id`, `request_id` (FK PR, restrict on delete), `action` (CREATED,
SUBMITTED, APPROVED, REJECTED, REVISED, EDITED), `from_status` nullable,
`to_status`, `revision_no` unsigned integer, `actor_id` nullable (FK users,
restrict on delete), `reason` text nullable, dan `created_at`. Tidak ada
`updated_at`. Relasi `histories()` diurutkan menurut ID naik; aktor ditampilkan
melalui relasi `actor()`.

**B — Nomor revisi:** kolom PR `revision_no` unsigned integer default 0.
Pembuatan PR dimulai pada revisi 0; tiap revise yang berhasil menaikkan nilai
tepat satu. Edit biasa tidak menaikkan nomor revisi.

**C — Guard model:** `MaterialPurchaseRequestHistory` melempar exception pada
event `updating` dan `deleting`. Update melalui model, perubahan lalu `save()`,
dan delete melalui model ditolak tanpa mengubah record tersimpan.

**D — Aturan edit/revisi:**

- EDIT hanya untuk pembuat dengan izin create eksplisit, status DRAFT.
  Header bisnis yang boleh berubah: tanggal permintaan, tanggal dibutuhkan,
  dan catatan. Seluruh detail diganti dalam satu transaksi; action EDITED
  merekam DRAFT → DRAFT pada nomor revisi yang sama.
- REVISE hanya untuk pembuat berizin create, status REJECTED. Alasan wajib
  10–1000 karakter setelah trim; hasil DRAFT dan `revision_no + 1`.
  Snapshot submitted/approved/rejected beserta waktu dan alasan penolakan
  dibersihkan. Record REJECTED lama tidak diubah; REVISED menyimpan alasan baru.
- SUBMITTED/APPROVED/REJECTED tidak dapat diedit langsung; APPROVED tidak dapat
  direvisi. Detail mana pun dengan `qty_ordered > 0` melarang edit dan revise.
- Header dan detail dikunci sebelum perubahan; status, ownership, izin, dan
  quantity diperiksa kembali di service setelah lock. HTTP menolak akses yang
  tidak berwenang dengan 403. Actor berasal dari autentikasi, bukan payload;
  service menerima actor-ID dari pemanggil internal tepercaya.
- Allowlist Tahap 2 tetap global dan default kosong; ADMIN tidak mendapat
  bypass. Tidak ada grant role/permission produksi otomatis.

**E — Atomicity:** create, submit, approve, reject, edit, dan revise menulis
history dalam transaksi database yang sama dengan header/detail/status.
Kegagalan insert history membatalkan perubahan PR. Test mensimulasikan exception
insert untuk keenam operasi dan memeriksa header, detail, dan histori tetap utuh.

**F — Legacy tanpa backfill:** PR sebelum fitur ini tidak diberi histori
buatan. Detail menampilkan "PR dibuat sebelum pencatatan histori" bila histori
kosong. Bila PR legacy kemudian ditransisikan, histori hanya mencatat aksi baru,
bukan merekonstruksi siklus lama.

### 13.2 Tampilan dan batas fitur

Form edit mengikuti label Indonesia existing, mempertahankan semua baris detail
dan old input, serta catatan detail. Tabel History menampilkan waktu, aksi,
dari → ke, aktor, alasan, dan nomor revisi. Tombol Edit/Revise hanya tampil untuk
user berwenang pada status yang sesuai dan tanpa quantity ordered; UI bukan
pengaman utama. Snapshot status masih ditampilkan sebagai ringkasan, sedangkan
riwayat aksi kini berasal dari tabel append-only.

Histori **bukan immutability absolut**: raw SQL, query builder/bulk update yang
melewati event model, dan administrator DB masih dapat mengubahnya. Belum ada
trigger DB; trigger/proteksi tingkat database tetap backlog rollout. Histori
EDITED adalah catatan aksi/status, bukan snapshot lengkap versi header/detail.

Hubungan PR → PO approval dan matching PO/GRN/Bill merupakan tahap berikutnya.
Factory, Purchase Type, approval bertingkat, dan isolasi company/factory tidak
ditambahkan pada Tahap 3. COA dan jurnal tidak diubah.

### 13.3 Migration dan risiko rollout

Dua file migration dibuat, **belum dijalankan pada database operasional**:

- `2026_10_03_090001_create_material_purchase_request_histories_table.php`
- `2026_10_03_090002_add_revision_no_to_material_purchase_requests_table.php`

Migration diuji hanya melalui SQLite in-memory dengan RefreshDatabase.
**Operator wajib menjalankan migration secara manual sebelum deploy/aktivasi
kode Tahap 3.** Kode yang memakai tabel history atau kolom revision_no tidak
boleh berjalan di database operasional sebelum schema diterapkan; bila belum
diterapkan, pembacaan detail/penulisan PR dapat gagal. Cline hanya membuat file
migration dan tidak menjalankan migrate operasional, push, merge, atau deploy.

FK restrict juga melarang penghapusan PR atau user yang dirujuk histori.
Allowlist kosong tetap menolak akses sampai operator memberi izin manual.

### 13.4 Verifikasi sementara

- Langkah 5: 36 test PR passed, 375 assertions, termasuk 16 test revision/history.
- History append-only, penolakan otorisasi/status/quantity, alasan revisi,
  rollback keenam operasi, actor HTTP, legacy notice, dan form edit diuji.
- Lint, Pint class/test, diff check, dan view:cache lulus pada Langkah 5.
- Suite penuh Tahap 3 belum dijalankan; hanya dijalankan sekali pada Langkah 7.
- Verifier MySQL integration tidak dijalankan; verifikasi concurrency MySQL
  dan trigger DB tetap batas verifikasi/rollout.