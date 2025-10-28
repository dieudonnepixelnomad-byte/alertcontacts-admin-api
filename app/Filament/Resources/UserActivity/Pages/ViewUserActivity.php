<?php

namespace App\Filament\Resources\UserActivity\Pages;

use App\Filament\Resources\UserActivityResource;
use Filament\Resources\Pages\ViewRecord;

class ViewUserActivity extends ViewRecord
{
    protected static string $resource = UserActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Pas d'actions d'édition ou de suppression
        ];
    }
}