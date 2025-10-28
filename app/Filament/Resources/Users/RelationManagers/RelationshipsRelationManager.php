<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Relationship;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class RelationshipsRelationManager extends RelationManager
{
    protected static string $relationship = 'myContacts';

    protected static ?string $title = 'Mes Contacts';

    protected static ?string $modelLabel = 'Contact';

    protected static ?string $pluralModelLabel = 'Contacts';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('contact_id')
                    ->label('Contact')
                    ->relationship('contact', 'name')
                    ->required()
                    ->searchable()
                    ->preload(),

                Forms\Components\Select::make('share_level')
                    ->label('Niveau de partage')
                    ->options([
                        'none' => 'Aucun',
                        'alerts_only' => 'Alertes uniquement',
                        'real_time' => 'Temps réel',
                    ])
                    ->required()
                    ->default('alerts_only'),

                Forms\Components\Toggle::make('is_active')
                    ->label('Actif')
                    ->default(true),

                Forms\Components\Textarea::make('notes')
                    ->label('Notes')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('contact.name')
            ->columns([
                Tables\Columns\TextColumn::make('contact.name')
                    ->label('Nom du contact')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('contact.email')
                    ->label('Email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('share_level')
                    ->label('Niveau de partage')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'none' => 'gray',
                        'alerts_only' => 'warning',
                        'real_time' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Actif')
                    ->boolean(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Créé le')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('share_level')
                    ->label('Niveau de partage')
                    ->options([
                        'none' => 'Aucun',
                        'alerts_only' => 'Alertes uniquement',
                        'real_time' => 'Temps réel',
                    ]),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Actif'),
            ])
            ->headerActions([
                // Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                // Tables\Actions\EditAction::make(),
                // Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                // Tables\Actions\BulkActionGroup::make([
                //     Tables\Actions\DeleteBulkAction::make(),
                // ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
