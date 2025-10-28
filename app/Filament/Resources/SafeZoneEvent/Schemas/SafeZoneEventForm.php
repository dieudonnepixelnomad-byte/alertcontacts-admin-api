<?php

namespace App\Filament\Resources\SafeZoneEvent\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SafeZoneEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations de l\'événement')
                    ->schema([
                        Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        Select::make('safe_zone_id')
                            ->label('Zone de sécurité')
                            ->relationship('safeZone', 'name')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->disabled(),

                        Select::make('event_type')
                            ->label('Type d\'événement')
                            ->options([
                                'enter' => 'Entrée',
                                'exit' => 'Sortie',
                            ])
                            ->required()
                            ->disabled(),

                        DateTimePicker::make('captured_at_device')
                            ->label('Capturé sur l\'appareil')
                            ->displayFormat('d/m/Y H:i:s')
                            ->disabled(),
                    ])
                    ->columns(2),

                Section::make('Données de localisation')
                    ->schema([
                        TextInput::make('location')
                            ->label('Coordonnées')
                            ->disabled()
                            ->formatStateUsing(function ($state) {
                                if ($state && isset($state->latitude, $state->longitude)) {
                                    return $state->latitude . ', ' . $state->longitude;
                                }
                                return 'N/A';
                            }),

                        TextInput::make('accuracy')
                            ->label('Précision (m)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('distance_m')
                            ->label('Distance (m)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('speed_kmh')
                            ->label('Vitesse (km/h)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('heading')
                            ->label('Direction (°)')
                            ->numeric()
                            ->disabled(),
                    ])
                    ->columns(3),

                Section::make('Informations techniques')
                    ->schema([
                        TextInput::make('battery_level')
                            ->label('Niveau batterie (%)')
                            ->numeric()
                            ->disabled(),

                        TextInput::make('source')
                            ->label('Source')
                            ->disabled(),

                        Checkbox::make('foreground')
                            ->label('Application en premier plan')
                            ->disabled(),

                        Checkbox::make('notification_sent')
                            ->label('Notification envoyée')
                            ->disabled(),

                        DateTimePicker::make('notification_sent_at')
                            ->label('Notification envoyée le')
                            ->displayFormat('d/m/Y H:i:s')
                            ->disabled(),
                    ])
                    ->columns(2),
            ]);
    }
}