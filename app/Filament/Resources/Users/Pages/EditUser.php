<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use App\Services\RevenueCatSubscriptionSyncService;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('synchronizeSubscription')
                ->label('Resynchroniser l’abonnement')
                ->icon('heroicon-o-arrow-path')
                ->requiresConfirmation()
                ->action(function () {
                    $result = app(RevenueCatSubscriptionSyncService::class)->synchronize($this->record);
                    Notification::make()->success()->title('Abonnement resynchronisé')
                        ->body($result['active'] ? 'Accès Premium confirmé.' : 'Aucun entitlement Premium actif.')->send();
                }),
            DeleteAction::make(),
        ];
    }
}
