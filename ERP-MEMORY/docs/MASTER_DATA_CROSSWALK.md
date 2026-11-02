# MASTER_DATA_CROSSWALK.md — Crosswalk Master Data Lintas Sistem

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (bab 10)

---

## 1. TUJUAN

Crosswalk memetakan entitas master dari sistem sumber (Addonan, Payment Plan v33, dll.) ke target ERP Marvel-Dard sehingga tidak ada master yang diimpor tanpa mapping yang sah.

---

## 2. TABEL CROSSWALK

| Kolom | Deskripsi |
|-------|-----------|
| `company_id` | ID perusahaan di ERP target |
| `source_system` | Sistem sumber (misal: `addonan`, `v33`) |
| `entity_type` | Tipe entitas (misal: `company`, `coa`, `party`, `material`, `bank`) |
| `source_id` | ID entitas di sistem sumber |
| `target_id` | ID entitas di ERP target |
| `raw_code` | Kode asli dari sistem sumber |
| `status` | `unresolved`, `mapped`, `rejected` |
| `confidence` | Tingkat kepercayaan mapping |
| `approved_by` | User yang menyetujui mapping |
| `approved_at` | Tanggal persetujuan |

**Constraint unique:** `company_id` + `source_system` + `entity_type` + `source_id`

**Aturan:** Status `unresolved` memblokir aktivasi, tetapi tidak menghapus baris crosswalk.

---

## 3. MASTER YANG PERLU CROSSWALK

| Entitas | Sumber | Target ERP | Catatan |
|---------|--------|------------|---------|
| Company / tenant | Addonan / v33 | `companies` | Petakan kode MGC/MGI ke ID perusahaan yang sah. Jangan menyamakan string label dengan identitas tenant. Default company wajib eksplisit. |
| COA / akun bantu | Addonan / v33 | `chart_of_accounts` | Pertahankan COA ERP. Map kode Addonan ke `account_id` ERP, simpan kode asli. Validasi detail, aktif, normal balance, mata uang dan tanggal efektif. |
| Supplier / customer | Addonan / v33 | `parties` | Satu party master dengan role supplier/customer, rekening terverifikasi, NPWP bila dibutuhkan, termin dan alamat. Nama sama bukan alasan auto-merge. |
| Material / varian | Addonan / v33 | `items` / `item_variants` | Kode canonical + legacy alias + category/subgroup + warna + ukuran exact decimal + unit dan conversion + HS/BC + COA. Simpan raw source. |
| Bank / kurs | v33 | `bank_accounts` | Currency, account_id GL, payment policy effective date, beneficiary validation, sumber kurs/tanggal. |
| User / role | v33 | `users` / `roles` | User ERP satu otoritas; map role v33 tanpa membuat identitas approval palsu. |

---

## 4. ATURAN MATERIAL (DARI AUDIT BAB 10)

| Aturan | Detail |
|--------|--------|
| Warna dan ukuran | Atribut eksplisit, tidak ditebak dari kode lama |
| Ukuran desimal | Simpan sebagai decimal/string canonical dengan unit; 0,125 tidak boleh dibulatkan menjadi 0,13 tanpa kebijakan |
| Konversi unit | Harus > 0 |
| Identitas material | Spesifikasi yang benar-benar membedakan barang |
| Import anomali | Masuk quarantine dan tetap terhitung dalam coverage |

---

## 5. ATURAN REKENING (DARI AUDIT BAB 09)

| Rekening | ZIP v33 | Implikasi Target |
|----------|---------|------------------|
| Mandiri IDR 1 - ...0699 9 | Bukan sumber bayar | Tetap master/saldo; jangan aktifkan tanpa keputusan mandat |
| Mandiri IDR 2 - ...6888 9 | Bukan sumber bayar | Tetap master/saldo |
| Mandiri IDR 3 - ...8778 9 | Sumber bayar IDR | Default sesuai v33; gate konfirmasi mandat |
| Mandiri USD 1 - ...8699 9 | Sumber bayar USD | Default sesuai v33; gate konfirmasi mandat |
| Mandiri USD 2 - ...6678 9 | Bukan sumber bayar | Konflik dengan rencana historis PV; perlu diputuskan |

**Keputusan:** Jangan membalik diam-diam. Ubah hardcode menjadi policy bertanggal efektif setelah mandat disahkan; pertahankan snapshot rekening lama pada transaksi historis.

---

## 6. DISCOVERY SEBELUM CODING

Sebelum mengubah master data:
1. Baca `app/Models/` untuk model master
2. Baca `database/migrations/` untuk struktur tabel master
3. Baca `app/Modules/` untuk service master
4. Baca `INVARIANTS.md` (INV-38 untuk crosswalk)
# MASTER_DATA_CROSSWALK.md — Crosswalk Master Data Lintas Sistem

> Last Updated: 2026-11-02
> Branch: `feat/marvel-dard-integration`
> Sumber: `d:/Project ERP/Audit_Penggabungan_ERP_Marvel_Dard_Addonan_Payment_Plan_v33.txt` (bab 10)

