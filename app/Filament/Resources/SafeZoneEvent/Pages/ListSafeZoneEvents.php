<?php

namespace App\Filament\Resources\SafeZoneEvent\Pages;

use App\Filament\Resources\SafeZoneEventResource;
use Filament\Resources\Pages\ListRecords;

class ListSafeZoneEvents extends ListRecords
{
    protected static string $resource = SafeZoneEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas d'action de création car les événements sont automatiques
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Widget temporairement désactivé
        ];
    }
}