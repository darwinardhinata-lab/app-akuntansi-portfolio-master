<?php

namespace App\Filament\Resources\FabricResource\Pages;

use App\Filament\Resources\FabricResource;
use Filament\Resources\Pages\ViewRecord;

/** Native ViewRecord reauthorizes the locked record on hydration. No write actions. */
final class ViewFabric extends ViewRecord
{
    protected static string $resource = FabricResource::class;
}