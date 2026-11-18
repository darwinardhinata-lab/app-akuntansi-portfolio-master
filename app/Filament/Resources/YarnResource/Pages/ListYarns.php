<?php

namespace App\Filament\Resources\YarnResource\Pages;

use App\Filament\Resources\YarnResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Actions\Action;
use App\Modules\Manufacturing\Exports\YarnExport;
use Maatwebsite\Excel\Facades\Excel;

final class ListYarns extends ListRecords
{
    protected static string $resource = YarnResource::class;

    protected function getHeaderActions(): array
    {
        return [Action::make('export')->label(__('erp.export_excel'))
            ->action('exportFiltered')];
    }

    public function exportFiltered()
    {
        // Recheck on the action, not just on initial page load or hidden buttons.
        abort_unless(YarnResource::canViewAny(), 403);

        return Excel::download(new YarnExport($this->getFilteredSortedTableQuery()), 'Master_Yarn_Filtered.xlsx');
    }

    public function hydrate(): void
    {
        abort_unless(YarnResource::canViewAny(), 403);
    }
}