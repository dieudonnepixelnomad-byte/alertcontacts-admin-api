<?php

namespace App\Filament\Resources\UserActivity\Schemas;

use App\Models\UserActivity;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations de l\'activité')
                    ->schema([
                        Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        Select::make('activity_type')
                            ->label('Type d\'activité')
                            ->options([
                                UserActivity::ACTIVITY_AUTH => 'Authentification',
                                UserActivity::ACTIVITY_ZONE => 'Zones',
                                UserActivity::ACTIVITY_NOTIFICATION => 'Notifications',
                                UserActivity::ACTIVITY_RELATIONSHIP => 'Relations',
                                UserActivity::ACTIVITY_SETTINGS => 'Paramètres',
                                UserActivity::ACTIVITY_LOCATION => 'Localisation',
                            ])
                            ->required()
                            ->disabled(),

                        TextInput::make('action')
                            ->label('Action')
                            ->required()
                            ->disabled(),

                        TextInput::make('entity_type')
                            ->label('Type d\'entité')
                            ->disabled(),

                        TextInput::make('entity_id')
                            ->label('ID de l\'entité')
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Métadonnées')
                    ->schema([
                        KeyValue::make('metadata')
                            ->label('Données supplémentaires')
                            ->disabled(),
                    ]),

                Section::make('Informations techniques')
                    ->schema([
                        TextInput::make('ip_address')
                            ->label('Adresse IP')
                            ->disabled(),

                        TextInput::make('user_agent')
                            ->label('User Agent')
                            ->disabled(),
                    ])
                    ->columns(1),
            ]);
    }
}