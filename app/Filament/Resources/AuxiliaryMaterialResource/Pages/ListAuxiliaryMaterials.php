<?php

namespace App\Filament\Resources\AuxiliaryMaterialResource\Pages;

use App\Filament\Resources\AuxiliaryMaterialResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use App\Modules\Manufacturing\Exports\AuxiliaryMaterialExport;
use Maatwebsite\Excel\Facades\Excel;

final class ListAuxiliaryMaterials extends ListRecords
{
    protected static string $resource = AuxiliaryMaterialResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label(__('erp.export_excel'))->action('exportFiltered'),
            Action::make('previewImport')->label('Preview Import (read-only)')->url(AuxiliaryMaterialResource::getUrl('preview-import'))];
    }

    public function exportFiltered()
    {
        abort_unless(AuxiliaryMaterialResource::canViewAny(), 403);

        return Excel::download(new AuxiliaryMaterialExport($this->getFilteredSortedTableQuery(), lookup: true), 'Master_Auxiliary_Material_Filtered.xlsx');
    }

    public function hydrate(): void
    {
        // Mount authorization alone does not protect an already-hydrated table.
        abort_unless(AuxiliaryMaterialResource::canViewAny(), 403);
    }
}
