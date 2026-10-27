<?php
namespace App\Filament\Resources\DivisionResource\Pages;

final class ListDivisions extends \Filament\Resources\Pages\ListRecords
{
    protected static string $resource = \App\Filament\Resources\DivisionResource::class;
    protected function getHeaderActions(): array { return [\Filament\Actions\CreateAction::make()]; }
}