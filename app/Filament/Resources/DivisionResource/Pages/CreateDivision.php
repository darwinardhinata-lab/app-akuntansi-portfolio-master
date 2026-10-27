<?php
namespace App\Filament\Resources\DivisionResource\Pages;

final class CreateDivision extends \Filament\Resources\Pages\CreateRecord
{
    protected static string $resource = \App\Filament\Resources\DivisionResource::class;
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(\App\Services\DivisionService::class)->save($data);
    }
}