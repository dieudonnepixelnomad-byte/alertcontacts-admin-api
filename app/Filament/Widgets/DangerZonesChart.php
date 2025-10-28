<?php

namespace App\Filament\Widgets;

use App\Models\DangerZone;
use Filament\Widgets\ChartWidget;

class DangerZonesChart extends ChartWidget
{
    protected static ?int $sort = 4;

    public function getHeading(): ?string
    {
        return 'Répartition des zones de danger par gravité';
    }

    protected function getData(): array
    {
        $dangerZones = DangerZone::selectRaw('severity, COUNT(*) as count')
            ->groupBy('severity')
            ->pluck('count', 'severity')
            ->toArray();

        $labels = [];
        $data = [];
        $colors = [
            'high' => '#ef4444',
            'med' => '#f97316',
            'low' => '#eab308',
        ];

        $severityLabels = [
            'high' => 'Élevé',
            'med' => 'Moyen',
            'low' => 'Faible',
        ];

        foreach ($dangerZones as $severity => $count) {
            $labels[] = $severityLabels[$severity] ?? ucfirst($severity);
            $data[] = $count;
        }

        return [
            'datasets' => [
                [
                    'label' => 'Zones de danger',
                    'data' => $data,
                    'backgroundColor' => array_values($colors),
                    'borderColor' => array_values($colors),
                    'borderWidth' => 1,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
