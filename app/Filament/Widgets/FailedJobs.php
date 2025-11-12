<?php

namespace App\Filament\Widgets;

use App\Models\FailedJob;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class FailedJobs extends BaseWidget
{
    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->query(FailedJob::query())
            ->columns([
                Tables\Columns\TextColumn::make('uuid')->label('UUID')->searchable(),
                Tables\Columns\TextColumn::make('connection')->label('Connection'),
                Tables\Columns\TextColumn::make('queue')->label('Queue'),
                Tables\Columns\TextColumn::make('failed_at')->label('Failed At')->dateTime(),
            ])
            ->defaultSort('failed_at', 'desc');
    }
}
