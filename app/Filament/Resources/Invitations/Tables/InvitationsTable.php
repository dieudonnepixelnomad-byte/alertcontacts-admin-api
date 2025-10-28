<?php

namespace App\Filament\Resources\Invitations\Tables;

use App\Models\Invitation;
use Filament\Tables;
use Filament\Tables\Table;

class InvitationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('sender.name')
                    ->label('Expéditeur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('recipient_email')
                    ->label('Email destinataire')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'warning',
                        'accepted' => 'success',
                        'declined' => 'danger',
                        'expired' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'En attente',
                        'accepted' => 'Acceptée',
                        'declined' => 'Refusée',
                        'expired' => 'Expirée',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expire le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('accepted_at')
                    ->label('Acceptée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('Non acceptée'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créée le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options([
                        'pending' => 'En attente',
                        'accepted' => 'Acceptée',
                        'declined' => 'Refusée',
                        'expired' => 'Expirée',
                    ]),

                Tables\Filters\SelectFilter::make('sender_id')
                    ->label('Expéditeur')
                    ->relationship('sender', 'name')
                    ->searchable()
                    ->preload(),

                Tables\Filters\TernaryFilter::make('is_expired')
                    ->label('Expiration')
                    ->trueLabel('Expirées')
                    ->falseLabel('Valides')
                    ->placeholder('Toutes')
                    ->queries(
                        true: fn ($query) => $query->where('expires_at', '<', now()),
                        false: fn ($query) => $query->where('expires_at', '>=', now()),
                    ),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}