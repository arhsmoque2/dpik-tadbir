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

class ChatQuickSwitcher extends Component
{
    public bool $isOpen = false;

    public string $search = '';

    public ?int $selectedSessionId = null;

    #[On('open-quick-switcher')]
    public function open(): void
    {
        $this->isOpen = true;
        $this->search = '';
    }

    public function close(): void
    {
        $this->isOpen = false;
        $this->search = '';
    }

    public function toggle(): void
    {
        $this->isOpen = ! $this->isOpen;
        if (! $this->isOpen) {
            $this->search = '';
        }
    }

    /**
     * @return Collection<int, ChatSession>
     */
    #[Computed]
    public function results(): Collection
    {
        $userId = Auth::id();
        if ($userId === null) {
            return new Collection;
        }

        $query = ChatSession::where('user_id', $userId)
            ->withCount('messages');

        $searchTerm = trim($this->search);
        if ($searchTerm !== '') {
            $query->where('title', 'like', '%'.$searchTerm.'%');
        }

        return $query->latest('updated_at')->take(15)->get();
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
            $this->close();
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

        $this->close();
        $this->dispatch('open-copilot-drawer', sessionId: $session->id);
        $this->dispatch('chat-sessions-updated');
        $this->dispatch('refresh-sidebar');
    }

    public function render(): View
    {
        return view('livewire.chat.chat-quick-switcher');
    }
}
