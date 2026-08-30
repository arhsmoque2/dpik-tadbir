<?php

namespace App\Livewire;

use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ExecutivePreset;
use App\Models\User;
use App\Services\Ai\AgentService;
use App\Services\Mcp\OutlookMcpBridge;
use App\Services\Presets\PresetExecutionService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

class AiCopilotDrawer extends Component
{
    public bool $isOpen = false;

    public ?int $activeSessionId = null;

    public string $inputPrompt = '';

    public bool $isProcessing = false;

    /**
     * @var array{
     *     id: string,
     *     name: string,
     *     arguments: array<string, mixed>,
     *     suspension_payload: array<string, mixed>
     * }|null
     */
    public ?array $suspendedToolCall = null;

    public string $choiceSelection = '';

    /**
     * @var array<int, string>
     */
    public array $multiSelections = [];

    public string $choiceNotes = '';

    public string $outlookStatus = 'checking';

    public string $statusMessage = '';

    public function mount(?int $sessionId = null): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        if ($sessionId !== null) {
            $session = ChatSession::where('id', $sessionId)
                ->where('user_id', $user->id)
                ->first();
            if ($session !== null) {
                $this->activeSessionId = $session->id;
            }
        }

        if ($this->activeSessionId === null) {
            $latest = ChatSession::where('user_id', $user->id)
                ->latest('updated_at')
                ->first();

            if ($latest !== null) {
                $this->activeSessionId = $latest->id;
            } else {
                $created = ChatSession::create([
                    'user_id' => $user->id,
                    'title' => 'Executive Briefing '.now()->format('d M Y'),
                    'context_mode' => 'executive',
                ]);
                $this->activeSessionId = $created->id;
            }
        }

