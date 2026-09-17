<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator; // <-- 1. WAJIB PANGGIL CLASS INI
use Illuminate\Support\Facades\Gate;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 2. PAKSA LARAVEL MENGGUNAKAN TAMPILAN BOOTSTRAP 5
        Paginator::useBootstrapFive();

        // 3. BLADE DIRECTIVE UNTUK LINKIFY NOMOR DOKUMEN DALAM TEKS
        \Illuminate\Support\Facades\Blade::directive('linkify', function ($expression) {
            return "<?php echo \\App\\Support\\DocumentLinkify::render($expression); ?>";
        });

        // 4. FIX (H3 / Fase 1-B): Gate untuk modul Customs.
        // Catatan RBAC: TIDAK ditemukan sistem Gate/permission yang baku di proyek ini
        // (tidak ada Gate::define lain, tidak ada tabel permission; akses admin di
        // UserController dijaga manual di controller). Gate ini sengaja dibuat PERMISIF
        // (semua user login boleh) sebagai perbaikan minimal supaya tombol aksi tidak
        // terkunci total — TODO: ganti dengan role/permission check setelah ada keputusan
        // RBAC untuk modul ini.
        Gate::define('customs.submit', function ($user) {
            return $user !== null; // TODO: ganti dengan role/permission check yang sesuai setelah ada keputusan RBAC untuk modul ini
        });

        Gate::define('customs.void', function ($user) {
            return $user !== null; // TODO: sama seperti di atas
        });
    }
}