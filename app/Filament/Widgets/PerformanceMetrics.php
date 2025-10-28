<?php

namespace App\Filament\Widgets;

use App\Models\DangerZone;
use App\Models\Invitation;
use App\Models\Relationship;
use App\Models\SafeZone;
use App\Models\SafeZoneEvent;
use App\Models\User;
use App\Models\UserActivity;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Cache;

class PerformanceMetrics extends BaseWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    protected function getStats(): array
    {
        return Cache::remember('performance_metrics', 30, function () {
            // Métriques de base avec évolution
            $metrics = $this->getBaseMetrics();
            
            // Métriques temps réel
            $realtimeMetrics = $this->getRealtimeMetrics();
            
            // Métriques d'engagement
            $engagementMetrics = $this->getEngagementMetrics();
            
            return array_merge($metrics, $realtimeMetrics, $engagementMetrics);
        });
    }

    private function getBaseMetrics(): array
    {
        // Calcul du taux d'acceptation des invitations avec évolution
        $totalInvitations = Invitation::count();
        $acceptedInvitations = Invitation::where('status', 'accepted')->count();
        $acceptanceRate = $totalInvitations > 0 ? round(($acceptedInvitations / $totalInvitations) * 100, 1) : 0;
        
        // Évolution sur 7 jours
        $lastWeekAccepted = Invitation::where('status', 'accepted')
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $lastWeekTotal = Invitation::where('created_at', '>=', now()->subDays(7))->count();
        $lastWeekRate = $lastWeekTotal > 0 ? round(($lastWeekAccepted / $lastWeekTotal) * 100, 1) : 0;
        $acceptanceTrend = $lastWeekRate - $acceptanceRate;

        // Calcul du taux d'activation des utilisateurs
        $totalUsers = User::count();
        $usersWithSafeZones = SafeZone::distinct('owner_id')->count('owner_id');
        $usersWithDangerZones = DangerZone::distinct('reported_by')->count('reported_by');
        $activeUsers = $usersWithSafeZones + $usersWithDangerZones;
        $activationRate = $totalUsers > 0 ? round(($activeUsers / $totalUsers) * 100, 1) : 0;

        return [
            Stat::make('Taux d\'acceptation des invitations', $acceptanceRate . '%')
                ->description($acceptanceTrend >= 0 ? "+{$acceptanceTrend}% cette semaine" : "{$acceptanceTrend}% cette semaine")
                ->descriptionIcon($acceptanceTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($acceptanceRate >= 70 ? 'success' : ($acceptanceRate >= 50 ? 'warning' : 'danger'))
                ->chart($this->getInvitationChart()),

            Stat::make('Taux d\'activation des utilisateurs', $activationRate . '%')
                ->description('Utilisateurs ayant créé au moins une zone')
                ->descriptionIcon('heroicon-m-user-circle')
                ->color($activationRate >= 80 ? 'success' : ($activationRate >= 60 ? 'warning' : 'danger'))
                ->chart($this->getActivationChart()),
        ];
    }

    private function getRealtimeMetrics(): array
    {
        // Activité en temps réel (dernières 24h)
        $recentActivity = UserActivity::where('created_at', '>=', now()->subDay())->count();
        $recentEvents = SafeZoneEvent::where('captured_at', '>=', now()->subDay())->count();
        
        // Nouvelles inscriptions aujourd'hui
        $todayUsers = User::whereDate('created_at', today())->count();
        $yesterdayUsers = User::whereDate('created_at', now()->subDay()->toDateString())->count();
        $userGrowth = $yesterdayUsers > 0 ? round((($todayUsers - $yesterdayUsers) / $yesterdayUsers) * 100, 1) : 0;

        return [
            Stat::make('Activité 24h', number_format($recentActivity))
                ->description('Actions utilisateurs récentes')
                ->descriptionIcon('heroicon-m-bolt')
                ->color('info')
                ->chart($this->getActivityChart()),

            Stat::make('Événements zones', number_format($recentEvents))
                ->description('Entrées/sorties de zones (24h)')
                ->descriptionIcon('heroicon-m-map-pin')
                ->color('warning')
                ->chart($this->getEventsChart()),
        ];
    }

    private function getEngagementMetrics(): array
    {
        // Calcul du nombre moyen de contacts par utilisateur
        $totalUsers = User::count();
        $totalRelationships = Relationship::count();
        $avgContactsPerUser = $totalUsers > 0 ? round($totalRelationships / $totalUsers, 1) : 0;

        // Calcul du taux de zones de danger confirmées
        $totalDangerZones = DangerZone::count();
        $confirmedDangerZones = DangerZone::where('confirmations', '>', 1)->count();
        $confirmationRate = $totalDangerZones > 0 ? round(($confirmedDangerZones / $totalDangerZones) * 100, 1) : 0;

        // Zones actives vs inactives
        $activeZones = SafeZone::where('is_active', true)->count();
        $totalSafeZones = SafeZone::count();
        $activeZoneRate = $totalSafeZones > 0 ? round(($activeZones / $totalSafeZones) * 100, 1) : 0;

        return [
            Stat::make('Contacts moyens par utilisateur', $avgContactsPerUser)
                ->description('Engagement social moyen')
                ->descriptionIcon('heroicon-m-users')
                ->color($avgContactsPerUser >= 3 ? 'success' : ($avgContactsPerUser >= 2 ? 'warning' : 'info')),

            Stat::make('Taux de confirmation des dangers', $confirmationRate . '%')
                ->description('Zones validées par la communauté')
                ->descriptionIcon('heroicon-m-shield-exclamation')
                ->color($confirmationRate >= 60 ? 'success' : ($confirmationRate >= 40 ? 'warning' : 'danger')),

            Stat::make('Zones actives', $activeZoneRate . '%')
                ->description("{$activeZones}/{$totalSafeZones} zones de sécurité")
                ->descriptionIcon('heroicon-m-shield-check')
                ->color($activeZoneRate >= 80 ? 'success' : ($activeZoneRate >= 60 ? 'warning' : 'danger')),
        ];
    }

    private function getInvitationChart(): array
    {
        return Invitation::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }

    private function getActivationChart(): array
    {
        return User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count')
            ->toArray();
    }

    private function getActivityChart(): array
    {
        return UserActivity::selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count')
            ->toArray();
    }

    private function getEventsChart(): array
    {
        return SafeZoneEvent::selectRaw('HOUR(captured_at) as hour, COUNT(*) as count')
            ->where('captured_at', '>=', now()->subDay())
            ->groupBy('hour')
            ->orderBy('hour')
            ->pluck('count')
            ->toArray();
    }
}
