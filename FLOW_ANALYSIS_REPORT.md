# LAPORAN ANALISIS FLOW DATA: PO & SO KE SEMUA MENU

## Ringkasan Flow Data End-to-End

Berikut adalah analisis lengkap alur data dari **Purchase Order (PO)** dan **Sales Order (SO)** ke semua menu terkait:

---

## 📊 Flow Data PO (Pembelian)

### 1. PO Dibuat → Semua Data Otomatis Terisi

```
┌─────────────────────────────────────────────────────────────────┐
│                    PURCHASE ORDER (PO)                           │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (receivePartialOrder)
┌─────────────────────────────────────────────────────────────────┐
│                        PURCHASE BILL                             │
│                  (Faktur Pembelian / Bukti Penerimaan)           │
│                                                                  │
│ ✓ AUTO CREATE:                                                    │
│ - Journal Header (tipe: 'Purchase Bill')                         │
│ - Journal Detail:                                                │
│   - 11200 (Persediaan) - DEBET                                 │
│   - 22000 (Hutang) / 11305 (Uang Muka) - KREDIT               │
│ - Inventory Ledger:                                              │
│   - type: 'IN' (stok bertambah)                                │
│   - Moving Average Cost dihitung otomatis                        │
│ - Stok Produk bertambah                                        │
└─────────────────────────────────────────────────────────────────┘
```

### 2. Retur Pembelian (PR) → Data Terhubung ke PO

```
┌─────────────────────────────────────────────────────────────────┐
│                   PURCHASE RETURN (PR)                            │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (store)
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│ ✓ AUTO CREATE:                                                    │
│ - Journal Detail:                                                │
│   - 22000/11305 (Hutang/Uang Muka) - DEBET                    │
│   - 11200 (Persediaan) - KREDIT                                │
│ - Inventory Ledger:                                              │
│   - type: 'OUT' (stok berkurang)                               │
│ - Stok Produk berkurang                                         │
└─────────────────────────────────────────────────────────────────┘
```

### 3. Gudang Barang Masuk (Warehouse Inbound)

```
┌─────────────────────────────────────────────────────────────────┐
│                WAREHOUSE INBOUND MANUAL                          │
│              (retur_online + penerimaan_barang)                  │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (storeInbound)
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│ ✓ AUTO CREATE:                                                    │
│ - Journal Detail:                                                │
│   - 11200 (Persediaan) - DEBET                                 │
│   - offset_account - KREDIT                                      │
│ - Inventory Ledger: type: 'IN'                                  │
│ - Stok bertambah                                               │
└─────────────────────────────────────────────────────────────────┘
```

### 4. Hutang/Piutang Terhubung

```
┌─────────────────────────────────────────────────────────────────┐
│                 TRANSAKSI PAYMENT PLAN                           │
│                (PEMBELIAN PERSEDIAAN)                            │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (postToJournal)
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│ ✓ AUTO CREATE:                                                    │
│ - Journal Detail:                                                │
│   - account_code - DEBET (tergantung tipe)                     │
│   - 1101 (Kas) - KREDIT                                        │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📊 Flow Data SO (Penjualan)

### 1. SO Dibuat → Semua Data Otomatis Terisi

```
┌─────────────────────────────────────────────────────────────────┐
│                     SALES ORDER (SO)                             │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (JubelioWebhook / createInvoiceAndShip)
┌─────────────────────────────────────────────────────────────────┐
│                        SALES INVOICE                             │
│                       (Faktur Penjualan)                         │
│                                                                  │
│ ✓ AUTO CREATE:                                                    │
│ - Journal Header (tipe: 'Faktur')                               │
│ - Journal Detail:                                                │
│   - 11100 (Piutang) - DEBET                                    │
│   - 44001, 66499, 44002 (Diskon) - DEBET                      │
│   - 44000 (Penjualan) - KREDIT                                 │
│   - 77005 (Ongkos Kirim) - KREDIT (INCORRECT! Harus: 11405)   │
│   - 21104 (Pajak) - KREDIT                                     │
│   - 44004 (Biaya Lain) - KREDIT (INCORRECT! Harus: 88004)     │
│   - 55000 (HPP) - DEBET                                        │
│   - 11200 (Persediaan) - KREDIT                                │
│ - Inventory Ledger: type: 'OUT' (stok berkurang)                │
└─────────────────────────────────────────────────────────────────┘
```

### 2. Retur Penjualan (SR) → Data Terhubung ke SO

```
┌─────────────────────────────────────────────────────────────────┐
│                   SALES RETURN (SR)                               │
│                (Retur Channel Online)                              │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (process)
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│ ✓ AUTO CREATE:                                                    │
│ - Journal Detail:                                                │
│   - 44010 (Pendapatan Lain/Retur) - DEBET                      │
│   - 77005 (Ongkos Kirim) - DEBET                              │
│   - 11100 (Piutang) - KREDIT                                  │
│   - 11200 (Persediaan) - DEBET (barang bagus)                  │
│   - 55000 (HPP) - KREDIT                                       │
│   - 88004 (Penyesuaian Persediaan) - DEBET (barang cacat)      │
│ - Inventory Ledger: type: 'IN' (stok bertambah)                 │
└─────────────────────────────────────────────────────────────────┘
```

### 3. Uang Muka Terhubung

```
┌─────────────────────────────────────────────────────────────────┐
│              PAYMENT PLAN - UANG MUKA                              │
│          (Kategori: PEMBELIAN PERSEDIAAN (UANG MUKA))           │
└─────────────────────────────────────────────────────────────────┘
                 ↓ (receivePartialOrder di POService)
