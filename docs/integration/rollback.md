# Filament addition rollback constraint

Disable PLATFORM_FILAMENT_ENABLED (default false) without discarding earlier ledger/workflow evidence. No database migration added for this UI increment. Package removal requires coordinated reviewed removal of panel provider and User Filament contract, paired composer.json/lock changes; never reset dirty worktree. Do not delete shared masters or revert template_saldo_awal.csv deletion. Existing posted journal seals must remain enforced. See filament-livewire-adoption.md.

# Rollback dan gate B01

Tidak ada migration, schema change, runtime code change, feature activation, commit, merge atau deploy. Behavior aplikasi sebelum/sesudah sama; tambahan hanya dokumentasi dan fixture/test development.

Rollback pekerjaan ini: tinjau dan hapus hanya file baru dalam `D:\xampp\htdocs\app-akuntansi-portfolio-master\docs\integration`, `D:\xampp\htdocs\app-akuntansi-portfolio-master\tests\Feature\IntegrationMatrixBaselineTest.php` dan `D:\xampp\htdocs\app-akuntansi-portfolio-master\tests\Fixtures\integration\matrix-baseline.php`. Jangan gunakan git reset/clean menyeluruh; jangan memulihkan penghapusan template_saldo_awal.csv milik pengguna.

Backup/restore staging DB + object storage **belum direhearsal**. Sebelum migration tahap berikutnya: identifikasi host/schema/blob yang diotorisasi, cutoff, checksum/count, uji restore ke target terisolasi dan rekonsiliasi. Tidak menganggap source ZIP sebagai backup DB/blob. Tidak menuliskan command restore host yang belum diketahui.

No-go: full suite gagal, coverage baseline belum lengkap, company boundary belum siap, tidak ada bukti restore. Feature flag integrasi baru: tidak dibuat/diaktifkan. Flag lokal existing tidak diubah. Hentikan aktivasi sampai gate terpenuhi.