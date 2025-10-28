<?php

namespace App\Filament\Resources\UserActivity\Tables;

use App\Models\UserActivity;
use Filament\Tables;
use Filament\Tables\Table;

class UserActivityTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('action')
                    ->label('Action')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login' => 'success',
                        'logout' => 'warning',
                        'create' => 'info',
                        'update' => 'warning',
                        'delete' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('model_type')
                    ->label('Type de modèle')
                    ->formatStateUsing(fn (?string $state): string => $state ? class_basename($state) : 'N/A')
                    ->searchable(),

                Tables\Columns\TextColumn::make('model_id')
                    ->label('ID du modèle')
                    ->sortable(),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('Adresse IP')
                    ->searchable(),

                Tables\Columns\TextColumn::make('user_agent')
                    ->label('User Agent')
                    ->limit(50)
                    ->tooltip(fn (Tables\Columns\TextColumn $column): ?string => $column->getState()),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label('Action')
                    ->options([
                        'login' => 'Connexion',
                        'logout' => 'Déconnexion',
                        'create' => 'Création',
                        'update' => 'Modification',
                        'delete' => 'Suppression',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\SelectFilter::make('model_type')
                    ->label('Type de modèle')
                    ->options([
                        'App\\Models\\User' => 'Utilisateur',
                        'App\\Models\\SafeZone' => 'Zone de sécurité',
                        'App\\Models\\DangerZone' => 'Zone de danger',
                        'App\\Models\\Relationship' => 'Relation',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}