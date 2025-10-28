<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DangerZonesChart;
use App\Filament\Widgets\LatestActivity;
use App\Filament\Widgets\PerformanceMetrics;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UsersChart;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected static ?string $navigationLabel = 'Tableau de bord';

    protected static ?int $navigationSort = 1;

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            UsersChart::class,
            DangerZonesChart::class,
            PerformanceMetrics::class,
            LatestActivity::class,
        ];
    }

    public function getColumns(): int|array
    {
        return [
            'sm' => 1,
            'md' => 2,
            'lg' => 3,
            'xl' => 4,
        ];
    }
}
