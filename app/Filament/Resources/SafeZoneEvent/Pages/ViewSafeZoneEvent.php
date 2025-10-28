<?php

namespace App\Filament\Resources\SafeZoneEvent\Pages;

use App\Filament\Resources\SafeZoneEventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSafeZoneEvent extends ViewRecord
{
    protected static string $resource = SafeZoneEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas d'actions d'édition ou de suppression
        ];
    }
}