---

## TUJUAN

Satu sumber kebenaran untuk master data lintas sistem. Tidak mengimpor master tanpa crosswalk. Setiap entri sumber dari Addonan atau Payment Plan v33 dipetakan ke target ERP melalui crosswalk dengan company scope.

---

## TABEL CROSSWALK

| Kolom | Tipe | Deskripsi |
|-------|------|-----------|
| `company_id` | integer / UUID | ID perusahaan tujuan di ERP |
| `source_system` | string | Sistem sumber (Addonan, v33, legacy, dll) |
| `entity_type` | string | Jenis entitas (company, coa, party, material, bank, user, role) |
| `source_id` | string | ID entitas di sistem sumber |
| `target_id` | integer / UUID | ID entitas target di ERP (nullable jika unresolved) |
| `raw_code` | string | Kode asli dari sistem sumber |
| `status` | enum | `unresolved`, `mapped`, `rejected`, `merged` |
| `confidence` | decimal | Tingkat keyakinan pemetaan (0-1) |
| `approved_by` | integer / UUID | User yang menyetujui pemetaan |
| `approved_at` | timestamp | Waktu persetujuan |

**Constraint:** Unique pada `company_id` + `source_system` + `entity_type` + `source_id`

**Aturan:** Status `unresolved` memblokir aktivasi, bukan menghapus baris.

---

## MASTER DATA YANG DIPETAKAN

| Entity | Sumber | Target ERP | Catatan |
|--------|--------|------------|---------|
| Company / Tenant | MGC / MGI | `companies` | Kode MGC/MGI dipetakan ke ID perusahaan yang sah; jangan menyamakan string label dengan identitas tenant |
| COA / Akun Bantu | Addonan / v33 | `chart_of_accounts` | Map kode Addonan ke `account_id` ERP; simpan kode asli. Validasi detail, aktif, normal balance, mata uang, tanggal efektif |
| Supplier / Customer | Addonan | `parties` | Satu party master dengan role supplier/customer, rekening terverifikasi, NPWP bila dibutuhkan, termin dan alamat. Nama sama bukan alasan auto-merge |
| Material / Varian | Addonan | `items` | Kode canonical + legacy alias + category/subgroup + warna + ukuran exact decimal + unit dan conversion + HS/BC + COA. Simpan raw source |
| Lokasi / Gudang / Rak | Addonan | `warehouses`, `racks` | Stock ledger wajib lokasi, gudang/rak, lot dan ownership bila relevan |
| Bank / Kurs | v33 | `bank_accounts` | Currency, account_id GL, payment policy effective date, beneficiary validation, sumber kurs/tanggal |
| User / Role | v33 / ERP | `users`, `roles` | User ERP satu otoritas; map role v33 tanpa membuat identitas approval palsu |

---

## ATURAN PRECISI DAN DATA

1. **Warna dan ukuran** harus menjadi atribut eksplisit, bukan ditebak hanya dari kode lama
2. **Ukuran desimal** simpan sebagai decimal/string canonical dengan unit; `0,125` tidak boleh dibulatkan menjadi `0,13` tanpa kebijakan
3. **Konversi unit** harus > 0
4. **Identitas material** memakai spesifikasi yang benar-benar membedakan barang
5. **Import anomali** masuk quarantine dan tetap terhitung dalam coverage
6. Kode akun display tidak boleh menjadi satu-satunya foreign key

---

## MANDAT REKENING (v33 vs Rencana Lama)

| Rekening | Sumber ZIP v33 | Status | Catatan |
|----------|---------------|--------|---------|
| Mandiri IDR 1 - ...0699 9 | Bukan sumber bayar | Tetap master/saldo | Jangan aktifkan tanpa keputusan mandat |
| Mandiri IDR 2 - ...6888 9 | Bukan sumber bayar | Tetap master/saldo | |
| Mandiri IDR 3 - ...8778 9 | Sumber bayar IDR | Default sesuai v33 | Gate konfirmasi mandat |
| Mandiri USD 1 - ...8699 9 | Sumber bayar USD | Default sesuai v33 | Gate konfirmasi mandat |
| Mandiri USD 2 - ...6678 9 | Bukan sumber bayar | Tetap master/saldo | Konflik dengan rencana historis PV; perlu diputuskan |

Rencana historis membagi IDR 1 / USD 2 sebagai pengeluaran; ZIP v33 secara eksplisit memilih IDR 3 / USD 1 dan menormalkan flag server. Jangan membalik diam-diam. Ubah hardcode menjadi policy bertanggal efektif setelah mandat disahkan; pertahankan snapshot rekening lama pada transaksi historis.

---

## FILE TERKAIT

- `docs/MENU_MAP.md` — Pemetaan menu ke target ERP
- `docs/IMPLEMENTATION_ROADMAP.md` — Tahapan B01–B12
- `docs/EXIM_CUSTOMS_RULES.md` — Aturan EXIM dan dokumen pabean