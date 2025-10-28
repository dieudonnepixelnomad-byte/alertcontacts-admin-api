<?php

namespace App\Filament\Resources\Feedback\Tables;

use App\Models\Feedback;
use Filament\Tables;
use Filament\Tables\Table;

class FeedbackTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Utilisateur')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bug' => 'danger',
                        'feature' => 'info',
                        'compliment' => 'success',
                        'complaint' => 'warning',
                        'other' => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Feedback::TYPES[$state] ?? $state),

                Tables\Columns\TextColumn::make('subject')
                    ->label('Sujet')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('rating')
                    ->label('Note')
                    ->badge()
                    ->color(fn (?int $state): string => match (true) {
                        $state >= 4 => 'success',
                        $state >= 3 => 'warning',
                        $state >= 2 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (?int $state): string => $state ? $state . '/5' : 'N/A'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Statut')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'new' => 'info',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => Feedback::STATUSES[$state] ?? $state),



                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Modifié le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Type')
                    ->options(Feedback::TYPES),

                Tables\Filters\SelectFilter::make('status')
                    ->label('Statut')
                    ->options(Feedback::STATUSES),



                Tables\Filters\SelectFilter::make('rating')
                    ->label('Note')
                    ->options([
                        '1' => '1/5',
                        '2' => '2/5',
                        '3' => '3/5',
                        '4' => '4/5',
                        '5' => '5/5',
                    ]),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Utilisateur')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->defaultSort('created_at', 'desc')
            ->poll('30s');
    }
}