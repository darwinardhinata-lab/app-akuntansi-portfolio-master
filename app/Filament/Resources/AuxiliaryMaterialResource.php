<?php

namespace App\Filament\Resources;

use App\Modules\Manufacturing\Models\AuxiliaryMaterial;
use App\Support\ErpPanelAccess;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only lookup: stock and valuation remain owned by receipt/issue services. */
final class AuxiliaryMaterialResource extends Resource
{
    protected static ?string $model = AuxiliaryMaterial::class;

    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string
    {
        return __('erp.auxiliary_material_master');
    }

    public static function getPluralModelLabel(): string
    {
        return __('erp.auxiliary_material_master');
    }

    public static function canViewAny(): bool
    {
        $access = app(ErpPanelAccess::class);

        // Shared legacy master has no company_id: retain the existing single-MGI gate.
        return $access->allows(request(), auth()->user())
            && $access->allows(request(), auth()->user(), 'material.view');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('material_code')->label(__('panel.code')),
            TextEntry::make('material_name')->label(__('panel.name')),
            TextEntry::make('english_name'),
            TextEntry::make('category'),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('color'),
            TextEntry::make('specification'),
            TextEntry::make('hs_code'),
            TextEntry::make('unit'),
            TextEntry::make('meters_per_roll'),
            TextEntry::make('stock_quantity'),
            IconEntry::make('is_active')->label(__('panel.active'))->boolean(),
        ]);
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('material_code')->label(__('panel.code'))->searchable()->sortable(),
            TextColumn::make('material_name')->label(__('panel.name'))->searchable()->sortable(),
            TextColumn::make('english_name')->searchable()->toggleable(),
            TextColumn::make('color')->searchable()->sortable(),
            TextColumn::make('specification')->searchable()->wrap(),
            TextColumn::make('hs_code')->searchable()->toggleable(),
            TextColumn::make('unit'),
            TextColumn::make('meters_per_roll'),
            TextColumn::make('stock_quantity'),
            IconColumn::make('is_active')->label(__('panel.active'))->boolean(),
        ])->filters([
            TernaryFilter::make('is_active')->label(__('panel.active')),
        ])->defaultSort('material_code')->paginationPageOptions([25, 50, 100])->defaultPaginationPageOption(25)
            ->recordActions([\Filament\Actions\ViewAction::make()])->toolbarActions([])
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => AuxiliaryMaterialResource\Pages\ListAuxiliaryMaterials::route('/'),
            'preview-import' => AuxiliaryMaterialResource\Pages\PreviewAuxiliaryMaterialImport::route('/preview-import'),
            'view' => AuxiliaryMaterialResource\Pages\ViewAuxiliaryMaterial::route('/{record}'),
        ];
    }
}
