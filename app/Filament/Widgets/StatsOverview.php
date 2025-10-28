<?php

namespace App\Filament\Widgets;

use App\Models\DangerZone;
use App\Models\Invitation;
use App\Models\Relationship;
use App\Models\SafeZone;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Utilisateurs totaux', User::count())
                ->description('Nombre total d\'utilisateurs inscrits')
                ->descriptionIcon('heroicon-m-users')
                ->color('success')
                ->chart([7, 2, 10, 3, 15, 4, 17]),

            Stat::make('Zones de danger', DangerZone::count())
                ->description('Zones signalées comme dangereuses')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger')
                ->chart([2, 5, 3, 8, 4, 6, 5]),

            Stat::make('Zones de sécurité', SafeZone::count())
                ->description('Zones de sécurité configurées')
                ->descriptionIcon('heroicon-m-shield-check')
                ->color('primary')
                ->chart([1, 3, 2, 4, 3, 5, 4]),

            Stat::make('Relations actives', Relationship::where('status', 'accepted')->count())
                ->description('Connexions entre utilisateurs')
                ->descriptionIcon('heroicon-m-link')
                ->color('info')
                ->chart([3, 1, 4, 2, 6, 3, 7]),

            Stat::make('Invitations en attente', Invitation::where('status', 'pending')->count())
                ->description('Invitations non encore acceptées')
                ->descriptionIcon('heroicon-m-paper-airplane')
                ->color('warning')
                ->chart([2, 4, 1, 3, 2, 5, 3]),

            Stat::make('Nouveaux utilisateurs (7j)', User::where('created_at', '>=', now()->subDays(7))->count())
                ->description('Inscriptions cette semaine')
                ->descriptionIcon('heroicon-m-user-plus')
                ->color('success')
                ->chart([1, 2, 0, 3, 1, 2, 4]),
        ];
    }
}