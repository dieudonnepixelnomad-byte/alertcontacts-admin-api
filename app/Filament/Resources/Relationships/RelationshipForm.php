<?php

namespace App\Filament\Resources\Relationships;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class RelationshipForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Informations de la relation')
                    ->schema([
                        Select::make('user_id')
                            ->label('Utilisateur principal')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable(),

                        Select::make('contact_id')
                            ->label('Contact (proche)')
                            ->relationship('contact', 'name')
                            ->required()
                            ->searchable(),

                        Select::make('status')
                            ->label('Statut')
                            ->options([
                                'pending' => 'En attente',
                                'accepted' => 'Accepté',
                                'refused' => 'Refusé',
                                'blocked' => 'Bloqué',
                            ])
                            ->required()
                            ->default('pending'),

                        Select::make('share_level')
                            ->label('Niveau de partage')
                            ->options([
                                'none' => 'Aucun',
                                'alerts_only' => 'Alertes seulement',
                                'real_time' => 'Temps réel',
                                'full' => 'Complet',
                            ])
                            ->required()
                            ->default('alerts_only'),

                        Toggle::make('can_see_me')
                            ->label('Peut me voir')
                            ->default(true),
                    ]),

                Section::make('Dates importantes')
                    ->schema([
                        DateTimePicker::make('accepted_at')
                            ->label('Accepté le')
                            ->nullable(),

                        DateTimePicker::make('refused_at')
                            ->label('Refusé le')
                            ->nullable(),
                    ]),
            ]);
    }
}
