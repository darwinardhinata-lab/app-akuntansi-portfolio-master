<?php

namespace App\Providers\Filament;

use App\Http\Middleware\EnsureErpPanelAccess;
use Filament\Panel;
use Filament\PanelProvider;

final class ErpPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel->id('erp')->path('erp-panel')->authGuard('web')
            // Legacy layouts disable global injection; reactive ERP pages still need Livewire.
            ->renderHook(\Filament\View\PanelsRenderHook::SCRIPTS_AFTER,
                fn () => new \Illuminate\Support\HtmlString(\Illuminate\Support\Facades\Blade::render('@livewireScripts')))
            ->resources([\App\Filament\Resources\DivisionResource::class, \App\Filament\Resources\AuxiliaryMaterialResource::class, \App\Filament\Resources\PartyResource::class])
            ->widgets([\App\Filament\Widgets\DivisionOverview::class])
            ->pages([\Filament\Pages\Dashboard::class, \App\Filament\Pages\ApReconciliationQueue::class, \App\Filament\Pages\BankPayments::class])
            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([EnsureErpPanelAccess::class], isPersistent: true);
    }
}