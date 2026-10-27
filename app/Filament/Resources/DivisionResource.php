<?php

namespace App\Filament\Resources;

use App\Models\MasterDivisi;
use App\Support\ErpPanelAccess;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class DivisionResource extends Resource
{
    protected static ?string $model = MasterDivisi::class;
    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string { return __('panel.division'); }
    public static function getPluralModelLabel(): string { return __('panel.divisions'); }
    public static function canViewAny(): bool { return self::allowed('view'); }
    public static function canCreate(): bool { return self::allowed('create'); }
    public static function canEdit(Model $record): bool { return self::allowed('update'); }
    public static function canView(Model $record): bool { return self::allowed('view'); }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    private static function allowed(string $action): bool
    {
        return app(ErpPanelAccess::class)->allows(request(), auth()->user())
            && app(ErpPanelAccess::class)->allows(request(), auth()->user(), 'division.'.$action);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            \Filament\Forms\Components\TextInput::make('kode_divisi')->label(__('panel.code'))->required()->maxLength(10),
            \Filament\Forms\Components\TextInput::make('nama_divisi')->label(__('panel.name'))->required()->maxLength(50),
            \Filament\Forms\Components\Toggle::make('status_aktif')->label(__('panel.active'))->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            \Filament\Tables\Columns\TextColumn::make('kode_divisi')->label(__('panel.code'))->searchable()->sortable(),
            \Filament\Tables\Columns\TextColumn::make('nama_divisi')->label(__('panel.name'))->searchable()->sortable(),
            \Filament\Tables\Columns\IconColumn::make('status_aktif')->label(__('panel.active'))->boolean(),
        ])->recordActions([\Filament\Actions\ViewAction::make(), \Filament\Actions\EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => DivisionResource\Pages\ListDivisions::route('/'),
            'create' => DivisionResource\Pages\CreateDivision::route('/create'),
            'view' => DivisionResource\Pages\ViewDivision::route('/{record}'),
            'edit' => DivisionResource\Pages\EditDivision::route('/{record}/edit'),
        ];
    }
}