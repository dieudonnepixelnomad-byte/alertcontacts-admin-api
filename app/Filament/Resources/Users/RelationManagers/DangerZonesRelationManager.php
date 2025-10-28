<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\DangerZone;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class DangerZonesRelationManager extends RelationManager
{
    protected static string $relationship = 'dangerZones';

    protected static ?string $title = 'Zones de Danger';

    protected static ?string $modelLabel = 'Zone de Danger';

    protected static ?string $pluralModelLabel = 'Zones de Danger';

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

                Forms\Components\Select::make('danger_type')
                    ->label('Type de danger')
                    ->options([
                        'theft' => 'Vol',
                        'assault' => 'Agression',
                        'accident' => 'Accident',
                        'harassment' => 'Harcèlement',
                        'vandalism' => 'Vandalisme',
                        'drug_activity' => 'Activité de drogue',
                        'suspicious_activity' => 'Activité suspecte',
                        'other' => 'Autre',
                    ])
                    ->required(),

                Forms\Components\Select::make('severity')
                    ->label('Gravité')
                    ->options([
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Élevée',
                        'critical' => 'Critique',
                    ])
                    ->required()
                    ->default('medium'),

                Forms\Components\TextInput::make('radius_m')
                    ->label('Rayon (mètres)')
                    ->numeric()
                    ->required()
                    ->default(100)
                    ->minValue(10)
                    ->maxValue(1000),

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

                Forms\Components\DateTimePicker::make('expires_at')
                    ->label('Date d\'expiration')
                    ->default(now()->addDays(30))
                    ->required(),
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

                Tables\Columns\TextColumn::make('danger_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'theft' => 'warning',
                        'assault' => 'danger',
                        'accident' => 'info',
                        'harassment' => 'danger',
                        'vandalism' => 'warning',
                        'drug_activity' => 'danger',
                        'suspicious_activity' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'theft' => 'Vol',
                        'assault' => 'Agression',
                        'accident' => 'Accident',
                        'harassment' => 'Harcèlement',
                        'vandalism' => 'Vandalisme',
                        'drug_activity' => 'Activité de drogue',
                        'suspicious_activity' => 'Activité suspecte',
                        'other' => 'Autre',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('severity')
                    ->label('Gravité')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'low' => 'success',
                        'medium' => 'warning',
                        'high' => 'danger',
                        'critical' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Élevée',
                        'critical' => 'Critique',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('radius_m')
                    ->label('Rayon')
                    ->suffix(' m')
                    ->sortable(),

                Tables\Columns\TextColumn::make('confirmations_count')
                    ->label('Confirmations')
                    ->counts('confirmations')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('danger_type')
                    ->label('Type de danger')
                    ->options([
                        'theft' => 'Vol',
                        'assault' => 'Agression',
                        'accident' => 'Accident',
                        'harassment' => 'Harcèlement',
                        'vandalism' => 'Vandalisme',
                        'drug_activity' => 'Activité de drogue',
                        'suspicious_activity' => 'Activité suspecte',
                        'other' => 'Autre',
                    ]),

                Tables\Filters\SelectFilter::make('severity')
                    ->label('Gravité')
                    ->options([
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Élevée',
                        'critical' => 'Critique',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active'),

                Tables\Filters\Filter::make('expires_soon')
                    ->label('Expire bientôt')
                    ->query(fn ($query) => $query->where('expires_at', '<=', now()->addDays(7))),
            ])
            ->defaultSort('created_at', 'desc');
    }
}