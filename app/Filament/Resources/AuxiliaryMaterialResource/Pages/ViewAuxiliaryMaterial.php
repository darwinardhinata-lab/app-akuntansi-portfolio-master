<?php

namespace App\Filament\Resources\AuxiliaryMaterialResource\Pages;

use App\Filament\Resources\AuxiliaryMaterialResource;
use Filament\Resources\Pages\ViewRecord;

/** ViewRecord rechecks canView on every hydration; no write actions. */
final class ViewAuxiliaryMaterial extends ViewRecord
{
    protected static string $resource = AuxiliaryMaterialResource::class;
}