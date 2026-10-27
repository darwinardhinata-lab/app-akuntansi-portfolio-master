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
            ->resources([\App\Filament\Resources\DivisionResource::class])
            ->widgets([\App\Filament\Widgets\DivisionOverview::class])
            ->navigationItems([
                \Filament\Navigation\NavigationItem::make('bank-payments')->label(fn () => __('bank_payment.title'))
                    ->url(fn () => route('payment-plan-v33.workspace'))
                    ->visible(fn () => config('payment_plan_v33.enabled') && config('payment_plan_v33.bank_workflow')),
            ])
            ->pages([\Filament\Pages\Dashboard::class, \App\Filament\Pages\ApReconciliationQueue::class])
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