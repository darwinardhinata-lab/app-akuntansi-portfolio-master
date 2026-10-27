# Keputusan integrasi

## Filament / Livewire adoption

- User-approved presentation adapter: Filament panel for standard screens; Livewire custom interactions; existing journal UI and dedicated v33 vanilla/Blade remain canonical.
- Actual installed versions 5.11.0 / 4.4.8 compatible with local PHP/Laravel; no downgrade/global update. Panel separate and default off; shared Platform permissions, no RBAC plugin or parallel master.
- Division existing is first service-backed master. No delete/bulk/inline mutation. Existing legacy controller convergence remains open.
- Composer audit advisories, missing browser/reactive HTTP acceptance and remaining backend controls are release blockers. See filament-livewire-adoption.md; passing 709 tests is not complete integration acceptance.

## 2026-10-26 — B01 / discovery, bukan aktivasi

Status: baseline dicatat; gate belum lulus.

- Pertahankan UI baris matriks aktual dan satu ledger; jangan memaksakan layout 12 blok dari konteks lama.
- Reuse `D:\xampp\htdocs\app-akuntansi-portfolio-master\app\Support\PostingService.php` saat B03, bukan membuat PostingService kedua. Perlu meninjau atomicity legacy dan seluruh call-site sebelum ekstraksi controller.
- Reuse GRN A3 yang ditemukan. B06 perlu mengatasi pembatasan integer qty dan coupling receipt/bill secara backward-compatible.
- B02 tidak boleh menganggap shared GL/stock sudah multi-company. OperationalCompany secara eksplisit single-MGI.
- Jangan memakai PaymentPlanService deprecated. Domain parity v33 harus discovery/golden fixture terpisah pada B04.
- Mandat rekening IDR3/USD1 adalah gate bisnis menurut referensi, bukan konfigurasi yang disahkan sesi ini. Tidak mengubah bank policy.
- Tidak menyalakan flag baru; tidak mematikan flag pengguna yang sudah ada; tidak deploy atau migrate.

## Rencana patch kecil berikutnya (belum dieksekusi)

1. B01.1 selesai: akar masalah fixture account dan kontrak locale dibuktikan; perbaikan test-only. Full suite hijau; guard production tidak diubah. Lihat test-evidence.md.
2. B01.2: tambah coverage perilaku sekarang untuk opening/multi-period, laporan dan reversal. Jika linked reversal belum tersedia, dokumentasikan gap untuk B03, bukan syarat membangun fitur target saat discovery. Browser urutan kolom/shortcut dan isolated MySQL memerlukan bukti tersendiri.
3. Siapkan backup DB/blob staging yang diotorisasi, verifikasi restore dan hash/count. Bukti ZIP source bukan backup operasional.
4. Setelah gate B01 selesai, B02 inventaris penuh 151 entri + disposition dan crosswalk delta terhadap legacy_map yang ada.
5. B03 atomic PostingService + accounting intent/idempotency/period/immutable posted; B04–B05 parity v33 dan financial bridge; B06–B10 operasi; B11 UAT/restore; B12 paket cutover, bukan cutover otomatis.

Keputusan bisnis tersisa: mandat rekening bertanggal efektif; metode valuation/precision; tenant deployment; otorisasi period/reversal; definisi AGPO; PIC dan persetujuan UAT. Tidak menciptakan keputusan palsu.

## B01.1 — diagnosis baseline, tanpa aktivasi

- Pertahankan akun → kode bantu → posisi → nominal, PostingService existing, GRN A3 existing, dan single-company MGI.
- PaymentPlanService deprecated bukan baseline v33; runtime period lock tetap gap penting.
- Akun anonim FX dibuat hanya dalam fixture SQLite: piutang/utang USD dan beban pembulatan tanpa currency lock. Tidak menambah master operasional, tidak mengubah 111001 menjadi USD.
- Header ekspor mengikuti locale aktif; nilai master tetap baku dan dapat diimpor ulang. Test mencakup ID/EN/ZH_CN dan memulihkan locale.
- B01.3 tetap restore rehearsal staging DB+attachment dengan control totals. Belum dilaksanakan; B01 belum lulus dan B02/integrasi tidak diaktifkan.