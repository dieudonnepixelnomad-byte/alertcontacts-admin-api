<?php

namespace App\Filament\Resources\SafeZoneEvent\Tables;

use App\Models\SafeZoneEvent;
use Filament\Tables;
use Filament\Tables\Table;

class SafeZoneEventTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('safeZone.name')
                    ->label('Zone de sécurité')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('event_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'enter' => 'success',
                        'exit' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'enter' => 'Entrée',
                        'exit' => 'Sortie',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('location')
                    ->label('Coordonnées')
                    ->formatStateUsing(function ($state) {
                        if ($state && isset($state->latitude, $state->longitude)) {
                            return number_format($state->latitude, 6) . ', ' . number_format($state->longitude, 6);
                        }
                        return 'N/A';
                    })
                    ->limit(25)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        if ($state && isset($state->latitude, $state->longitude)) {
                            return 'Lat: ' . $state->latitude . ', Lng: ' . $state->longitude;
                        }
                        return null;
                    }),

                Tables\Columns\TextColumn::make('accuracy')
                    ->label('Précision')
                    ->formatStateUsing(fn (?float $state): string => $state ? $state . 'm' : 'N/A')
                    ->sortable(),

                Tables\Columns\TextColumn::make('distance_m')
                    ->label('Distance')
                    ->formatStateUsing(fn (?float $state): string => $state ? number_format($state, 1) . 'm' : 'N/A')
                    ->sortable(),

                Tables\Columns\IconColumn::make('notification_sent')
                    ->label('Notif.')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('captured_at_device')
                    ->label('Capturé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Enregistré le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('event_type')
                    ->label('Type d\'événement')
                    ->options([
                        'enter' => 'Entrée',
                        'exit' => 'Sortie',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('safe_zone_id')
                    ->label('Zone de sécurité')
                    ->relationship('safeZone', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('notification_sent')
                    ->label('Notification')
                    ->options([
                        '1' => 'Envoyée',
                        '0' => 'Non envoyée',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}