<?php
namespace App\Filament\Resources\DivisionResource\Pages;

final class EditDivision extends \Filament\Resources\Pages\EditRecord
{
    protected static string $resource = \App\Filament\Resources\DivisionResource::class;
    protected function handleRecordUpdate(\Illuminate\Database\Eloquent\Model $record, array $data): \Illuminate\Database\Eloquent\Model
    {
        return app(\App\Services\DivisionService::class)->save($data, (int) $record->getKey());
    }
}