<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Informations personnelles')
                ->schema([
                    TextInput::make('name')
                        ->label('Nom')
                        ->required()
                        ->maxLength(255),

                    TextInput::make('email')
                        ->label('Email')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true),

                    TextInput::make('phone_number')
                        ->label('Numéro de téléphone')
                        ->tel()
                        ->maxLength(20),

                    TextInput::make('avatar_url')
                        ->label('URL de l\'avatar')
                        ->url()
                        ->maxLength(500),

                    DateTimePicker::make('email_verified_at')
                        ->label('Email vérifié le')
                        ->disabled(),
                ]),

            Section::make('Authentification')
                ->schema([
                    TextInput::make('firebase_uid')
                        ->label('Firebase UID')
                        ->disabled(),

                    Select::make('provider')
                        ->label('Fournisseur d\'authentification')
                        ->options([
                            'firebase' => 'Firebase',
                            'google' => 'Google',
                            'apple' => 'Apple',
                            'email' => 'Email/Mot de passe',
                        ])
                        ->default('firebase'),

                    TextInput::make('password')
                        ->label('Mot de passe')
                        ->password()
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? bcrypt($state) : null)
                        ->dehydrated(fn ($state) => filled($state))
                        ->required(fn (string $context): bool => $context === 'create'),
                ]),

            Section::make('Notifications')
                ->schema([
                    TextInput::make('fcm_token')
                        ->label('Token FCM')
                        ->disabled(),

                    Select::make('fcm_platform')
                        ->label('Plateforme FCM')
                        ->options([
                            'android' => 'Android',
                            'ios' => 'iOS',
                            'web' => 'Web',
                        ]),

                    DateTimePicker::make('fcm_token_updated_at')
                        ->label('Token FCM mis à jour le')
                        ->disabled(),
                ]),

            Section::make('Heures de silence')
                ->schema([
                    Toggle::make('quiet_hours_enabled')
                        ->label('Heures de silence activées')
                        ->default(false),

                    TimePicker::make('quiet_hours_start')
                        ->label('Début des heures de silence')
                        ->seconds(false),

                    TimePicker::make('quiet_hours_end')
                        ->label('Fin des heures de silence')
                        ->seconds(false),

                    Select::make('timezone')
                        ->label('Fuseau horaire')
                        ->options([
                            'Europe/Paris' => 'Europe/Paris (UTC+1)',
                            'Europe/London' => 'Europe/London (UTC+0)',
                            'America/New_York' => 'America/New_York (UTC-5)',
                            'America/Los_Angeles' => 'America/Los_Angeles (UTC-8)',
                            'Asia/Tokyo' => 'Asia/Tokyo (UTC+9)',
                        ])
                        ->default('Europe/Paris')
                        ->searchable(),
                ]),
        ]);
    }
}
