<?php

namespace App\Filament\Pages;

use App\Models\PersonalTask;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class ExecutiveAssistant extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Executive Copilot';

    protected static ?string $title = 'Executive Copilot';

    protected static string|\UnitEnum|null $navigationGroup = 'Copilot Command Center';

    protected string $view = 'filament.pages.executive-assistant';

    public function toggleTaskStatus(int $taskId): void
    {
        $task = PersonalTask::where('user_id', Auth::id())->find($taskId);
        if ($task) {
            $task->status = $task->status === 'completed' ? 'pending' : 'completed';
            $task->save();
        }
    }
}
