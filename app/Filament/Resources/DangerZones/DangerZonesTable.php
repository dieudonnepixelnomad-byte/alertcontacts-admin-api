<?php

namespace App\Filament\Resources\DangerZones;

use App\Models\DangerZone;
use Filament\Tables;
use Filament\Tables\Table;

class DangerZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nom')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Créateur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('danger_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'assault' => 'danger',
                        'theft' => 'warning',
                        'accident' => 'info',
                        'harassment' => 'danger',
                        'other' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'assault' => 'Agression',
                        'theft' => 'Vol',
                        'accident' => 'Accident',
                        'harassment' => 'Harcèlement',
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

                Tables\Columns\TextColumn::make('radius')
                    ->label('Rayon')
                    ->formatStateUsing(fn (?float $state): string => $state ? $state . 'm' : 'N/A')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('danger_type')
                    ->label('Type de danger')
                    ->options([
                        'assault' => 'Agression',
                        'theft' => 'Vol',
                        'accident' => 'Accident',
                        'harassment' => 'Harcèlement',
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
                    ->label('Statut')
                    ->trueLabel('Actives')
                    ->falseLabel('Inactives')
                    ->placeholder('Toutes'),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Créateur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}