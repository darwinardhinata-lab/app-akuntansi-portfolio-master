<?php

namespace App\Filament\Resources\YarnResource\Pages;

use App\Filament\Resources\YarnResource;
use Filament\Resources\Pages\ListRecords;

final class ListYarns extends ListRecords
{
    protected static string $resource = YarnResource::class;

    public function hydrate(): void
    {
        abort_unless(YarnResource::canViewAny(), 403);
    }
}