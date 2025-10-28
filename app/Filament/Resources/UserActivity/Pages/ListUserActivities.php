<?php

namespace App\Filament\Resources\UserActivity\Pages;

use App\Filament\Resources\UserActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListUserActivities extends ListRecords
{
    protected static string $resource = UserActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas d'action de création car les activités sont automatiques
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            // Widget temporairement désactivé
        ];
    }
}