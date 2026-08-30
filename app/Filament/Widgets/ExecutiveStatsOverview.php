<?php

namespace App\Filament\Widgets;

use App\Models\AiActionReceipt;
use App\Models\PersonalNote;
use App\Models\PersonalTask;
use App\Models\ProjectRegistryEntry;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ExecutiveStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $userId = auth()->id() ?? 1;

        $totalMemory = ProjectRegistryEntry::count();
        $pendingTasks = PersonalTask::where('user_id', $userId)->where('status', 'pending')->count();
        $executedActions = AiActionReceipt::where('user_id', $userId)->where('status', 'executed')->count();
        $personalNotes = PersonalNote::where('user_id', $userId)->count();

        return [
            Stat::make('Domain Memory Records', $totalMemory)
                ->description('Company-wide FTS5 indexed project entries')
                ->descriptionIcon('heroicon-m-folder')
                ->color('primary'),
            Stat::make('Pending Actions & Tasks', $pendingTasks)
                ->description('Private follow-up items')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),
            Stat::make('Executed AI Actions', $executedActions)
                ->description('Immutable action ledger entries')
                ->descriptionIcon('heroicon-m-check-badge')
                ->color('success'),
            Stat::make('Personal Notes', $personalNotes)
                ->description('Sovereign executive workspace notes')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('gray'),
        ];
    }
}
