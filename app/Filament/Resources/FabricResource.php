<?php

namespace App\Filament\Resources;

use App\Modules\Manufacturing\Models\Fabric;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\IconEntry;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Read-only fabric lookup; receipt/issue services retain stock ownership. */
final class FabricResource extends Resource
{
    protected static ?string $model = Fabric::class;
    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string { return __('erp.fabric_master'); }
    public static function getPluralModelLabel(): string { return __('erp.fabric_master'); }

    public static function canViewAny(): bool
    {
        // Same shared-master permission and single-MGI restriction, no implicit grants.
        return AuxiliaryMaterialResource::canViewAny();
    }

    public static function canCreate(): bool { return false; }
    public static function canView(Model $record): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            TextEntry::make('fabric_code')->label(__('panel.code')),
            TextEntry::make('material_name')->label(__('panel.name')),
            TextEntry::make('english_name'),
            TextEntry::make('category'),
            TextEntry::make('description')->columnSpanFull(),
            TextEntry::make('fabric_type'),
            TextEntry::make('subtype'),
            TextEntry::make('state'),
            TextEntry::make('composition'),
            TextEntry::make('color'),
            TextEntry::make('specification'),
            TextEntry::make('width'),
            TextEntry::make('gsm'),
            TextEntry::make('hs_code'),
            TextEntry::make('unit'),
            TextEntry::make('meters_per_roll'),
            TextEntry::make('stock_quantity'),
            TextEntry::make('inventory_account_code'),
            IconEntry::make('is_active')->label(__('panel.active'))->boolean(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('fabric_code')->label(__('panel.code'))->searchable()->sortable(),
            TextColumn::make('material_name')->label(__('panel.name'))->searchable()->sortable(),
            TextColumn::make('english_name')->searchable()->toggleable(),
            TextColumn::make('fabric_type')->searchable(),
            TextColumn::make('state')->sortable(),
            TextColumn::make('color')->searchable()->sortable(),
            TextColumn::make('specification')->searchable()->wrap(),
            TextColumn::make('width'),
            TextColumn::make('gsm'),
            TextColumn::make('composition')->searchable(),
            TextColumn::make('hs_code')->searchable()->toggleable(),
            TextColumn::make('unit'),
            TextColumn::make('meters_per_roll'),
            TextColumn::make('stock_quantity'),
            TextColumn::make('inventory_account_code'),
            IconColumn::make('is_active')->label(__('panel.active'))->boolean(),
        ])->filters([
            SelectFilter::make('state')->options(['GREY' => 'GREY', 'FINISHED' => 'FINISHED']),
            TernaryFilter::make('is_active')->label(__('panel.active')),
        ])->defaultSort('fabric_code')->paginationPageOptions([25, 50, 100])->defaultPaginationPageOption(25)
            ->recordActions([\Filament\Actions\ViewAction::make()])->toolbarActions([])
            ->recordUrl(fn (Model $record): string => static::getUrl('view', ['record' => $record]));
    }

    public static function getPages(): array
    {
        return [
            'index' => FabricResource\Pages\ListFabrics::route('/'),
            'view' => FabricResource\Pages\ViewFabric::route('/{record}'),
        ];
    }
}