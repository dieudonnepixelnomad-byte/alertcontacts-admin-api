<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use App\Services\RevenueCatSubscriptionSyncService;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('synchronizeSubscription')
                ->label('Resynchroniser l’abonnement')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $result = app(RevenueCatSubscriptionSyncService::class)->synchronize($this->record);
                    Notification::make()->success()->title('Abonnement resynchronisé')
                        ->body($result['active'] ? 'Accès Premium confirmé.' : 'Aucun entitlement Premium actif.')->send();
                }),
            Actions\EditAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
