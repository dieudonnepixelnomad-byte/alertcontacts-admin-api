<?php

namespace App\Filament\Resources\SafeZones;

use App\Models\SafeZone;
use Filament\Tables;
use Filament\Tables\Table;

class SafeZonesTable
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
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