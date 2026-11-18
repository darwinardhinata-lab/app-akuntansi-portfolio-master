<?php

namespace App\Filament\Resources\FabricResource\Pages;

use App\Filament\Resources\FabricResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use App\Modules\Manufacturing\Exports\FabricExport;
use Maatwebsite\Excel\Facades\Excel;

final class ListFabrics extends ListRecords
{
    protected static string $resource = FabricResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label(__('erp.export_excel'))->action('exportFiltered')];
    }

    public function exportFiltered()
    {
        abort_unless(FabricResource::canViewAny(), 403);

        return Excel::download(new FabricExport($this->getFilteredSortedTableQuery(), lookup: true), 'Master_Fabric_Filtered.xlsx');
    }

    public function hydrate(): void
    {
        abort_unless(FabricResource::canViewAny(), 403);
    }
}