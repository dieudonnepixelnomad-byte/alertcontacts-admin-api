<?php

namespace App\Filament\Resources\Feedback\Schemas;

use App\Models\Feedback;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FeedbackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Informations du feedback')
                    ->schema([
                        Select::make('user_id')
                            ->label('Utilisateur')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),

                        Select::make('type')
                            ->label('Type')
                            ->options(Feedback::TYPES)
                            ->required(),

                        TextInput::make('subject')
                            ->label('Sujet')
                            ->required()
                            ->maxLength(255),

                        Textarea::make('message')
                            ->label('Message')
                            ->required()
                            ->rows(4)
                            ->maxLength(2000),

                        TextInput::make('rating')
                            ->label('Note (1-5)')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(5),
                    ])
                    ->columns(2),

                Section::make('Informations techniques')
                    ->schema([
                        TextInput::make('app_version')
                            ->label('Version de l\'app')
                            ->maxLength(50),

                        Textarea::make('device_info')
                            ->label('Informations de l\'appareil')
                            ->rows(2)
                            ->maxLength(500),
                    ])
                    ->columns(2),

                Section::make('Gestion administrative')
                    ->schema([
                        Select::make('status')
                            ->label('Statut')
                            ->options(Feedback::STATUSES)
                            ->required()
                            ->default('pending'),

                        Textarea::make('admin_response')
                            ->label('Réponse administrateur')
                            ->rows(3)
                            ->maxLength(1000),

                        DateTimePicker::make('reviewed_at')
                            ->label('Examiné le')
                            ->displayFormat('d/m/Y H:i'),
                    ])
                    ->columns(1),
            ]);
    }
}