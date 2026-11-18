<?php

namespace App\Filament\Resources\YarnResource\Pages;

use App\Filament\Resources\YarnResource;
use Filament\Resources\Pages\ViewRecord;

/** Native ViewRecord reauthorizes the locked record on hydration. No write actions. */
final class ViewYarn extends ViewRecord
{
    protected static string $resource = YarnResource::class;
}