<?php

namespace App\Filament\Resources\PartyResource\Pages;

use App\Filament\Resources\PartyResource;
use Filament\Resources\Pages\ListRecords;

final class ListParties extends ListRecords
{
    protected static string $resource = PartyResource::class;

    public function hydrate(): void
    {
        abort_unless(PartyResource::canViewAny(), 403);
    }
}