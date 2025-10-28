<?php

namespace App\Filament\Resources;

use App\Filament\Resources\Relationships\Pages;
use App\Filament\Resources\Relationships\RelationshipForm;
use App\Filament\Resources\Relationships\RelationshipsTable;
use App\Models\Relationship;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class RelationshipResource extends Resource
{
    protected static ?string $model = Relationship::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Relations Proches';

    protected static ?string $modelLabel = 'Relation';

    protected static ?string $pluralModelLabel = 'Relations';

    protected static string|UnitEnum|null $navigationGroup = 'Gestion des Utilisateurs';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return RelationshipForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RelationshipsTable::configure($table);
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
            'index' => Pages\ListRelationships::route('/'),
            'create' => Pages\CreateRelationship::route('/create'),
            'edit' => Pages\EditRelationship::route('/{record}/edit'),
        ];
    }
}