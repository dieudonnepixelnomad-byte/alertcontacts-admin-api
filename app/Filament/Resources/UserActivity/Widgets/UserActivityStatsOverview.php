<?php

namespace App\Filament\Resources\UserActivity\Widgets;

use App\Models\UserActivity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class UserActivityStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalActivities = UserActivity::count();
        $todayActivities = UserActivity::whereDate('created_at', today())->count();
        $authActivities = UserActivity::where('activity_type', UserActivity::ACTIVITY_AUTH)->count();
        $zoneActivities = UserActivity::where('activity_type', UserActivity::ACTIVITY_ZONE)->count();

        return [
            Stat::make('Total Activités', $totalActivities)
                ->description('Nombre total d\'activités enregistrées')
                ->descriptionIcon('heroicon-m-clipboard-document-list')
                ->color('primary'),

            Stat::make('Aujourd\'hui', $todayActivities)
                ->description('Activités d\'aujourd\'hui')
                ->descriptionIcon('heroicon-m-calendar-days')
                ->color('success'),

            Stat::make('Authentifications', $authActivities)
                ->description('Connexions/déconnexions')
                ->descriptionIcon('heroicon-m-key')
                ->color('info'),

            Stat::make('Actions Zones', $zoneActivities)
                ->description('Activités liées aux zones')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning'),
        ];
    }
}