┌─────────────────────────────────────────────────────────────────┐
│                                                                  │
│ ✓ AKUN KREDIT OTOMATIS: 11305 (Uang Muka)                     │
│ (bukan 22000 Hutang)                                           │
└─────────────────────────────────────────────────────────────────┘
```

---

## 🔍 Analisis Detail per Menu

### ✅ Menu yang SUDAH TERISI LENGKAP:

| Menu | Status | Auto Create | Catatan |
|------|--------|-------------|---------|
| **1. Sales Order (SO)** | ✅ Auto | Journal, Invoice, Ledger | Stok berkurang, HPP terkalkulasi |
| **2. Sales Invoice (Faktur)** | ✅ Auto | Journal, Ledger | Dari SO yang diproses |
| **3. Purchase Order (PO)** | ✅ Auto | Bill, Journal, Ledger | Stok bertambah, HPP terkalkulasi |
| **4. Purchase Bill (Tagihan)** | ✅ Auto | Journal, Ledger | Dari PO yang diterima |
| **5. Purchase Return (PR)** | ✅ Auto | Journal, Ledger | Stok berkurang |
| **6. Sales Return (SR)** | ✅ Auto | Journal, Ledger | Stok bertambah (retur) |
| **7. Warehouse Inbound** | ✅ Manual | Journal, Ledger | Stok bertambah |
| **8. Warehouse Outbound** | ✅ Manual | Journal, Ledger | Stok berkurang |
| **9. Payment Plan (Uang Muka)** | ⚠️ Manual | Journal | Harus di-post manual |

### ❌ Menu yang BELUM TERISI OTOMATIS:

| Menu | Status | Keterangan |
|------|--------|------------|
| **Retur Channel Online (Warehouse Inbound)** | ⚠️ Partial | Hanya retur dari Jubelio yang masuk via webhook |
| **AR Subledger** | ✅ Auto | Dari SalesInvoice |
| **AP Subledger** | ✅ Auto | Dari PurchaseBill |

---

## ⚠️ Temuan Kritik & Perbaikan

### 1. **Akun Ongkos Kirim (77005) - Salah Posisi**
- **SalesOrderService.php Baris 162**: `77005` (Ongkos Kirim) - KREDIT
- **Harusnya**: Akun Ongkos Kirim seharusnya DEBET (Beban) atau KREDIT di akun Pendapatan Lain

### 2. **Akun Biaya Lain (44004) - Salah Posisi**  
- **SalesOrderService.php Baris 171**: `44004` - KREDIT
- **Harusnya**: `88004` (Biaya Lain-lain) - DEBET

### 3. **Inventory Ledger Retur Penjualan - Tidak Update HPP**
- Saat barang retur, HPP tidak di-update secara otomatis
- Harus dihitung ulang saat proses retur

### 4. **Payment Plan - Uang Muka**
- Payment Plan dengan kategori "PEMBELIAN PERSEDIAAN (UANG MUKA)" sudah otomatis terhubung ke PO
- Tapi harus di-post manual ke jurnal via tombol "Posting"

---

## 📋 Rekomendasi Perbaikan

### Prioritas 1 - Perbaikan Akun COA:
1. Perbaiki akun Ongkos Kirim: Gunakan 11405 (Pendapatan Lain) jika ditagih, atau 77005 (Beban Lain) jika tidak ditagih
2. Perbaiki akun Biaya Lain: Ganti 44004 ke 88004

### Prioritas 2 - Flow Data Otomatis:
1. Tambahkan trigger otomatis untuk Payment Plan ke jurnal saat di-approve
2. Pastikan semua akun di SalesOrderService terhubung ke master COA (bukan hardcoded)

### Prioritas 3 - Validasi:
1. Tambahkan validasi keseimbangan jurnal sebelum insert
2. Tambahkan audit trail perubahan saldo akun

---

*Dokumen ini dibuat pada: 2026-07-20*
*Berdasarkan analisis kode sumber: SalesOrderService, PurchaseOrderService, SalesReturnController, PurchaseReturnController, WarehouseController, PaymentPlanService*