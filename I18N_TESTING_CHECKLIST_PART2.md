# i18n Testing Checklist — Part 2: Advanced Testing

> **Status: [WAJIB]** — Checklist ini harus dilalui sebelum deployment ke production.

---

## 5. Edge Cases Testing

### 5.1 Validation Errors

**Test Scenario:**
1. Buka form create jurnal
2. Submit tanpa isi field wajib
3. Cek error messages

- [ ] Required field error terjemahan
- [ ] Format error terjemahan
- [ ] Date validation error terjemahan

### 5.2 Database Errors

**Test Scenario:**
1. Coba create jurnal dengan debet ≠ kredit
2. Cek error message

- [ ] "Jurnal tidak seimbang" error terjemahan

### 5.3 Import Errors

**Test Scenario:**
1. Upload CSV dengan format salah
2. Cek error message

- [ ] Import error message terjemahan
- [ ] File format error terjemahan

### 5.4 Empty States

**Test Scenario:**
1. Buka halaman dengan data kosong (filter yang tidak match)
2. Cek empty state message

- [ ] "No data available" / "Tidak ada data" terjemahan

### 5.5 Delete Confirmation

**Test Scenario:**
1. Klik tombol "Hapus" di mana saja
2. Cek SweetAlert confirmation

- [ ] Confirmation title terjemahan
- [ ] Confirmation text terjemahan
- [ ] Button "Ya, hapus!" / "Yes, delete!" / "是的，删除！" terjemahan
- [ ] Button "Batal" / "Cancel" / "取消" terjemahan

### 5.6 Success Messages

**Test Scenario:**
1. Create/update/delete data dengan sukses
2. Cek flash message

- [ ] Success create message terjemahan
- [ ] Success update message terjemahan
- [ ] Success delete message terjemahan

---

## 6. Print & Export Testing

### 6.1 Print PDF

**Test Scenario:**
1. Buka laporan Laba Rugi
2. Klik "Cetak"
3. Cek print preview

- [ ] Report title terjemahan
- [ ] Column headers terjemahan
- [ ] Date format sesuai locale
- [ ] Currency format sesuai locale

### 6.2 Export Excel

**Test Scenario:**
1. Buka daftar jurnal
2. Klik "Export Excel"
3. Buka file Excel

- [ ] Excel headers terjemahan
- [ ] Column names terjemahan
- [ ] Date format sesuai locale
- [ ] Number format sesuai locale

---

## 7. JavaScript Alerts Testing

### 7.1 SweetAlert Confirmations

**Test Scenario:**
1. Klik tombol "Hapus" di mana saja
2. Cek SweetAlert dialog

- [ ] Title terjemahan
- [ ] Text body terjemahan
- [ ] Confirm button terjemahan
- [ ] Cancel button terjemahan

### 7.2 Toast/Flash Messages

**Test Scenario:**
1. Perform CRUD operations
2. Cek toast notifications

- [ ] Success toast terjemahan
- [ ] Error toast terjemahan
- [ ] Warning toast terjemahan
- [ ] Info toast terjemahan

---

## 8. Final Scan & Verification

### 8.1 Final Hardcoded Scan

```bash
php artisan i18n:scan-hardcoded --verbose
```

- [ ] 0 hardcoded strings found (or only Tier 3 remaining)

### 8.2 Final Key Check

```bash
php artisan lang:check-missing
```

- [ ] All translations complete

### 8.3 Git Status Check

```bash
git status
```

- [ ] Review all changed files
- [ ] No unexpected changes
- [ ] All changes intentional

---

## 9. Sign-Off

### Testing Sign-Off

| Role | Name | Date | Signature |
|---|---|---|---|
| Developer | | | |
| QA Tester | | | |
| Product Owner | | | |

### Pre-Deployment Checklist

- [ ] All tests passed
- [ ] No critical bugs found
- [ ] Documentation updated
- [ ] Backup created
- [ ] Rollback plan ready
- [ ] Stakeholder approval received

---

## 📊 Testing Summary

| Category | Total Tests | Passed | Failed | Skipped |
|---|---|---|---|---|
| Pre-Testing Setup | 7 | | | |
| Cache & Syntax | 4 | | | |
| Key Completeness | 3 | | | |
| Manual UI Testing | 32 | | | |
| Edge Cases | 18 | | | |
| Print & Export | 6 | | | |
| JavaScript Alerts | 8 | | | |
| Final Scan | 3 | | | |
| **TOTAL** | **81** | **0** | **0** | **0** |

---

**Document Version:** 1.0  
**Last Updated:** 2026-09-09
