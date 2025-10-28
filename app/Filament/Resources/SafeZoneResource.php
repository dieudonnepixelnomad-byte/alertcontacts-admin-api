<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SafeZones\Pages;
use App\Filament\Resources\SafeZones\SafeZoneForm;
use App\Filament\Resources\SafeZones\SafeZonesTable;
use App\Models\SafeZone;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SafeZoneResource extends Resource
{
    protected static ?string $model = SafeZone::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?string $navigationLabel = 'Zones de Sécurité';

    protected static ?string $modelLabel = 'Zone de Sécurité';

    protected static ?string $pluralModelLabel = 'Zones de Sécurité';

    protected static string|UnitEnum|null $navigationGroup = 'Gestion des Zones';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return SafeZoneForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SafeZonesTable::configure($table);
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
            'index' => Pages\ListSafeZones::route('/'),
            'create' => Pages\CreateSafeZone::route('/create'),
            'edit' => Pages\EditSafeZone::route('/{record}/edit'),
        ];
    }
}