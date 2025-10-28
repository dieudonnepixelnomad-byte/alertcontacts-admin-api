<?php

namespace App\Filament\Resources\Feedback\Widgets;

use App\Models\Feedback;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FeedbackStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $totalFeedback = Feedback::count();
        $pendingFeedback = Feedback::where('status', 'pending')->count();
        $resolvedFeedback = Feedback::where('status', 'resolved')->count();
        $averageRating = Feedback::whereNotNull('rating')->avg('rating');

        return [
            Stat::make('Total Feedbacks', $totalFeedback)
                ->description('Nombre total de feedbacks')
                ->descriptionIcon('heroicon-m-chat-bubble-left-right')
                ->color('primary'),

            Stat::make('En attente', $pendingFeedback)
                ->description('Feedbacks en attente de traitement')
                ->descriptionIcon('heroicon-m-clock')
                ->color($pendingFeedback > 10 ? 'danger' : ($pendingFeedback > 5 ? 'warning' : 'success')),

            Stat::make('Résolus', $resolvedFeedback)
                ->description('Feedbacks traités et résolus')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Note moyenne', $averageRating ? number_format($averageRating, 1) . '/5' : 'N/A')
                ->description('Note moyenne des utilisateurs')
                ->descriptionIcon('heroicon-m-star')
                ->color($averageRating >= 4 ? 'success' : ($averageRating >= 3 ? 'warning' : 'danger')),
        ];
    }
}