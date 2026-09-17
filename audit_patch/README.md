# Deliverable: Audit + Perbaikan Modul Customs (CEISA H2H)

## Isi folder
- `LAPORAN-AUDIT.md` — laporan audit lengkap (11 temuan, severity-ranked, hasil eksekusi perbaikan).
- `fixes/customs-module-fixes.patch` — unified diff (format `diff -u`, siap di-apply dengan `patch -p1` atau `git apply`) untuk 7 file yang diperbaiki.
- `fixes/patched-files/` — salinan lengkap ke-7 file **setelah** diperbaiki, siap ditimpakan langsung ke repo jika patch tidak apply bersih.

## Cara apply
Dari root repo Laravel:
```bash
patch -p1 < fixes/customs-module-fixes.patch
# atau, jika struktur folder patch tidak cocok persis:
git apply --reject fixes/customs-module-fixes.patch
```
Atau salin manual dari `fixes/patched-files/` ke path yang sama di repo.

## Temuan yang DIPERBAIKI pada deliverable ini
T3, T4, T5, T6, T7, T8, T9 (bagian wiring), T10, T11 — semua bug fatal & blocker di modul `app/Modules/Customs`.

## Temuan yang BELUM diperbaiki (butuh keputusan bisnis / scope terpisah — lihat §4 LAPORAN-AUDIT.md)
- T1: model bisnis Manufacturing (Yarn→Knit→Dyeing→Cutting→Stitching-maklun) vs flowchart (CMT internal)
- T2: SOP Jarum Patah & SOP Aval/Scrap — belum ada modul
- 7 jenis dokumen BC yang belum ada (BC 2.7/2.6.2/4.1/4.7/2.6.1/2.5) — butuh entitas dokumen mutasi internal baru
- 8 laporan mutasi BC — masih placeholder kosong, butuh spesifikasi query per laporan
- Modul NCR & hold barang

## Validasi yang sudah dilakukan
`php -l` (PHP 8.3.6 CLI) atas seluruh file yang diubah — semua bersih, tidak ada syntax error.
**Belum** dijalankan: `php artisan test` (butuh DB, tidak tersedia di lingkungan audit ini) — lihat §6 LAPORAN-AUDIT.md untuk perintah verifikasi yang disarankan sebelum merge.
