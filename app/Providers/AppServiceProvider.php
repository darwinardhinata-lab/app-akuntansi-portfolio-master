<?php

namespace App\Providers;

use App\Listeners\LogUserLogin;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator; // <-- 1. WAJIB PANGGIL CLASS INI
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Database\Console\Migrations\FreshCommand;
use Illuminate\Database\Console\Migrations\RefreshCommand;
use Illuminate\Database\Console\Migrations\ResetCommand;
use Illuminate\Database\Console\Migrations\RollbackCommand;
use Illuminate\Database\Console\WipeCommand;
use App\Support\OperationalDatabaseSafety;

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
        // Database MGI adalah data operasional. Perintah berikut dapat menghapus
        // schema/data dan tidak boleh berjalan tanpa opt-in operator yang eksplisit.
        // Test suite tetap diizinkan karena selalu menggunakan APP_ENV=testing.
        if (OperationalDatabaseSafety::prohibitsDestructiveCommands()) {
            FreshCommand::prohibit();
            RefreshCommand::prohibit();
            ResetCommand::prohibit();
            RollbackCommand::prohibit();
            WipeCommand::prohibit();
        }

        Gate::policy(\App\Modules\Platform\Models\Party::class, \App\Modules\Platform\Policies\PartyPolicy::class);

        Event::listen(Login::class, LogUserLogin::class);

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