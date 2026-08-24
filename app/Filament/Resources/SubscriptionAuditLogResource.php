<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SubscriptionAuditLogResource\Pages;
use App\Models\SubscriptionAuditLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use UnitEnum;

class SubscriptionAuditLogResource extends Resource
{
    protected static ?string $model = SubscriptionAuditLog::class;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-credit-card';
    protected static ?string $navigationLabel = 'Journal abonnements';
    protected static ?string $modelLabel = 'Événement abonnement';
    protected static ?string $pluralModelLabel = 'Journal abonnements';
    protected static string|UnitEnum|null $navigationGroup = 'Audit & Logs';
    protected static ?int $navigationSort = 2;

    public static function table(Table $table): Table
    {
        return $table->defaultSort('created_at', 'desc')->columns([
            Tables\Columns\TextColumn::make('created_at')->label('Reçu le')->dateTime('d/m/Y H:i:s')->sortable(),
            Tables\Columns\TextColumn::make('user.email')->label('Utilisateur')->searchable()->placeholder('Utilisateur inconnu'),
            Tables\Columns\TextColumn::make('event_type')->label('Événement')->badge(),
            Tables\Columns\TextColumn::make('outcome')->label('Résultat')->badge()
                ->color(fn (string $state): string => match ($state) { 'processed' => 'success', 'duplicate' => 'gray', 'ignored' => 'warning', default => 'danger' }),
            Tables\Columns\TextColumn::make('product_id')->label('Produit')->toggleable(),
            Tables\Columns\TextColumn::make('previous_tier')->label('Avant')->toggleable(),
            Tables\Columns\TextColumn::make('resulting_tier')->label('Après')->toggleable(),
            Tables\Columns\TextColumn::make('details')->label('Détails')->formatStateUsing(fn ($state) => $state ? json_encode($state, JSON_UNESCAPED_UNICODE) : '—')->wrap()->limit(100),
        ])->filters([
            Tables\Filters\SelectFilter::make('outcome')->options(['processed' => 'Traité', 'duplicate' => 'Doublon', 'ignored' => 'Ignoré', 'error' => 'Erreur']),
            Tables\Filters\SelectFilter::make('event_type')->options(array_combine(['INITIAL_PURCHASE','RENEWAL','TRIAL_STARTED','TRIAL_CONVERTED','UNCANCELLATION','CANCELLATION','EXPIRATION','TRIAL_CANCELLED','PRODUCT_CHANGE'], ['INITIAL_PURCHASE','RENEWAL','TRIAL_STARTED','TRIAL_CONVERTED','UNCANCELLATION','CANCELLATION','EXPIRATION','TRIAL_CANCELLED','PRODUCT_CHANGE'])),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListSubscriptionAuditLogs::route('/')];
    }

    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
}
