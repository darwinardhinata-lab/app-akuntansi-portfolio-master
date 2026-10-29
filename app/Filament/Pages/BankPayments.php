<?php

namespace App\Filament\Pages;

use App\Modules\PaymentPlan\Support\ErpActor;
use App\Support\ErpPanelAccess;

final class BankPayments extends \Filament\Pages\Page
{
    protected string $view = 'payment_plan.bank_workspace';
    protected static ?string $slug = 'bank-payments';

    public static function canAccess(): bool
    {
        if (!config('payment_plan_v33.enabled') || !config('payment_plan_v33.bank_workflow')
            || !app(ErpPanelAccess::class)->allows(request(), auth()->user())) return false;
        try { app(ErpActor::class)->resolve(request()); return true; }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { return false; }
    }

    public static function getNavigationLabel(): string { return __('bank_payment.title'); }
    public function getTitle(): string { return __('bank_payment.title'); }

    protected function getViewData(): array
    {
        abort_unless(static::canAccess(), 403);
        return ['actor' => app(ErpActor::class)->resolve(request()), 'endpoints' => [
            'data' => route('payment-plan-v33.data'), 'request' => route('payment-plan-v33.request'),
            'action' => route('payment-plan-v33.action'), 'attachment' => route('payment-plan-v33.attachment'),
            'download' => url('/payment-plan/api/attachment'), 'purchaseBills' => route('payment-plan-v33.purchase-bills'),
            'print' => url('/payment-plan/bank'),
        ]];
    }
}