<?php

namespace App\Filament\Resources\DangerZones\Pages;

use App\Filament\Resources\DangerZoneResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDangerZones extends ListRecords
{
    protected static string $resource = DangerZoneResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}