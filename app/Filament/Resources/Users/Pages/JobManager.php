<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use BackedEnum;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use App\Filament\Widgets\QueueStats;
use App\Filament\Widgets\FailedJobs;

class JobManager extends Page
{
    protected static string $resource = UserResource::class;

    protected string $view = 'filament.resources.users.pages.job-manager';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $title = 'Gestion des Jobs';

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('retry_failed')
                ->label('Relancer les jobs échoués')
                ->color('success')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    Artisan::call('queue:retry all');
                    Notification::make()
                        ->title('Les jobs échoués ont été relancés')
                        ->success()
                        ->send();
                }),
            Actions\Action::make('flush_failed')
                ->label('Purger les jobs échoués')
                ->color('danger')
                ->icon('heroicon-o-trash')
                ->requiresConfirmation()
                ->action(function () {
                    Artisan::call('queue:flush');
                    Notification::make()
                        ->title('La liste des jobs échoués a été purgée')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            QueueStats::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            FailedJobs::class,
        ];
    }
}
