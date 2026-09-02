<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator; // <-- 1. WAJIB PANGGIL CLASS INI

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
    }
}