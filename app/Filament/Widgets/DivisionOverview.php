<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\DivisionResource;
use App\Models\MasterDivisi;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

final class DivisionOverview extends StatsOverviewWidget
{
    public static function canView(): bool { return DivisionResource::canViewAny(); }

    protected function getStats(): array
    {
        abort_unless(static::canView(), 403);
        return [Stat::make(__('panel.divisions'), MasterDivisi::query()->count())
            ->description(__('panel.master_records'))->url(DivisionResource::getUrl('index'))];
    }
}