        $this->refreshOutlookStatus();
    }

    public function refreshOutlookStatus(): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            $this->outlookStatus = 'offline';

            return;
        }

        try {
            $bridge = app(OutlookMcpBridge::class)->forUser($user);
            $this->outlookStatus = $bridge->checkAuthStatus() ? 'online' : 'offline';
        } catch (\Throwable $e) {
            $this->outlookStatus = 'offline';
        }
    }

    #[On('open-copilot-drawer')]
    public function openDrawer(?string $initialPrompt = null, ?int $sessionId = null): void
    {
        $this->isOpen = true;

        if ($sessionId !== null) {
            $this->switchSession($sessionId);
        }

        if (! empty($initialPrompt)) {
            $this->inputPrompt = $initialPrompt;
            $this->sendMessage();
        }
    }

    #[On('toggle-copilot-drawer')]
    public function toggleDrawer(): void
    {
        $this->isOpen = ! $this->isOpen;
        if ($this->isOpen && $this->activeSessionId === null) {
            $this->mount();
        }
    }

    public function closeDrawer(): void
    {
        $this->isOpen = false;
    }

    public function sendMessage(?string $overridePrompt = null): void
    {
        $prompt = trim($overridePrompt ?? $this->inputPrompt);
        if (empty($prompt)) {
            return;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = $this->ensureActiveSession($user);
        $this->isProcessing = true;
        $this->inputPrompt = '';
        $this->statusMessage = 'Analyzing and retrieving context...';

        try {
            $agent = app(AgentService::class);
            $turnResponse = $agent->handleUserTurn($session, $prompt);

            /** @var array{id: string, name: string, arguments: array<string, mixed>, suspension_payload: array<string, mixed>}|null $suspended */
            $suspended = $turnResponse->suspendedToolCall;
            $this->suspendedToolCall = $suspended;

            $this->choiceSelection = '';
            $this->multiSelections = [];
            $this->choiceNotes = '';
            $this->statusMessage = '';
        } catch (\Throwable $e) {
            Log::error('Copilot turn failed', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Turn processing failed: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    #[On('run-preset')]
    public function runPreset(int $presetId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $preset = ExecutivePreset::where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
            ->find($presetId);

        if ($preset === null) {
            return;
        }

        $executionService = app(PresetExecutionService::class);
        $rendered = $executionService->renderPrompt($preset, [], $user);

        $this->isOpen = true;
        $this->sendMessage($rendered);
    }

    #[On('ask-copilot-about')]
    public function askAbout(string $subject, ?string $context = null): void
    {
        $this->isOpen = true;
        $prompt = "Please provide an executive summary and status analysis for {$subject}."
            .($context ? " Context: {$context}" : '');
        $this->sendMessage($prompt);
    }

    public function approveActionCard(string $approvalToken): void
    {
        if ($this->suspendedToolCall === null || $this->suspendedToolCall['name'] !== 'propose_action_card') {
            return;
        }

        $toolUseId = $this->suspendedToolCall['id'];
        $card = $this->suspendedToolCall['suspension_payload']['card'] ?? [];
        $token = $this->suspendedToolCall['suspension_payload']['approval_token'] ?? $approvalToken;

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = $this->ensureActiveSession($user);
        $this->isProcessing = true;

        try {
            $agent = app(AgentService::class);
            $turnResponse = $agent->resumeWithToolResult($session, $toolUseId, [
                'action' => 'approved',
                'approval_token' => $token,
                'card' => $card,
            ]);

            /** @var array{id: string, name: string, arguments: array<string, mixed>, suspension_payload: array<string, mixed>}|null $suspended */
            $suspended = $turnResponse->suspendedToolCall;
            $this->suspendedToolCall = $suspended;
        } catch (\Throwable $e) {
            Log::error('Action approval failed', ['error' => $e->getMessage()]);
            $this->statusMessage = 'Approval failed: '.$e->getMessage();
        } finally {
            $this->isProcessing = false;
        }
    }

    public function discardActionCard(string $approvalToken): void
    {
        if ($this->suspendedToolCall === null) {
            return;
        }

        $toolUseId = $this->suspendedToolCall['id'];
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = $this->ensureActiveSession($user);
        $this->isProcessing = true;

        try {
            $agent = app(AgentService::class);
            $turnResponse = $agent->resumeWithToolResult($session, $toolUseId, [
                'action' => 'discarded',
                'approval_token' => $approvalToken,
            ]);

            /** @var array{id: string, name: string, arguments: array<string, mixed>, suspension_payload: array<string, mixed>}|null $suspended */
            $suspended = $turnResponse->suspendedToolCall;
            $this->suspendedToolCall = $suspended;
        } finally {
            $this->isProcessing = false;
        }
    }

    public function submitChoice(): void
    {
        if ($this->suspendedToolCall === null || $this->suspendedToolCall['name'] !== 'ask_user_question') {
            return;
        }

        $toolUseId = $this->suspendedToolCall['id'];
        $isMulti = (bool) ($this->suspendedToolCall['arguments']['is_multi_select'] ?? false);
        $selection = $isMulti ? $this->multiSelections : $this->choiceSelection;

        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = $this->ensureActiveSession($user);
        $this->isProcessing = true;

        try {
            $agent = app(AgentService::class);
            $turnResponse = $agent->resumeWithToolResult($session, $toolUseId, [
                'action' => 'answered',
                'selection' => $selection,
                'notes' => trim($this->choiceNotes),
            ]);

            /** @var array{id: string, name: string, arguments: array<string, mixed>, suspension_payload: array<string, mixed>}|null $suspended */
            $suspended = $turnResponse->suspendedToolCall;
            $this->suspendedToolCall = $suspended;

            $this->choiceSelection = '';
            $this->multiSelections = [];
            $this->choiceNotes = '';
        } finally {
            $this->isProcessing = false;
        }
    }

    public function skipChoice(): void
    {
        if ($this->suspendedToolCall === null) {
            return;
        }

        $toolUseId = $this->suspendedToolCall['id'];
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = $this->ensureActiveSession($user);
        $this->isProcessing = true;

        try {
            $agent = app(AgentService::class);
            $turnResponse = $agent->resumeWithToolResult($session, $toolUseId, [
                'action' => 'skipped',
            ]);

            /** @var array{id: string, name: string, arguments: array<string, mixed>, suspension_payload: array<string, mixed>}|null $suspended */
            $suspended = $turnResponse->suspendedToolCall;
            $this->suspendedToolCall = $suspended;
        } finally {
            $this->isProcessing = false;
        }
    }

    public function newSession(): void
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
        $this->suspendedToolCall = null;
        $this->inputPrompt = '';
        $this->statusMessage = '';
    }

    public function switchSession(int $sessionId): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $session = ChatSession::where('id', $sessionId)
            ->where('user_id', $user->id)
            ->first();

        if ($session !== null) {
            $this->activeSessionId = $session->id;
            $this->suspendedToolCall = null;
        }
    }

    protected function ensureActiveSession(User $user): ChatSession
    {
        if ($this->activeSessionId !== null) {
            $session = ChatSession::where('id', $this->activeSessionId)
                ->where('user_id', $user->id)
                ->first();

            if ($session !== null) {
                return $session;
            }
        }

        $session = ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Executive Session '.now()->format('d M Y'),
            'context_mode' => 'executive',
        ]);

        $this->activeSessionId = $session->id;

        return $session;
    }

    /**
     * @return Collection<int, ChatMessage>
     */
    #[Computed]
    public function messages(): Collection
    {
        if ($this->activeSessionId === null) {
            return new Collection;
        }

        return ChatMessage::where('chat_session_id', $this->activeSessionId)
            ->orderBy('id', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, ExecutivePreset>
     */
    #[Computed]
    public function presets(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return new Collection;
        }

        return ExecutivePreset::where('is_active', true)
            ->where(function ($q) use ($user) {
                $q->where('user_id', $user->id)
                    ->orWhereNull('user_id');
            })
            ->orderBy('sort_order', 'asc')
            ->get();
    }

    /**
     * @return Collection<int, ChatSession>
     */
    #[Computed]
    public function sessions(): Collection
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return new Collection;
        }

        return ChatSession::where('user_id', $user->id)
            ->latest('updated_at')
            ->take(10)
            ->get();
    }

    public function render(): View
    {
        return view('livewire.ai-copilot-drawer');
    }
}
