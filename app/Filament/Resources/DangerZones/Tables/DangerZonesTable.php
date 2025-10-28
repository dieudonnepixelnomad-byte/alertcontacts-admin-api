<?php

namespace App\Filament\Resources\DangerZones\Tables;

use Filament\Tables;
use Filament\Tables\Table;

class DangerZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label('Titre')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\BadgeColumn::make('severity')
                    ->label('Gravité')
                    ->colors([
                        'success' => 'low',
                        'warning' => 'medium',
                        'danger' => 'high',
                        'danger' => 'critical',
                    ])
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Élevée',
                        'critical' => 'Critique',
                        default => $state,
                    }),
                
                Tables\Columns\TextColumn::make('reporter.name')
                    ->label('Signalé par')
                    ->searchable()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('confirmations')
                    ->label('Confirmations')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),
                
                Tables\Columns\TextColumn::make('radius_m')
                    ->label('Rayon (m)')
                    ->sortable()
                    ->alignCenter(),
                
                Tables\Columns\TextColumn::make('last_report_at')
                    ->label('Dernier signalement')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('severity')
                    ->label('Gravité')
                    ->options([
                        'low' => 'Faible',
                        'medium' => 'Moyenne',
                        'high' => 'Élevée',
                        'critical' => 'Critique',
                    ]),
                
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Zone active'),
                
                Tables\Filters\SelectFilter::make('reported_by')
                    ->label('Signalé par')
                    ->relationship('reporter', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('last_report_at', 'desc')
            ->poll('30s');
    }
}