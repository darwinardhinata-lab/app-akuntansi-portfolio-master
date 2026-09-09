# i18n Testing Checklist — Production-Ready

> **Status: [WAJIB]** — Checklist ini harus dilalui sebelum deployment ke production.
> 
> **Estimasi waktu**: 2-3 jam untuk testing lengkap di 3 bahasa.

---

## Bagian 1: Setup & Core Testing

---

## 1. Pre-Testing Setup

### 1.1 Clear All Caches

```bash
php artisan view:clear
php artisan cache:clear
php artisan config:clear
php artisan route:clear
```

- [ ] View cache cleared
- [ ] Application cache cleared
- [ ] Config cache cleared
- [ ] Route cache cleared

### 1.2 Verify Language Files Syntax

```bash
php -l lang/id/erp.php
php -l lang/en/erp.php
php -l lang/zh_CN/erp.php
```

- [ ] `lang/id/erp.php` — No syntax errors
- [ ] `lang/en/erp.php` — No syntax errors
- [ ] `lang/zh_CN/erp.php` — No syntax errors

### 1.3 Compile Blade Views

```bash
php artisan view:cache
```

- [ ] All blade views compiled successfully
- [ ] No compilation errors

---

## 2. Cache & Syntax Validation

### 2.1 Check for Missing Keys

```bash
php artisan lang:check-missing --verbose
```

- [ ] 0 missing keys in `id`
- [ ] 0 missing keys in `en`
- [ ] 0 missing keys in `zh_CN`

### 2.2 Verify Manufacturing Label Helper

```bash
php -l app/Support/ManufacturingLabel.php
```

- [ ] ManufacturingLabel helper has no syntax errors

---

## 3. Key Completeness Check

### 3.1 Manufacturing Module Keys

Verify all 61 manufacturing keys exist in all languages:

```bash
php -r "
$id = include 'lang/id/erp.php';
$en = include 'lang/en/erp.php';
$zh = include 'lang/zh_CN/erp.php';
$idMfg = array_filter(array_keys($id), fn($k) => str_starts_with($k, 'mfg_'));
$enMfg = array_filter(array_keys($en), fn($k) => str_starts_with($k, 'mfg_'));
$zhMfg = array_filter(array_keys($zh), fn($k) => str_starts_with($k, 'mfg_'));
echo 'ID: ' . count($idMfg) . ' keys' . PHP_EOL;
echo 'EN: ' . count($enMfg) . ' keys' . PHP_EOL;
echo 'ZH: ' . count($zhMfg) . ' keys' . PHP_EOL;
"
```

- [ ] ID: 61+ manufacturing keys
- [ ] EN: 61+ manufacturing keys
- [ ] ZH: 61+ manufacturing keys

---

## 4. Manual UI Testing per Modul

### 4.1 Dashboard

**URL Testing:**
- [ ] `http://app.local/lang/id` — Dashboard dalam Bahasa Indonesia
- [ ] `http://app.local/lang/en` — Dashboard dalam English
- [ ] `http://app.local/lang/zh_CN` — Dashboard dalam 中文

**Checklist:**
- [ ] Sidebar menu terjemahan
- [ ] Widget titles terjemahan
- [ ] Chart labels terjemahan
- [ ] Button labels terjemahan

### 4.2 Jurnal (General Journal)

**URL Testing:**
- [ ] `http://app.local/jurnal` — List jurnal
- [ ] `http://app.local/jurnal/create` — Form create jurnal
- [ ] `http://app.local/jurnal/1/edit` — Form edit jurnal

**Checklist:**
- [ ] Table headers terjemahan (Tanggal, Keterangan, Akun, Debit, Kredit)
- [ ] Form labels terjemahan
- [ ] Placeholder input field terjemahan
- [ ] Button "Simpan/Save/保存" terjemahan
- [ ] Button "Batal/Cancel/取消" terjemahan

### 4.3 Sales Order

**URL Testing:**
- [ ] `http://app.local/sales-orders` — List SO
- [ ] `http://app.local/sales-orders/create` — Form create SO
- [ ] `http://app.local/sales-orders/1` — Detail SO

**Checklist:**
- [ ] Table headers terjemahan
- [ ] Status badges terjemahan (OPEN, COMPLETED, VOIDED)
- [ ] Form labels terjemahan
- [ ] Customer dropdown placeholder terjemahan

### 4.4 Purchase Order

**URL Testing:**
- [ ] `http://app.local/purchase-orders` — List PO
- [ ] `http://app.local/purchase-orders/create` — Form create PO
- [ ] `http://app.local/purchase-orders/1` — Detail PO

**Checklist:**
- [ ] Table headers terjemahan
- [ ] Status badges terjemahan
- [ ] Form labels terjemahan
- [ ] Supplier dropdown placeholder terjemahan

### 4.5 Manufacturing — Material Receipt (MRN)

**URL Testing:**
- [ ] `http://app.local/manufaktur/material-receipts` — List MRN
- [ ] `http://app.local/manufaktur/material-receipts/create` — Form create MRN
- [ ] `http://app.local/manufaktur/material-receipts/1` — Detail MRN

**Checklist:**
- [ ] Table headers terjemahan
- [ ] Form labels terjemahan (Kode Kain, Qty Diterima, dll)
- [ ] Status badges terjemahan
- [ ] Success/error messages terjemahan

### 4.6 Manufacturing — Work Orders (SPK)

**URL Testing:**
- [ ] `http://app.local/manufaktur/work-orders` — List SPK
- [ ] `http://app.local/manufaktur/work-orders/create` — Form create SPK
- [ ] `http://app.local/manufaktur/work-orders/1` — Detail SPK

**Checklist:**
- [ ] Table headers terjemahan
- [ ] Status badges terjemahan (OPEN, IN_PROGRESS, COMPLETED, VOIDED)
- [ ] Process type labels terjemahan (Knitting, Dyeing, Printing, dll)
- [ ] Form labels terjemahan

### 4.7 Manufacturing — Master Data

**URL Testing:**
- [ ] `http://app.local/manufaktur/master/yarn` — Master Yarn
- [ ] `http://app.local/manufaktur/master/fabric` — Master Fabric
- [ ] `http://app.local/manufaktur/master/supplier` — Master Supplier
- [ ] `http://app.local/manufaktur/master/process` — Master Rate Proses

**Checklist:**
- [ ] Table headers terjemahan
- [ ] Form labels terjemahan
- [ ] Button labels terjemahan

### 4.8 Reports

**URL Testing:**
- [ ] `http://app.local/laba-rugi` — Laba Rugi
- [ ] `http://app.local/neraca` — Neraca
- [ ] `http://app.local/buku-besar` — Buku Besar

**Checklist:**
- [ ] Report titles terjemahan
- [ ] Column headers terjemahan
- [ ] Filter labels terjemahan
- [ ] Export button terjemahan

---

**Document Version:** 1.0  
**Last Updated:** 2026-09-09
