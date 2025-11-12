<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Queue;

class QueueStats extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Default Queue', Queue::size('default')),
            Stat::make('Notifications Queue', Queue::size('notifications')),
            Stat::make('Emails Queue', Queue::size('emails')),
        ];
    }
}
