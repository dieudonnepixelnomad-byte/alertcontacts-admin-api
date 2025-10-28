<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\SafeZone;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SafeZonesRelationManager extends RelationManager
{
    protected static string $relationship = 'safeZones';

    protected static ?string $title = 'Zones de Sécurité';

    protected static ?string $modelLabel = 'Zone de Sécurité';

    protected static ?string $pluralModelLabel = 'Zones de Sécurité';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\TextInput::make('name')
                    ->label('Nom')
                    ->required()
                    ->maxLength(255),

                Forms\Components\Textarea::make('description')
                    ->label('Description')
                    ->rows(3)
                    ->columnSpanFull(),

                Forms\Components\Select::make('icon')
                    ->label('Icône')
                    ->options([
                        'home' => 'Maison',
                        'school' => 'École',
                        'work' => 'Travail',
                        'hospital' => 'Hôpital',
                        'gym' => 'Salle de sport',
                        'restaurant' => 'Restaurant',
                        'shop' => 'Magasin',
                        'park' => 'Parc',
                        'other' => 'Autre',
                    ])
                    ->required()
                    ->default('home'),

                Forms\Components\TextInput::make('radius_m')
                    ->label('Rayon (mètres)')
                    ->numeric()
                    ->required()
                    ->default(100)
                    ->minValue(10)
                    ->maxValue(5000),

                Forms\Components\TextInput::make('center_lat')
                    ->label('Latitude')
                    ->numeric()
                    ->required()
                    ->step(0.000001),

                Forms\Components\TextInput::make('center_lng')
                    ->label('Longitude')
                    ->numeric()
                    ->required()
                    ->step(0.000001),

                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),

                Forms\Components\TimePicker::make('active_start_time')
                    ->label('Heure de début')
                    ->default('00:00'),

                Forms\Components\TimePicker::make('active_end_time')
                    ->label('Heure de fin')
                    ->default('23:59'),

                Forms\Components\CheckboxList::make('active_days')
                    ->label('Jours actifs')
                    ->options([
                        'monday' => 'Lundi',
                        'tuesday' => 'Mardi',
                        'wednesday' => 'Mercredi',
                        'thursday' => 'Jeudi',
                        'friday' => 'Vendredi',
                        'saturday' => 'Samedi',
                        'sunday' => 'Dimanche',
                    ])
                    ->default(['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'])
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('icon')
                    ->label('Icône')
                    ->badge()
                    ->color('info'),

                Tables\Columns\TextColumn::make('radius_m')
                    ->label('Rayon')
                    ->suffix(' m')
                    ->sortable(),

                Tables\Columns\TextColumn::make('center_lat')
                    ->label('Latitude')
                    ->limit(10)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('center_lng')
                    ->label('Longitude')
                    ->limit(10)
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('active_start_time')
                    ->label('Début')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('active_end_time')
                    ->label('Fin')
                    ->time('H:i')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('icon')
                    ->label('Icône')
                    ->options([
                        'home' => 'Maison',
                        'school' => 'École',
                        'work' => 'Travail',
                        'hospital' => 'Hôpital',
                        'gym' => 'Salle de sport',
                        'restaurant' => 'Restaurant',
                        'shop' => 'Magasin',
                        'park' => 'Parc',
                        'other' => 'Autre',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}