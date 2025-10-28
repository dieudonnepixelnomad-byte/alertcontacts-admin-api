<?php

namespace App\Filament\Resources\Invitations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class InvitationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informations générales')
                ->schema([
                    Select::make('inviter_id')
                        ->label('Inviteur')
                        ->relationship('inviter', 'name')
                        ->required()
                        ->searchable(),

                    TextInput::make('inviter_name')
                        ->label('Nom de l\'inviteur')
                        ->maxLength(255),

                    Textarea::make('message')
                        ->label('Message personnalisé')
                        ->rows(3)
                        ->maxLength(500),
                ]),

            Section::make('Configuration')
                ->schema([
                    Select::make('default_share_level')
                        ->label('Niveau de partage par défaut')
                        ->options([
                            'real_time' => 'Temps réel',
                            'alert_only' => 'Alertes uniquement',
                            'none' => 'Aucun partage',
                        ])
                        ->default('alert_only')
                        ->required(),

                    TextInput::make('max_uses')
                        ->label('Nombre d\'utilisations maximum')
                        ->numeric()
                        ->default(1)
                        ->min(1)
                        ->max(100)
                        ->required(),

                    DateTimePicker::make('expires_at')
                        ->label('Date d\'expiration')
                        ->default(now()->addHours(24))
                        ->required(),
                ]),

            Section::make('Statut et utilisation')
                ->schema([
                    Select::make('status')
                        ->label('Statut')
                        ->options([
                            'pending' => 'En attente',
                            'accepted' => 'Acceptée',
                            'refused' => 'Refusée',
                            'expired' => 'Expirée',
                        ])
                        ->default('pending')
                        ->required(),

                    TextInput::make('used_count')
                        ->label('Nombre d\'utilisations')
                        ->numeric()
                        ->default(0)
                        ->disabled(),

                    TextInput::make('token')
                        ->label('Token')
                        ->disabled(),

                    TextInput::make('pin')
                        ->label('Code PIN')
                        ->disabled(),
                ]),

            Section::make('Dates importantes')
                ->schema([
                    DateTimePicker::make('accepted_at')
                        ->label('Date d\'acceptation')
                        ->disabled(),

                    DateTimePicker::make('refused_at')
                        ->label('Date de refus')
                        ->disabled(),
                ]),
        ]);
    }
}
