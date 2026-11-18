<?php

namespace App\Filament\Resources;

use App\Modules\Platform\Http\Controllers\PartyController;
use App\Modules\Platform\Models\Party;
use App\Modules\Platform\Policies\PartyPolicy;
use App\Modules\Platform\Support\CompanyContext;
use App\Support\ErpPanelAccess;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Read-only company-scoped identities; existing Party controller owns master writes. */
final class PartyResource extends Resource
{
    protected static ?string $model = Party::class;
    protected static bool $isGloballySearchable = false;

    public static function getModelLabel(): string { return __('erp.party_master'); }
    public static function getPluralModelLabel(): string { return __('erp.party_master'); }

    public static function canViewAny(): bool
    {
        return app(ErpPanelAccess::class)->allows(request(), auth()->user())
            && app(PartyPolicy::class)->viewAny(auth()->user());
    }

    public static function getEloquentQuery(): Builder
    {
        abort_unless(static::canViewAny(), 403);
        return parent::getEloquentQuery()
            ->where('company_id', app(CompanyContext::class)->requireCompany(request())->id)
            ->with(['roles' => fn ($query) => $query->where('active', true)]);
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit(Model $record): bool { return false; }
    public static function canDelete(Model $record): bool { return false; }
    public static function canDeleteAny(): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('code')->label(__('panel.code'))->searchable()->sortable(),
            TextColumn::make('legal_name')->label(__('panel.name'))->searchable()->sortable(),
            TextColumn::make('roles.role')->badge(),
            TextColumn::make('tax_no')->searchable()->toggleable(),
            TextColumn::make('address')->searchable()->wrap(),
            TextColumn::make('phone')->searchable(),
            TextColumn::make('email')->searchable(),
            IconColumn::make('active')->label(__('panel.active'))->boolean(),
        ])->filters([
            SelectFilter::make('role')->options(array_combine(PartyController::ROLES, PartyController::ROLES))
                ->query(fn (Builder $query, array $data) => $query->when($data['value'] ?? null,
                    fn (Builder $query, string $role) => $query->whereHas('roles',
                        fn (Builder $roles) => $roles->where('role', $role)->where('active', true)))),
            TernaryFilter::make('active')->label(__('panel.active')),
        ])->defaultSort('code')->paginationPageOptions([25, 50, 100])->defaultPaginationPageOption(25)
            ->recordActions([])->toolbarActions([])->recordUrl(null);
    }

    public static function getPages(): array
    {
        return ['index' => PartyResource\Pages\ListParties::route('/')];
    }
}