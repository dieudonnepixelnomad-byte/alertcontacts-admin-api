<?php

namespace App\Filament\Resources\Relationships;

use App\Models\Relationship;
use Filament\Tables;
use Filament\Tables\Table;

class RelationshipsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Contact')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('share_level')
                    ->label('Niveau de partage')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'real_time' => 'success',
                        'alert_only' => 'warning',
                        'none' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'real_time' => 'Temps réel',
                        'alert_only' => 'Alertes uniquement',
                        'none' => 'Aucun partage',
                        default => $state,
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\IconColumn::make('is_mutual')
                    ->label('Mutuelle')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-path')
                    ->falseIcon('heroicon-o-arrow-right')
                    ->trueColor('info')
                    ->falseColor('gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_location_shared_at')
                    ->label('Dernière localisation')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Jamais partagée'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('share_level')
                    ->label('Niveau de partage')
                    ->options([
                        'real_time' => 'Temps réel',
                        'alert_only' => 'Alertes uniquement',
                        'none' => 'Aucun partage',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Statut')
                    ->trueLabel('Actives')
                    ->falseLabel('Inactives')
                    ->placeholder('Toutes'),

                Tables\Filters\TernaryFilter::make('is_mutual')
                    ->label('Type')
                    ->trueLabel('Mutuelles')
                    ->falseLabel('Unilatérales')
                    ->placeholder('Toutes'),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}