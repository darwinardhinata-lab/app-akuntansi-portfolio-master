<?php

use App\Http\Middleware\SetLocaleMiddleware;
use App\Modules\Platform\Http\Middleware\RequireOperationalCompany;
use App\Providers\CollectionMacroServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withEvents(discover: false)
    ->withMiddleware(function (Middleware $middleware) {
        // Hindari root aplikasi (/public/) untuk pengguna yang sudah login.
        // Endpoint eksplisit /dashboard stabil pada Apache/XAMPP.
        $middleware->redirectUsersTo(fn () => route('dashboard.index'));

        $middleware->web(append: [
            RequireOperationalCompany::class,
            SetLocaleMiddleware::class, // 💉 Injeksi Middleware Bahasa
        ]);
    })
    ->withProviders([
        CollectionMacroServiceProvider::class,
    ])
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
