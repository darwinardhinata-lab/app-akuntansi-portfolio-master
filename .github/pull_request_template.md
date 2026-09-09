## 📋 Deskripsi

<!-- Jelaskan perubahan yang dilakukan dalam 2-3 kalimat -->

## 🎯 Tipe Perubahan

<!-- Centang yang sesuai -->

- [ ] 🐛 Bug fix (perubahan yang memperbaiki issue)
- [ ] ✨ Fitur baru (perubahan yang menambah fungsionalitas)
- [ ] 🔧 Refactoring (perubahan kode tanpa menambah fitur/fix bug)
- [ ] 📝 Dokumentasi (perubahan pada dokumentasi)
- [ ] 🎨 Style (formatting, missing semi colons, dll)
- [ ] ⚡️ Performance (perubahan yang memperbaiki performa)
- [ ] 🌐 i18n/Translations (perubahan pada multi-bahasa)
- [ ] 🔒 Security (perubahan yang mengatasi vulnerability)

## 🏭 Modul yang Terpengaruh

<!-- Centang semua yang relevan -->

- [ ] Accounting (Jurnal, COA, Neraca, Laba Rugi)
- [ ] Sales (SO, INV, Retur Penjualan)
- [ ] Purchase (PO, BIL, PR, Retur Pembelian)
- [ ] Warehouse (Inbound, Outbound, Stock Opname)
- [ ] Manufacturing (SPK, MRN, Knitting, Processing, Cutting, Stitching)
- [ ] Product (Master Barang, Kategori)
- [ ] Asset (Aset Tetap, Penyusutan)
- [ ] Payment Plan (Hutang, Uang Muka, Deposit)
- [ ] Reports (Laporan, Export Excel)
- [ ] Settings (Konfigurasi, Pajak, Divisi)
- [ ] Other: ___________

## 🌐 Checklist i18n (WAJIB untuk perubahan UI)

<!-- Jika perubahan melibatkan UI/UX, centang semua -->

- [ ] Semua string UI baru memakai `__('erp.key')`
- [ ] Key baru ditambahkan di `lang/id/erp.php` (fallback)
- [ ] Key baru ditambahkan di `lang/en/erp.php`
- [ ] Key baru ditambahkan di `lang/zh_CN/erp.php`
- [ ] Tidak ada concat string — pakai placeholder `:variable`
- [ ] Exception message ke user sudah i18n
- [ ] Log internal (`\Log::`) tetap bahasa Indonesia/Inggris baku
- [ ] Export Excel headers sudah i18n (jika ada fitur export)
- [ ] Test di 3 bahasa (ID/EN/ZH_CN) sebelum merge
- [ ] Jalankan `php artisan lang:check-missing` — tidak ada missing keys

## 🧪 Testing

<!-- Jelaskan bagaimana perubahan ini sudah ditest -->

### Test Cases

1. **Test Case 1**: ___________
   - [ ] Pass
   - Steps: ___________
   - Expected: ___________
   - Actual: ___________

2. **Test Case 2**: ___________
   - [ ] Pass
   - Steps: ___________
   - Expected: ___________
   - Actual: ___________

### Manual Testing

- [ ] Test di browser Chrome
- [ ] Test di browser Firefox
- [ ] Test di mobile responsive
- [ ] Test dengan data real (bukan dummy)
- [ ] Test edge cases (empty state, error state, large data)

### Automated Testing

- [ ] Unit tests added/updated
- [ ] Feature tests added/updated
- [ ] All tests passing (`php artisan test`)

## 📸 Screenshots (jika ada perubahan UI)

<!-- Sertakan screenshot sebelum dan sesudah -->

### Sebelum
![Before](url_gambar)

### Sesudah
![After](url_gambar)

## 🔗 Related Issues

<!-- Link ke issue yang diselesaikan -->

Closes #___
Related to #___

## 📝 Notes for Reviewer

<!-- Catatan khusus untuk reviewer, jika ada -->

- Point yang perlu perhatian khusus: ___________
- Trade-offs yang diambil: ___________
- Future improvements (jika ada): ___________

## ✅ Pre-Merge Checklist

<!-- Reviewer centang sebelum merge -->

- [ ] Code review completed
- [ ] All conversations resolved
- [ ] CI/CD pipeline passing
- [ ] No merge conflicts
- [ ] Documentation updated (jika perlu)
- [ ] Database migrations reviewed (jika ada)
- [ ] Performance impact assessed
- [ ] Security implications reviewed

## 🚀 Deployment Notes

<!-- Instruksi khusus untuk deployment, jika ada -->

- [ ] Requires database migration
- [ ] Requires cache clear
- [ ] Requires config clear
- [ ] Requires queue restart
- [ ] Requires environment variable update

**Migration commands** (jika ada):
```bash
php artisan migrate
```

**Post-deployment commands** (jika ada):
```bash
php artisan cache:clear
php artisan config:clear
php artisan queue:restart
```

---

**Reviewer**: @___
**Approved by**: @___
**Merged by**: @___
**Merge date**: ____-__-__
