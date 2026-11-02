# SETUP_INSTALLATION.md — Panduan Setup Lokal ERP Marvel-Dard

> Last Updated: 2026-11-02  
> Branch: `feat/marvel-dard-integration`  
> OS: Windows (XAMPP), PowerShell

---

## PRASYARAT

| Perangkat | Versi | Catatan |
|---|---|---|
| PHP | 8.2.12 | Via XAMPP |
| Composer | Terbaru | Untuk dependency PHP |
| MySQL | 8.x | Via XAMPP |
| Node.js / npm | Terbaru | Untuk Vite assets (opsional untuk backend) |
| Git | Terbaru | Untuk version control |

---

## LANGKAH INSTALASI

```powershell
# 1. Clone repository
git clone https://github.com/darwinardhinata-lab/app-akuntansi-portfolio-master.git
cd app-akuntansi-portfolio-master
git checkout feat/marvel-dard-integration

# 2. Salin .env.example ke .env (jika ada)
# Jika tidak ada .env.example, buat .env manual dengan konfigurasi DB lokal
copy .env.example .env  # atau buat .env baru

# 3. Install dependency PHP
composer install

# 4. Generate application key
php artisan key:generate

# 5. Konfigurasi database di .env
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=erp_marvel_dard
# DB_USERNAME=root
# DB_PASSWORD=

# 6. Jalankan migrasi
php artisan migrate

# 7. Jalankan seeder (jika ada)
php artisan db:seed

# 8. Build assets frontend (opsional, hanya jika mengubah UI)
npm install
npm run build

# 9. Jalankan server pengembangan
php artisan serve
# Akses: http://127.0.0.1:8000
```

---

## VARIABEL .env PENTING

| Variabel | Contoh Nilai | Keterangan |
|---|---|---|
| `APP_NAME` | `ERP Marvel-Dard` | Nama aplikasi |
| `APP_ENV` | `local` | local / production |
| `APP_DEBUG` | `true` | Aktifkan debug mode lokal |
| `DB_CONNECTION` | `mysql` | Driver database |
| `DB_HOST` | `127.0.0.1` | Host MySQL |
| `DB_PORT` | `3306` | Port MySQL |
| `DB_DATABASE` | `erp_marvel_dard` | Nama database |
| `DB_USERNAME` | `root` | User MySQL |
| `DB_PASSWORD` | `` | Password MySQL (kosong untuk XAMPP default) |
| `platform.posting_intents_enabled` | `false` | Aktifkan posting intents (default OFF) |

---

## CARA MENJALANKAN TEST

```powershell
# Full suite
php vendor/phpunit/phpunit/phpunit --no-progress

# Hanya Payment Plan
php vendor/phpunit/phpunit/phpunit tests/Feature/PaymentPlanBankHttpTest.php --no-progress

# Integration test (butuh MySQL)
$env:AP_MYSQL_TESTS='1'
php vendor/phpunit/phpunit/phpunit tests/Integration/PaymentPlanMakerMysqlTest.php --no-progress

# Lint satu file PHP
php -l <path_file>
```

---

## TROUBLESHOOTING

| Masalah | Solusi |
|---|---|
| `Class not found` setelah composer install | Jalankan `composer dump-autoload` |
| Migration gagal (FK constraint) | Pastikan urutan migrasi benar, tabel referensi sudah ada |
| `.env` tidak ditemukan | Salin `.env.example` atau buat manual |
| Test integration gagal | Pastikan `AP_MYSQL_TESTS=1` dan MySQL berjalan |
| Asset build gagal | Jalankan `npm install` lalu `npm run build` |

_Last Updated: 2026-11-02_