<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SafeZoneEvent\Pages;
use App\Filament\Resources\SafeZoneEvent\Schemas\SafeZoneEventForm;
use App\Filament\Resources\SafeZoneEvent\Tables\SafeZoneEventTable;
use App\Models\SafeZoneEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class SafeZoneEventResource extends Resource
{
    protected static ?string $model = SafeZoneEvent::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationLabel = 'Événements Zones Sécurisées';

    protected static ?string $modelLabel = 'Événement';

    protected static ?string $pluralModelLabel = 'Événements';

    protected static string|UnitEnum|null $navigationGroup = 'Zones & Événements';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return SafeZoneEventForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SafeZoneEventTable::configure($table);
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
            'index' => Pages\ListSafeZoneEvents::route('/'),
            'view' => Pages\ViewSafeZoneEvent::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Les événements sont créés automatiquement
    }

    public static function canEdit($record): bool
    {
        return false; // Les événements ne peuvent pas être modifiés
    }

    public static function canDelete($record): bool
    {
        return false; // Les événements ne peuvent pas être supprimés individuellement
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::whereDate('created_at', today())->count();
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        $todayCount = static::getModel()::whereDate('created_at', today())->count();

        if ($todayCount > 100) {
            return 'success';
        } elseif ($todayCount > 50) {
            return 'warning';
        }

        return 'gray';
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name', 'user.email', 'safeZone.name', 'event_type'];
    }
}