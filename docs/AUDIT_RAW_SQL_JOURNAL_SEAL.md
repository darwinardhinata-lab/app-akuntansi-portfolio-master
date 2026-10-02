# Audit jalur raw-table jurnal dan proteksi seal

## Cakupan perubahan

Pemanggilan literal DB::table('journal_headers') / DB::table('journal_details')
di app diganti ProtectedJournalQuery, kecuali internal MaklunJournalProtection.
Read-only literal ikut memakai builder yang sama, tanpa perubahan filter laporan.
Pemanggilan beralias untuk laporan tetap read-only dan tidak diubah.

Jalur mutasi ditemukan: FastImportJurnal, FastSyncJurnal, JournalImport,
JournalCsvImportService, ProcessPendingTempJob, PurchaseOrderService void,
SalesOrderService rollback dan PaymentPlanService deprecated. Tidak menjalankan
command import/sync atau mengaktifkan service deprecated selama audit.
DB::statement multiline yang diperiksa pada sales import mengubah sales_orders,
bukan journal tables. SET FOREIGN_KEY_CHECKS pada import tetap ada; seal bukan
pengganti integrity/FK validation atau izin menjalankan import produksi.

## Guard

- Update/delete memeriksa semua journal_id target sebelum menulis, sehingga batch
  campuran tidak mengubah unsealed lalu gagal pada sealed.
- Insert/insertOrIgnore/insertGetId mensyaratkan journal_id eksplisit dan menolak
  ID sealed. Reparent detail ke jurnal sealed ditolak.
- Upsert, insertUsing, insertOrIgnoreUsing, truncate dan callable updateOrInsert
  ditolak. updateOrInsert array memakai transaksi dan protected insert/update.
- Flag seal tidak dapat diubah melalui ordinary protected update; seal internal
  hanya menulis true. Eloquent upsert header/detail juga ditolak.
- Regression source mendeteksi reintroduksi call literal yang diaudit, bukan
  pemeriksaan semantik seluruh PHP/SQL.

## Batas keamanan (wajib sebelum rollout)

Ini call-site hardening, bukan database enforcement. DB::table dengan alias,
DB::connection()->table, SQL dinamis, direct SQL, toBase, increment/decrement pada
jalur lain, atau metode baru bisa melewati guard bila tidak menggunakan wrapper.
Eloquent builder masih memiliki toBase yang dapat digunakan langsung. Administrator
DB/restore/migration dapat mengubah seal. Check-then-write memiliki race lintas
koneksi; SQLite tests tidak membuktikan isolation MySQL. Guard membaca flag saja,
bukan merekonstruksi receipt linkage bila flag hilang. Jurnal historis belum disegel
atau di-backfill otomatis.

Preflight terkait Bill/stock dalam caller mengandalkan transaksi caller untuk
rollback saat guard menolak; audit side-effect file dan command multi-phase masih
perlu. Seluruh paket belum layak disebut ledger immutable absolut.

Langkah database yang disarankan terpisah: review MySQL triggers untuk update/delete
sealed header, update/delete/insert detail sealed (OLD dan NEW journal_id), seal
one-way, serta role koneksi tanpa ALTER/DROP/TRIGGER. Jangan membuat trigger tanpa
review bootstrap/deployment dan transaction behavior. Tidak ada SQL deployment,
migration operasional, privilege, environment, atau data produksi diubah di sini.

## Validasi

ProtectedJournalQueryTest memeriksa mixed batch update/delete, insert/ignore,
truncate/upsert/updateOrInsert, reparent detail dan normal unsealed update. Full
suite SQLite: 276 test, 9998 assertion. Uji MySQL concurrency dan test command
import/sync pada database terisolasi masih diperlukan sebelum rollout.