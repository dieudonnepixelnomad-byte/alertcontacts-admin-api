<?php

namespace App\Filament\Resources\SafeZones;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SafeZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informations générales')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nom de la zone')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000),

                        Select::make('creator_id')
                            ->label('Créateur')
                            ->relationship('creator', 'name')
                            ->required()
                            ->searchable(),

                        Toggle::make('is_active')
                            ->label('Zone active')
                            ->default(true),
                    ]),

                Section::make('Localisation')
                    ->schema([
                        TextInput::make('latitude')
                            ->label('Latitude')
                            ->required()
                            ->numeric()
                            ->step(0.000001),

                        TextInput::make('longitude')
                            ->label('Longitude')
                            ->required()
                            ->numeric()
                            ->step(0.000001),

                        TextInput::make('radius')
                            ->label('Rayon (mètres)')
                            ->required()
                            ->numeric()
                            ->minValue(10)
                            ->maxValue(5000)
                            ->default(100),

                        TextInput::make('address')
                            ->label('Adresse')
                            ->maxLength(500),
                    ]),

                Section::make('Configuration')
                    ->schema([
                        Select::make('zone_type')
                            ->label('Type de zone')
                            ->options([
                                'home' => 'Domicile',
                                'school' => 'École',
                                'work' => 'Travail',
                                'family' => 'Famille',
                                'friend' => 'Ami',
                                'other' => 'Autre',
                            ])
                            ->required()
                            ->default('home'),

                        DateTimePicker::make('active_from')
                            ->label('Actif à partir de')
                            ->nullable(),

                        DateTimePicker::make('active_until')
                            ->label('Actif jusqu\'à')
                            ->nullable(),

                        Toggle::make('notify_entry')
                            ->label('Notifier à l\'entrée')
                            ->default(true),

                        Toggle::make('notify_exit')
                            ->label('Notifier à la sortie')
                            ->default(true),
                    ]),
            ]);
    }
}
