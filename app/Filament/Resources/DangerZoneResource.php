<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DangerZones\Pages\CreateDangerZone;
use App\Filament\Resources\DangerZones\Pages\EditDangerZone;
use App\Filament\Resources\DangerZones\Pages\ListDangerZones;
use App\Filament\Resources\DangerZones\Schemas\DangerZoneForm;
use App\Filament\Resources\DangerZones\Tables\DangerZonesTable;
use App\Models\DangerZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class DangerZoneResource extends Resource
{
    protected static ?string $model = DangerZone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-exclamation-triangle';

    protected static ?string $navigationLabel = 'Zones de Danger';

    protected static ?string $modelLabel = 'Zone de Danger';

    protected static ?string $pluralModelLabel = 'Zones de Danger';

    protected static string|UnitEnum|null $navigationGroup = 'Gestion des Zones';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return DangerZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DangerZonesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDangerZones::route('/'),
            'create' => CreateDangerZone::route('/create'),
            'edit' => EditDangerZone::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $activeCount = static::getModel()::where('is_active', true)->count();

        if ($activeCount > 50) {
            return 'danger';
        } elseif ($activeCount > 20) {
            return 'warning';
        }

        return 'success';
    }
}
