<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserActivity\Pages;
use App\Filament\Resources\UserActivity\Schemas\UserActivityForm;
use App\Filament\Resources\UserActivity\Tables\UserActivityTable;
use App\Models\UserActivity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class UserActivityResource extends Resource
{
    protected static ?string $model = UserActivity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationLabel = 'Activités Utilisateurs';

    protected static ?string $modelLabel = 'Activité';

    protected static ?string $pluralModelLabel = 'Activités';

    protected static string|UnitEnum|null $navigationGroup = 'Audit & Logs';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return UserActivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UserActivityTable::configure($table);
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
            'index' => Pages\ListUserActivities::route('/'),
            'view' => Pages\ViewUserActivity::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false; // Les activités sont créées automatiquement
    }

    public static function canEdit($record): bool
    {
        return false; // Les activités ne peuvent pas être modifiées
    }

    public static function canDelete($record): bool
    {
        return false; // Les activités ne peuvent pas être supprimées individuellement
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['user.name', 'user.email', 'action', 'ip_address'];
    }
}