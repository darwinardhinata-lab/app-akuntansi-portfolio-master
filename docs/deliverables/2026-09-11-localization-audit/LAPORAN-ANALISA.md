# Laporan Analisa `app-akuntansi-portfolio` — 11 Sep 2026

Sumber: `app-akuntansi-portfolio_10-9-26_2.txt` (dump Repomix, 565 file, 80.985 baris)
diekstrak & dicek satu-per-satu terhadap histori progres project.

---

## 1. STATE LOKALISASI — TIDAK SESUAI HISTORI, PERLU DIKONFIRMASI

Ini bagian paling penting. Data di file yang diupload **tidak mencerminkan**
pekerjaan Phase 1/2 yang sebelumnya tercatat selesai (grow ~105 → 2.100+
panggilan `__()`, 1.212 key unik). Kondisi aktual:

### 1a. Pemakaian `__()` di Blade view
| Metrik | Klaim histori | Kondisi file ini |
|---|---|---|
| Total panggilan `__()`/`@lang()` | 2.100+ | **105** |
| File Blade dengan ≥1 panggilan `__()` | 87 dari 88 | **3 dari 88** (`layouts/app.blade.php`, `dashboard.blade.php`, `reports/ar_subledger.blade.php`) |
| View Manufacturing (11 file) | sudah diterjemahkan | **0 panggilan `__()`** — semua masih hardcoded Bahasa Indonesia |
| View `tax/*`, `user/*` | sudah diterjemahkan (Phase 1) | **0 panggilan `__()`** |

Dicek langsung: `manufacturing/work_order/index.blade.php` dan `tax/index.blade.php`
isinya string mentah (`"Surat Perintah Kerja (SPK) Manufaktur"`, `"Master Pajak
(PPN / PPh)"`), bukan `__('erp.xxx')`.

### 1b. Kamus terjemahan (`lang/*/erp.php`)
| Locale | Jumlah key | Catatan |
|---|---|---|
| `id` | **218** | Lebih kecil dari milestone awal (312), jauh dari 1.212 |
| `en` | **2.955** | Jauh melebihi 1.212 — tidak sinkron dengan id |
| `zh_CN` | **1.354** | Ada `erp_backup.php` (1.293 key) tergeletak di folder yang sama |

- Key yang **ada di ketiga locale sekaligus**: cuma **209** dari total 3.229 key unik gabungan.
- 2.187 key ada di `en` tapi hilang dari `id` (locale utama bisnis ini).
- `lang/id/` juga **tidak punya** `auth.php`, `pagination.php`, `passwords.php`,
  `validation.php` (padahal `en/` dan `zh_CN/` punya) — pesan bawaan Laravel utk
  locale id akan fallback ke Inggris.
- `config/app.php`: `locale` & `fallback_locale` default = `'en'`, bukan `'id'`.

### Kesimpulan
File yang diupload ini kemungkinan besar **snapshot dari sebelum** deliverable
Phase 1/2 di-merge ke repo utama — kemungkinan deliverable itu masih di folder
staging terpisah dan belum pernah ter-commit ke sumber yang dipakai untuk
membuat dump ini.

### Yang perlu Anda konfirmasi
1. Apakah zip/folder deliverable Phase 1–2 dari sesi sebelumnya masih Anda simpan?
   Kalau ya, itu yang harus di-merge — bukan mulai dari nol.
2. Kalau deliverable itu sudah hilang, saya bisa mulai ulang proses lokalisasi
   dari state file ini (105 → target penuh), tapi itu scope besar (85 file Blade
   + selaraskan ulang 3 kamus + lengkapi `lang/id/auth.php` dkk) — sebaiknya
   dikerjakan bertahap per modul, bukan sekali jalan.
3. Sebelum lanjut kerja baru, sebaiknya bersihkan dulu file-file nyasar:
   `lang/id/erp_content.txt`, `lang/zh_CN/erp_backup.php` — supaya tidak
   membingungkan file mana yang jadi source of truth.

---

## 2. BUG DIPERBAIKI (lihat `idempotency-guard-fixes.patch`)

Pola yang konsisten dipakai di `CuttingOrderService`, `StitchingOrderService`,
`WorkOrderService::complete()`: selalu cek status entity sebelum posting jurnal.
Ditemukan 2 service yang **tidak** mengikuti pola ini:

### `KnitOrderService::receiveGreyFabric()`
Tidak ada guard status sebelum posting Jurnal #2 (penerimaan kain grey).
Tombol UI memang disembunyikan setelah status `COMPLETED`, tapi itu proteksi
client-side saja — resubmit form (tombol back + submit ulang, race condition
dobel klik, atau request langsung ke route) bisa membuat jurnal & stok
terposting dua kali, yarn cost yang sama ter-double-count.

**Fix:** tambah guard `if ($knitOrder->status !== 'ISSUED') throw ...` di awal
method, persis pola service lain.

### `ProcessingOrderService::receiveFabric()`
Bug identik — tidak ada guard status sebelum posting Jurnal #3 (penerimaan kain
finished hasil processing).

**Fix:** guard `if ($order->status !== 'ISSUED') throw ...`.

### `BarcodeLabelService::generate()` (bonus fix, minor)
Tidak dibungkus `DB::transaction()`. Kolom `barcode` punya unique constraint
di DB — kalau `Str::random(6)` collision di tengah loop, sebagian label sudah
ter-insert (partial batch) sementara sisanya gagal, `batch_number` jadi tidak
konsisten. Sekarang dibungkus transaction supaya atomic (all-or-nothing).

---

## 3. TEMUAN LAIN (belum di-patch, perlu keputusan Anda)

- **Folder duplikat** `manufacturing-integration-final/manufacturing-integration/`
  (784KB) — copy identik byte-per-byte dari `app/Modules/Manufacturing` +
  migrations, ikut ter-commit ke root repo. Sebaiknya dihapus.
- **`.qwen/settings.json`** — config tool AI lain (Qwen Code CLI) ikut ter-commit,
  tidak seharusnya ada di repo.
- Jumlah Service class Manufacturing = **8**, bukan 9 seperti catatan sebelumnya
  (`FabricController`, `SupplierController`, `YarnController` logic-nya langsung
  di controller, tidak punya Service class terpisah — kemungkinan wajar karena
  data master sederhana, hanya klarifikasi angka, bukan bug).

## 4. YANG SUDAH SESUAI / SOLID (dikonfirmasi ulang)

- Struktur modul: 21 model, 13 controller, 22 migration (21+1 tambahan
  `voided_status`), 11 view — cocok dengan catatan.
- `routes/manufacturing.php` ter-include & terlindungi middleware `auth`.
- Arsitektur WIP dikonfirmasi benar: WIP baru mulai di tahap Cutting, bukan
  Knitting (fabric masih fungible stock sebelum dipotong ke SPK tertentu).
- Pola moving-average cost, `lockForUpdate()` + `DB::transaction()`,
  `JournalBalanceValidator::isBalanced()` sebelum insert — konsisten dipakai
  di semua service manufaktur lain.
- Tidak ada `.env`/kredensial ter-commit, tidak ada pola raw SQL rawan injeksi.

---

## File terlampir
- `idempotency-guard-fixes.patch` — unified diff, siap di-apply (`git apply`)
- `patched-manufacturing-services.tar.gz` — 3 file lengkap hasil patch, siap timpa langsung
