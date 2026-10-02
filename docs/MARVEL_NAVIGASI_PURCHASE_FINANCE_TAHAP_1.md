# Marvel: navigasi Purchase dan Finance — tahap 1

## Lingkup

Penyelarasan sidebar berdasarkan inventaris visual Marvel, hanya untuk fitur yang
sudah memiliki route. Ini bukan implementasi seluruh workflow Marvel atau audit
kelengkapan backend. URL, controller, otorisasi, database, dan jurnal tidak berubah.
ID collapse `sectionOutflow`, `sectionAkuntansi`, dan `menuApHutang` dipertahankan.
Link GRN hanya tampil ketika `platform.order_company_scope_enabled` aktif,
sesuai prasyarat controller. Flag `platform.grn_enabled` tidak menjadi syarat
melihat histori: receipt existing tetap dapat ditinjau saat receiving baru nonaktif.
Flag deployment tidak diubah oleh penyelarasan navigasi.

## Pemetaan

| Target / fitur | Route existing | Posisi sidebar |
|---|---|---|
| Purchase Order | `po.index` | Purchase |
| Purchase Receipt | `grn.index` | Purchase (link baru ke fitur existing) |
| Purchase Return | `purchase-returns.index` | Purchase |
| AP Purchase Invoice | `purchase-bills.index` | Finance / AP; label Purchase Bill dipertahankan |
| Payment Plan (ekstensi existing) | `payment.index` | Finance / AP; bukan klaim Payment Posting Marvel |
| Journal Transaction | `jurnal.index` | Finance / Journal Entry |
| COA, kode bantu, pajak | `account.index`, `helper.index`, `tax.index` | Finance / Master Data |
| General Ledger | `buku-besar.index` | Finance / Reports |
| Profit & Loss | `laba-rugi.index` | Finance / Reports; tab existing dipertahankan |
| Balance Sheet | `neraca.index` | Finance / Reports; tab existing dipertahankan |
| Cash Flow | `arus-kas.index` | Finance / Reports; direct/indirect dipertahankan |
| AP/DP, AP subledger | `reports.ap_dp`, `reports.ap_subledger` | Finance / Reports |
| Aset dan depresiasi legacy | `aset.index`, `aset.list` | Finance; tidak dipecah menjadi modul aset Marvel |
| HPP dan tag legacy | `reports.cogs`, `reports.tags` | Finance / Reports |

Menu Sales/AR, manufaktur, inventory, customs, dan platform tidak dipindahkan.
PR/approval, recurring journal, fiscal lock, closing, dan menu target lain tidak
ditambahkan sebagai placeholder. Fitur tersebut memerlukan discovery tersendiri;
ketiadaan link baru tidak menyatakan backend pasti belum tersedia.

## Validasi

- Regression test render Blade untuk ID/EN/zh_CN dan keberadaan route/link.
- Active state serta pembukaan Purchase/Finance berdasarkan route halaman.
- Link Bill/Payment/AP tidak lagi berada di Purchase; aset legacy tetap tersedia.
- Cash Flow Indirect hanya aktif di route Cash Flow dengan tab indirect.
- Jalankan PHPUnit menggunakan SQLite in-memory, bukan konfigurasi MySQL operasional.
- UAT browser diperlukan untuk desktop/mobile, collapse, tema, dan Google Translate.

## Batas keamanan

Tidak ada migration/seeder, perubahan data, posting/void, numbering, approval,
scope company, atau RBAC backend. Cookie lampiran tidak digunakan.
Commit/push dilakukan hanya atas instruksi terpisah.