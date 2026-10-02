# Reversal issue append-only dan seal jurnal maklun

Reversal penuh issue memerlukan snapshot, tanpa partial-return, tanpa receipt posted
atau receipt legacy. Receipt yang sudah REVERSED memungkinkan pemulihan bahan.
Model/ledger issue asal dipertahankan; ledger IN baru memakai nilai issue lama dan
moving average existing. Akun master harus cocok snapshot (tidak reklasifikasi
diam-diam). Issue ditandai reversed_at/reversal_number, retry ditolak. Semua issue
dibalik membuat order CANCELED; order tidak dipakai ulang. Tidak ada jurnal baru
untuk issue karena issue awal hanya gerak fisik tanpa jurnal keuangan.

Receipt menolak issue reversed. Receipt/reversal journal disegel setelah semua
detail selesai. Guard model dan builder Eloquent header/detail menolak update,
delete, atau insert detail ke jurnal sealed. Service membalik lewat jurnal BARU,
bukan mengubah jurnal sealed. Metadata/ledger asli tetap tersimpan.

Migration 2026_10_02_080000 menambah maklun_sealed header dan reversal metadata issue.
Tidak dijalankan pada DB operasional, tanpa backfill jurnal/issue lama. Semua
migration maklun harus ditinjau bersama sebelum rollout; flag tetap nonaktif.

## Batas

Guard aplikasi bukan database immutability: DB::table/raw SQL/toBase/upsert/direct
SQL dapat melewati event/builder, begitu juga akses administrator database. Audit
semua jalur raw SQL dan privilege database diperlukan sebelum klaim immutable
produksi. Source receipt lama tanpa snapshot tidak dapat reverse otomatis.
Ledger/issue model belum punya guard universal terhadap perubahan langsung.
Partial return, return reason/approval UI, period lock, segregasi role dan audit
log immutable belum diimplementasikan. Endpoint void memakai auth existing.

Test lifecycle membuktikan full knitting/processing receipt → reversal → issue
return, original preservation, repeat rejection dan Eloquent sealed guards.
Stock/value memakai helper moving-average existing; test concurrency MySQL dan
rekonsiliasi inventory GL sementara bahan di subcontractor tetap perlu Finance.