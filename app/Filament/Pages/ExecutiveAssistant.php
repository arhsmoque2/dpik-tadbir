<?php

namespace App\Filament\Pages;

use App\Models\ChatSession;
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

    public function startNewSession(): void
    {
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Executive Briefing '.now()->format('d M Y H:i'),
            'context_mode' => 'executive',
        ]);

        $this->dispatch('open-copilot-drawer', sessionId: $session->id);
    }

    public function deleteSession(int $sessionId): void
    {
        $session = ChatSession::where('user_id', Auth::id())->find($sessionId);
        if ($session) {
            $session->delete();
        }
    }

    public function toggleTaskStatus(int $taskId): void
    {
        $task = PersonalTask::where('user_id', Auth::id())->find($taskId);
        if ($task) {
            $task->status = $task->status === 'completed' ? 'pending' : 'completed';
            $task->save();
        }
    }
}
