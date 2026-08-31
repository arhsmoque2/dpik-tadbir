<?php

namespace App\Filament\Pages;

use App\Models\ChatSession;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Support\Facades\Auth;

class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Executive Management';

    protected static ?string $navigationLabel = 'Dashboard';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

    protected string $view = 'filament.pages.dashboard';

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
}
