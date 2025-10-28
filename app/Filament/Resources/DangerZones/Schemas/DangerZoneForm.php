<?php

namespace App\Filament\Resources\DangerZones\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DangerZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations générales')
                    ->schema([
                        TextInput::make('title')
                            ->label('Titre')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('description')
                            ->label('Description')
                            ->rows(3)
                            ->maxLength(1000),

                        Select::make('severity')
                            ->label('Gravité')
                            ->options([
                                'low' => 'Faible',
                                'medium' => 'Moyenne',
                                'high' => 'Élevée',
                                'critical' => 'Critique',
                            ])
                            ->required()
                            ->default('medium'),

                        Toggle::make('is_active')
                            ->label('Zone active')
                            ->default(true),
                    ])
                    ->columns(2),

                Section::make('Localisation')
                    ->schema([
                        TextInput::make('center_latitude')
                            ->label('Latitude du centre')
                            ->numeric()
                            ->step(0.000001)
                            ->required(),

                        TextInput::make('center_longitude')
                            ->label('Longitude du centre')
                            ->numeric()
                            ->step(0.000001)
                            ->required(),

                        TextInput::make('radius_m')
                            ->label('Rayon (mètres)')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->minValue(10)
                            ->maxValue(5000),
                    ])
                    ->columns(3),

                Section::make('Informations de signalement')
                    ->schema([
                        Select::make('reported_by')
                            ->label('Signalé par')
                            ->relationship('reporter', 'name')
                            ->searchable()
                            ->preload(),

                        TextInput::make('confirmations')
                            ->label('Nombre de confirmations')
                            ->numeric()
                            ->default(1)
                            ->minValue(0),

                        DateTimePicker::make('last_report_at')
                            ->label('Dernier signalement')
                            ->default(now()),
                    ])
                    ->columns(3),
            ]);
    }
}
