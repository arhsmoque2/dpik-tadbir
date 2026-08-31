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

    public string $activeProvider = 'anthropic';

    public string $activeModel = 'claude-3-7-sonnet-20250219';

    public bool $isModelSwapperOpen = false;

    public function mount(?int $sessionId = null): void
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return;
        }

        $this->initializeActiveModel($user);

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

    /**
     * Deliberately not #[On('toggle-copilot-drawer')] — both trigger buttons
     * (topbar, hero page) dispatch that same event via a raw
     * $dispatch('toggle-copilot-drawer'), which the drawer's own Alpine
     * x-data already listens for directly (@toggle-copilot-drawer.window="isOpen
     * = !isOpen", entangled with $isOpen). Livewire auto-binds #[On(...)]
     * methods to window-dispatched events too, so with both listeners active
     * one click fired an instant client-side flip AND a server round-trip
     * that flipped it back on sync — the drawer would silently no-op or
     * flicker depending on timing. Alpine is now the sole owner of the
     * toggle; this method is reachable only via ⌘J's direct
     * $wire.toggleDrawer() call (a real Livewire method invocation, not the
     * window event), which was never part of the race. mount() already
     * unconditionally establishes activeSessionId, so the old re-init guard
     * here was dead code in practice.
     */
    public function toggleDrawer(): void
    {
        $this->isOpen = ! $this->isOpen;
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
            $turnResponse = $agent->handleUserTurn(
                session: $session,
                prompt: $prompt,
                options: [
                    'provider' => $this->activeProvider,
                    'model' => $this->activeModel,
                ]
            );

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

    public function initializeActiveModel(User $user): void
    {
        $tuple = (string) ($user->favorite_model_1 ?: 'anthropic:claude-3-7-sonnet-20250219');
        [$provider, $model] = $this->parseModelTuple($tuple);
        $this->activeProvider = $provider;
        $this->activeModel = $model;
    }

    /**
     * @return array{0: string, 1: string}
     */
    public function parseModelTuple(string $tuple): array
    {
        $clean = trim($tuple);

        if (str_contains($clean, ':')) {
            $parts = explode(':', $clean, 2);

            return [trim($parts[0]), trim($parts[1])];
        }

        if (str_starts_with($clean, 'anthropic/')) {
            $modelName = substr($clean, 10);
            if ($modelName === 'claude-3.7-sonnet') {
                $modelName = 'claude-3-7-sonnet-20250219';
            }

            return ['anthropic', $modelName];
        }

        if (str_starts_with($clean, 'google/') || str_starts_with($clean, 'gemini/')) {
            $parts = explode('/', $clean, 2);

            return ['gemini', $parts[1]];
        }

        if (str_starts_with($clean, 'openrouter/')) {
            return ['openrouter', substr($clean, 11)];
        }

        if (str_contains($clean, '/')) {
            return ['openrouter', $clean];
        }

        return ['anthropic', $clean];
    }

    public function toggleModelSwapper(): void
    {
        $this->isModelSwapperOpen = ! $this->isModelSwapperOpen;
    }

    public function selectModel(string $modelTuple): void
    {
        [$provider, $model] = $this->parseModelTuple($modelTuple);
        $this->activeProvider = $provider;
        $this->activeModel = $model;
        $this->isModelSwapperOpen = false;

        $this->dispatch('copilot-model-changed', provider: $provider, model: $model);
    }

    public function getActiveModelBadgeLabel(): string
    {
        $labels = [
            'claude-3-7-sonnet-20250219' => 'Claude 3.7 Sonnet',
            'claude-3.7-sonnet' => 'Claude 3.7 Sonnet',
            'deepseek/deepseek-r1' => 'DeepSeek R1',
            'gemini-2.5-flash' => 'Gemini 2.5 Flash',
            'gemini-2.5-pro' => 'Gemini 2.5 Pro',
            'anthropic/claude-3.7-sonnet' => 'Claude 3.7 Sonnet',
            'google/gemini-2.5-pro' => 'Gemini 2.5 Pro',
            'openai/gpt-4o' => 'GPT-4o',
            'meta-llama/llama-3.3-70b-instruct' => 'Llama 3.3 70B',
        ];

        $providerName = match ($this->activeProvider) {
            'anthropic' => 'Anthropic',
            'gemini' => 'Google',
            'openrouter' => 'OpenRouter',
            default => ucfirst($this->activeProvider),
        };

        $modelName = $labels[$this->activeModel] ?? $this->activeModel;

        return "{$providerName} · {$modelName}";
    }

    /**
     * @return list<array{slot: int, tuple: string, provider: string, model: string, label: string, is_active: bool}>
     */
    #[Computed]
    public function favoriteModels(): array
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            return [];
        }

        $slots = [
            1 => (string) ($user->favorite_model_1 ?: 'anthropic:claude-3-7-sonnet-20250219'),
            2 => (string) ($user->favorite_model_2 ?: 'openrouter:deepseek/deepseek-r1'),
            3 => (string) ($user->favorite_model_3 ?: 'gemini:gemini-2.5-flash'),
        ];

        $labels = [
            'anthropic:claude-3-7-sonnet-20250219' => 'Anthropic · Claude 3.7 Sonnet',
            'anthropic/claude-3.7-sonnet' => 'Anthropic · Claude 3.7 Sonnet',
            'openrouter:deepseek/deepseek-r1' => 'OpenRouter · DeepSeek R1',
            'deepseek/deepseek-r1' => 'OpenRouter · DeepSeek R1',
            'gemini:gemini-2.5-flash' => 'Google · Gemini 2.5 Flash',
            'google/gemini-2.5-pro' => 'Google · Gemini 2.5 Pro',
            'gemini:gemini-2.5-pro' => 'Google · Gemini 2.5 Pro',
            'openrouter:anthropic/claude-3.7-sonnet' => 'OpenRouter · Claude 3.7 Sonnet',
            'openrouter:google/gemini-2.5-pro' => 'OpenRouter · Gemini 2.5 Pro',
            'openrouter:openai/gpt-4o' => 'OpenRouter · GPT-4o',
            'openrouter:meta-llama/llama-3.3-70b-instruct' => 'OpenRouter · Llama 3.3 70B',
        ];

        $result = [];
        foreach ($slots as $slot => $tuple) {
            [$provider, $model] = $this->parseModelTuple($tuple);
            $label = $labels[$tuple] ?? (ucfirst($provider).' · '.$model);
            $isActive = ($this->activeProvider === $provider && $this->activeModel === $model);

            $result[] = [
                'slot' => $slot,
                'tuple' => $tuple,
                'provider' => $provider,
                'model' => $model,
                'label' => $label,
                'is_active' => $isActive,
            ];
        }

        return $result;
    }

    #[On('copilot-model-changed')]
    public function onModelChanged(?string $provider = null, ?string $model = null): void
    {
        if ($provider !== null && $model !== null) {
            $this->activeProvider = $provider;
            $this->activeModel = $model;
        } else {
            $user = Auth::user();
            if ($user instanceof User) {
                $this->initializeActiveModel($user);
            }
        }
    }

    public function render(): View
    {
        return view('livewire.ai-copilot-drawer');
    }
}
