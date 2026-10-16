<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## Akses development melalui Wi-Fi lokal (Windows)

Dari folder project, jalankan script berikut di PowerShell:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\serve-lan.ps1
```

Untuk menggunakan port lain:

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\serve-lan.ps1 -Port 8080
```

Script menggunakan PHP dari PATH, atau PHP XAMPP jika project berada di
`<xampp>\htdocs\<project>`. Laravel dijalankan pada `0.0.0.0` dengan port bawaan
`8000`. Buka URL IP lokal yang ditampilkan script dari HP/laptop pada Wi-Fi yang
sama (bukan `0.0.0.0` atau `localhost`). Jika ada beberapa IP, pilih interface
Wi-Fi/Ethernet yang digunakan. Hentikan server dengan **Ctrl+C**. Port yang sudah
dipakai akan menghasilkan error, bukan pindah port otomatis.

Script tidak mengubah `.env`, cache konfigurasi, atau firewall. Jika tidak bisa
diakses, izinkan TCP pada port pilihan melalui Windows Firewall hanya untuk
profil **Private**, idealnya dengan cakupan **Local subnet**; jangan matikan
firewall. Periksa juga AP/client isolation pada router. Gunakan hanya jaringan
tepercaya: server development ini bukan untuk production atau akses internet.

Jika aset frontend menggunakan Vite dan tidak tampil di HP, hentikan Vite lokal
dan jalankan `npm run build` (pastikan file `public/hot` dari dev server tidak
tertinggal). Script ini hanya menjalankan Laravel, bukan Vite atau queue worker.
Jika redirect/URL absolut masih menunjuk ke localhost, sesuaikan `APP_URL` di
`.env` dengan URL IP lokal dan jalankan `php artisan config:clear`. Autentikasi
Sanctum lintas origin juga dapat memerlukan konfigurasi domain stateful, CORS,
dan cookie; mengubah `APP_URL` saja tidak selalu cukup.

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
