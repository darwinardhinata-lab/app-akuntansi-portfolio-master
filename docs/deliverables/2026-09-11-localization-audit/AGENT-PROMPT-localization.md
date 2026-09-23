# AGENT PROMPT — Remediasi Lokalisasi `app-akuntansi-portfolio`

**Target eksekusi:** dijalankan oleh coding agent (Antigravity/Claude Code) yang
punya akses langsung ke repo `app-akuntansi-portfolio` (bukan dari dump statis).
**Mode kerja:** batch bertahap per modul, validasi di setiap batch, checkpoint
keputusan manusia sebelum langkah yang berisiko.

---

## 0. KONTEKS WAJIB DIBACA AGENT SEBELUM MULAI

Project ERP Laravel, 3 bahasa: `id` (default/utama, bahasa bisnis), `en`, `zh_CN`.
Namespace terjemahan tunggal: `erp` (`lang/{locale}/erp.php`).

**Temuan audit sebelumnya (11 Sep 2026)** — WAJIB dikonfirmasi ulang oleh
agent sebagai langkah pertama, karena bisa saja sudah berubah sejak audit:
- Hanya **105 panggilan `__()`/`@lang()`** di seluruh 88 file Blade, terkonsentrasi
  di 3 file (`layouts/app.blade.php`, `dashboard.blade.php`, `reports/ar_subledger.blade.php`).
  **85 file Blade lain (termasuk semua 11 view Manufacturing) = 0 panggilan `__()`**,
  masih hardcoded string Indonesia.
- `lang/id/erp.php` = 218 key, `lang/en/erp.php` = 2.955 key, `lang/zh_CN/erp.php` = 1.354 key.
  Key yang ada di **ketiga** locale sekaligus cuma 209.
- `lang/id/` tidak punya `auth.php`, `pagination.php`, `passwords.php`, `validation.php`.
- File nyasar: `lang/id/erp_content.txt`, `lang/zh_CN/erp_backup.php`.
- Folder duplikat `manufacturing-integration-final/manufacturing-integration/`
  (copy identik `app/Modules/Manufacturing`) ikut ter-commit di root repo.
- `.qwen/settings.json` ikut ter-commit.
- `config/app.php`: `locale`/`fallback_locale` default `en`, bukan `id`.

---

## LANGKAH 0 — Rekonsiliasi & Deteksi Konflik (WAJIB, sebelum kode apa pun disentuh)

1. Jalankan `git log --oneline --all -- lang/ resources/views/` dan
   `git log --oneline --all -- "*erp.php"` — cek apakah ada branch/commit lain
   yang sudah berisi hasil Phase 1/2 (grow ke 2.100+ `__()`, 1.212 key) yang
   belum di-merge ke branch kerja saat ini.
2. Cek juga apakah ada folder/zip deliverable lokal yang belum ter-commit
   (`find . -iname "*deliverable*" -o -iname "*phase*localization*"`,
   termasuk di luar working tree jika akses filesystem lebih luas tersedia).
3. **STOP dan laporkan ke manusia** jika ditemukan sumber Phase 1/2 yang lebih
   lengkap dari kondisi saat ini — jangan mulai kerja baru sebelum dikonfirmasi
   mana yang jadi source of truth. Prioritas: **merge yang sudah ada**, bukan
   mengerjakan ulang dari nol.
4. Jika benar-benar tidak ada sisa Phase 1/2 di mana pun → lanjut ke Langkah 1.

---

## LANGKAH 1 — Kebersihan Repo (checkpoint sebelum delete)

1. Verifikasi `manufacturing-integration-final/manufacturing-integration/`
   benar-benar identik byte-per-byte dengan `app/Modules/Manufacturing` + migration
   terkait (`diff -rq`). Jika identik → **tampilkan konfirmasi ke manusia**,
   baru hapus foldernya setelah "ya".
2. Hapus `.qwen/` dari repo (tambahkan ke `.gitignore` juga supaya tidak
   ke-commit lagi dari tool lain).
3. Hapus `lang/id/erp_content.txt` dan `lang/zh_CN/erp_backup.php` **setelah**
   memastikan tidak ada key unik berharga di dalamnya yang belum ada di
   `erp.php` masing-masing (diff key set dulu, extract kalau ada yang belum
   tercakup, baru hapus filenya).
4. Commit terpisah: `chore: bersihkan folder staging duplikat & file nyasar`.

---

## LANGKAH 2 — Lengkapi File Lokal Bawaan Laravel untuk `id`

1. Buat `lang/id/auth.php`, `lang/id/pagination.php`, `lang/id/passwords.php`,
   `lang/id/validation.php` — basis dari versi `en/` yang sudah ada (struktur
   key HARUS identik dengan file Laravel bawaan, hanya value yang diterjemahkan).
2. Validasi: `php artisan lang:check` (kalau tersedia) atau manual — jalankan
   halaman login dengan locale id dan pastikan pesan validasi tidak fallback ke Inggris.
3. Commit: `feat(lang): tambah file lokal bawaan Laravel untuk id`.

---

## LANGKAH 3 — Bangun Kamus Master (`erp.php`) — id sebagai basis

**Prinsip:** `id` adalah bahasa bisnis utama dan harus PALING LENGKAP, bukan
paling sedikit. Urutan kerja per key:

