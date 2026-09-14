<?php

declare(strict_types=1);

namespace App\Livewire\Chat;

use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class ChatSidebarNav extends Component
{
    public ?int $activeSessionId = null;

    public ?int $renamingSessionId = null;

    public string $renameTitle = '';

    /**
     * @return Collection<int, ChatSession>
     */
    #[Computed]
    public function sessions(): Collection
    {
        $userId = Auth::id();
        if ($userId === null) {
            return new Collection;
        }

        return ChatSession::where('user_id', $userId)
            ->withCount('messages')
            ->latest('updated_at')
            ->take(10)
            ->get();
    }

    public function selectSession(int $sessionId): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if ($session !== null) {
            $this->activeSessionId = $session->id;
            $this->dispatch('open-copilot-drawer', sessionId: $session->id);
        }
    }

    public function createNewSession(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Executive Session '.now()->format('d M Y H:i'),
            'context_mode' => 'executive',
        ]);

        $this->activeSessionId = $session->id;
        $this->dispatch('open-copilot-drawer', sessionId: $session->id);
        $this->dispatch('chat-sessions-updated');
        $this->dispatch('refresh-sidebar');
    }

    public function startRenaming(int $sessionId): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if ($session !== null) {
            $this->renamingSessionId = $session->id;
            $this->renameTitle = $session->title;
        }
    }

    public function saveRename(): void
    {
        $userId = Auth::id();
        if ($userId === null || $this->renamingSessionId === null) {
            return;
        }

        $title = trim($this->renameTitle);
        if ($title !== '') {
            ChatSession::where('id', $this->renamingSessionId)
                ->where('user_id', $userId)
                ->update(['title' => $title]);
        }

        $this->renamingSessionId = null;
        $this->renameTitle = '';
        $this->dispatch('chat-sessions-updated');
        $this->dispatch('refresh-sidebar');
    }

    public function cancelRename(): void
    {
        $this->renamingSessionId = null;
        $this->renameTitle = '';
    }

    public function deleteSession(int $sessionId): void
    {
        $userId = Auth::id();
        if ($userId === null) {
            return;
        }

        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if ($session !== null) {
            $session->messages()->delete();
            $session->delete();

            if ($this->activeSessionId === $sessionId) {
                $this->activeSessionId = null;
            }

            $this->dispatch('chat-sessions-updated');
            $this->dispatch('refresh-sidebar');
        }
    }

    #[On('chat-sessions-updated')]
    #[On('refresh-sidebar')]
    public function refreshList(): void
    {
        unset($this->sessions);
    }

    #[On('open-copilot-drawer')]
    public function handleDrawerOpen(?string $initialPrompt = null, ?int $sessionId = null, ?int $bundleId = null): void
    {
        if ($sessionId !== null) {
            $this->activeSessionId = $sessionId;
        }
    }

    public function render(): View
    {
        return view('livewire.chat.chat-sidebar-nav');
    }
}