1. Ambil union semua key dari `id`+`en`+`zh_CN` erp.php saat ini.
2. Untuk setiap key yang **belum ada di `id`**: tentukan dulu apakah key itu
   masih relevan (dipakai di Blade view yang masih ada) — kalau key `en`/`zh_CN`
   orphan (tidak dipakai di mana pun setelah Langkah 4 selesai), JANGAN ikut
   dimasukkan ke `id`, tandai untuk dibersihkan di akhir (Langkah 6).
3. Untuk key yang relevan tapi hilang dari salah satu locale: isi terjemahan
   yang hilang, JANGAN copy-paste dari locale lain tanpa translate.
4. Struktur array HARUS identik urutan & nesting di ketiga file (memudahkan
   diff & review manual ke depannya).
5. Kerjakan per modul (lihat Langkah 4), bukan sekaligus 3.000 key — supaya
   tiap batch bisa divalidasi.

---

## LANGKAH 4 — Konversi Hardcoded String → `__()` per Modul (BERTAHAP)

**Urutan batch (prioritas modul aktif dikerjakan tim):**

| Batch | Modul | File Blade | Alasan urutan |
|---|---|---|---|
| 1 | Manufacturing | 11 file | Modul paling baru & paling sering disentuh |
| 2 | Tax, User | ~6 file | Modul kecil, cepat untuk validasi pola kerja |
| 3 | Sales, Purchasing | — | Modul transaksi inti |
| 4 | Warehouse, Reporting | — | Sisanya |
| 5 | Layouts/shared partials sisa | — | Terakhir, karena dipakai lintas modul (perubahan di sini berdampak luas) |

**Per file dalam satu batch, agent HARUS:**
1. Ekstrak semua string UI hardcoded (label, heading, placeholder, tombol,
   pesan flash) — JANGAN ubah: nama variabel, atribut HTML non-visual, kode.
2. Buat/pakai key di `erp.php` dengan format `snake_case`, hindari duplikat
   makna (cek dulu apakah string yang mirip sudah punya key).
3. Ganti string dengan `{{ __('erp.key') }}` (di dalam teks/atribut) atau
   `__('erp.key')` (di dalam PHP/attribute binding).
4. Isi terjemahan `id`/`en`/`zh_CN` untuk key baru tersebut — TIDAK boleh ada
   key yang cuma terisi di 1-2 locale.
5. Jangan menerjemahkan: nama merek, kode dokumen (`SPK`, `MRN`, dll — ini
   istilah baku internal, biarkan apa adanya), variabel dinamis.

**Setelah tiap batch selesai:**
- Jalankan validasi (lihat Langkah 5) khusus untuk file yang disentuh batch itu.
- Commit terpisah per batch: `feat(i18n): terjemahkan view <nama modul>`.
- **Checkpoint ke manusia**: tampilkan ringkasan (jumlah file, jumlah key baru,
  hasil validasi) sebelum lanjut ke batch berikutnya — jangan jalan terus
  otomatis sampai semua modul tanpa jeda review.

---

## LANGKAH 5 — Skrip Validasi (jalankan setelah tiap batch & di akhir)

Wajib nol error sebelum lanjut/deliver:

1. `php -l` untuk setiap file `.php`/`.blade.php` yang disentuh.
2. Cek parity key 3 locale — key yang dipakai di file batch ini harus ADA
   dan TERISI (bukan string kosong) di `id`, `en`, DAN `zh_CN`.
3. Cek tidak ada `__('erp.` dengan key yang tidak terdaftar di ketiga file
   (grep semua pemanggilan, cocokkan ke daftar key final).
4. Render smoke-test tiap halaman yang disentuh (kalau ada test browser/dusk)
   untuk 3 locale, screenshot untuk review manusia jika sulit diverifikasi otomatis.
5. Pastikan tidak ada string yang tidak sengaja ikut ter-translate padahal
   seharusnya statis (nomor dokumen, kode akun, dsb).

---

## LANGKAH 6 — Pembersihan Akhir

1. Hapus key orphan di `en`/`zh_CN` yang sudah tidak dipakai di mana pun
   (hasil tandai dari Langkah 3.2) — buat daftar dulu, minta konfirmasi manusia
   sebelum hapus massal.
2. Pertimbangkan ulang `config/app.php` locale default — apakah memang mau
   `en` atau `id`. **Ini keputusan bisnis, bukan keputusan agent** — tanyakan,
   jangan ubah sepihak.
3. Deliverable akhir: ringkasan `git diff --stat` total, jumlah `__()` sebelum/
   sesudah, jumlah key final per locale, daftar file yang masih 0% (jika ada
   yang sengaja di-skip).

---

## Batasan Keras untuk Agent

- Jangan pernah menimpa/menghapus `lang/*/erp.php` tanpa diff-check dulu terhadap versi sebelumnya.
- Jangan gabungkan Langkah 1 (hapus file) dengan batch terjemahan di commit yang sama.
- Jangan lanjut ke batch modul berikutnya tanpa checkpoint manusia di batch sebelumnya.
- Jangan ubah kode logic (Controller/Service) dalam pekerjaan ini — murni Blade view + lang files. Kalau menemukan bug logic saat kerja (seperti 2 bug idempotency yang sudah dipatch sebelumnya), catat terpisah, jangan diperbaiki di commit i18n.
