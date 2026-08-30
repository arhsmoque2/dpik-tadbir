<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class LlmGatewayService
{
    protected string $primaryProvider;

    protected string $primaryModel;

    protected string $fallbackProvider;

    protected string $fallbackModel;

    protected ?string $activeProvider = null;

    protected ?string $activeModel = null;

    /**
     * @var array<string, mixed>|null
     */
    protected static ?array $fakeProviders = null;

    /**
     * @var list<mixed>|null
     */
    protected static ?array $fakeSequence = null;

    public function __construct()
    {
        $this->primaryProvider = (string) Config::get('services.ai.default_provider', 'anthropic');
        $this->primaryModel = (string) Config::get('services.ai.default_model', 'claude-3-7-sonnet-20250219');
        $this->fallbackProvider = (string) Config::get('services.ai.fallback_provider', 'gemini');
        $this->fallbackModel = (string) Config::get('services.ai.fallback_model', 'gemini-2.5-flash');
        $this->activeProvider = $this->primaryProvider;
        $this->activeModel = $this->primaryModel;
    }

    public function getActiveProvider(): string
    {
        return $this->activeProvider ?? $this->primaryProvider;
    }

    public function getActiveModel(): string
    {
        if ($this->activeModel !== null) {
            return $this->activeModel;
        }

        return ($this->activeProvider ?? $this->primaryProvider) === $this->fallbackProvider
            ? $this->fallbackModel
            : $this->primaryModel;
    }

    public function setActiveModel(string $provider, string $model): void
    {
        $this->activeProvider = $provider;
        $this->activeModel = $model;
    }

    /**
     * Set fake responses or exceptions keyed by provider name.
     *
     * @param  array<string, mixed>  $fakes
     */
    public static function fake(array $fakes): void
    {
        self::$fakeProviders = $fakes;
    }

    /**
     * Set an ordered sequence of responses or exceptions.
     *
     * @param  list<mixed>  $sequence
     */
    public static function fakeSequence(array $sequence): void
    {
        self::$fakeSequence = $sequence;
    }

    public static function resetFakes(): void
    {
        self::$fakeProviders = null;
        self::$fakeSequence = null;
    }

    /**
     * Completes a conversation turn with optional tool schemas.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string}
     */
    public function complete(array $messages, array $tools = [], array $options = []): array
    {
        $targetProvider = (string) ($options['provider'] ?? $this->primaryProvider);
        $targetModel = (string) ($options['model'] ?? $this->primaryModel);

        if (self::$fakeSequence !== null && count(self::$fakeSequence) > 0) {
            $step = array_shift(self::$fakeSequence);
            if ($step instanceof Throwable) {
                throw $step;
            }
            if (is_array($step)) {
                if (! isset($step['provider'])) {
                    $step['provider'] = $targetProvider;
                    $step['model'] = $targetModel;
                }

                /** @var array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string} $step */
                return $step;
            }
        }

        try {
            $this->activeProvider = $targetProvider;
            $this->activeModel = $targetModel;

            return $this->invokeProvider($targetProvider, $targetModel, $messages, $tools, $options);
        } catch (Throwable $e) {
            Log::warning("Primary LLM provider [{$targetProvider}] failed: {$e->getMessage()}. Triggering fallback to [{$this->fallbackProvider}].");

            $this->activeProvider = $this->fallbackProvider;
            $this->activeModel = $this->fallbackModel;

            return $this->invokeProvider($this->fallbackProvider, $this->fallbackModel, $messages, $tools, $options);
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string}
     */
    protected function invokeProvider(
        string $provider,
        string $model,
        array $messages,
        array $tools,
        array $options
    ): array {
        if (self::$fakeProviders !== null && array_key_exists($provider, self::$fakeProviders)) {
            $fake = self::$fakeProviders[$provider];
            if ($fake instanceof Throwable) {
                throw $fake;
            }
            if (is_array($fake)) {
                $fake['provider'] = $provider;
                $fake['model'] = $model;

                /** @var array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string} $fake */
                return $fake;
            }
        }

        // Resolve key: prioritize user-scoped key if provided, else fallback to system/env/SOPS key
        /** @var User|null $user */
        $user = $options['user'] ?? (auth()->check() ? auth()->user() : null);
        $userAnthropicKey = $user?->anthropic_api_key;
        $userGeminiKey = $user?->gemini_api_key;
        $userOpenrouterKey = $user?->openrouter_api_key;

        $key = match ($provider) {
            'anthropic' => ! empty($userAnthropicKey) ? (string) $userAnthropicKey : (string) Config::get('services.ai.anthropic_api_key'),
            'gemini' => ! empty($userGeminiKey) ? (string) $userGeminiKey : (string) Config::get('services.ai.gemini_api_key'),
            'openrouter' => ! empty($userOpenrouterKey) ? (string) $userOpenrouterKey : (string) Config::get('services.ai.openrouter_api_key'),
            'openai' => (string) Config::get('services.ai.openai_api_key'),
            default => '',
        };

        if ($provider === 'openrouter' && (! app()->environment('testing') || ! empty($options['live']))) {
            return $this->invokeOpenRouter($model, $messages, $tools, $key, $options);
        }

        $mock = $this->mockCompletion($messages, $tools);
        $mock['provider'] = $provider;
        $mock['model'] = $model;

        return $mock;
    }

    /**
     * Invoke OpenRouter OpenAI-compatible completions endpoint.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string}
     */
    protected function invokeOpenRouter(
        string $model,
        array $messages,
        array $tools,
        string $key,
        array $options
    ): array {
        if (empty($key)) {
            throw new RuntimeException('OpenRouter API key is missing or unconfigured. Please configure your key in Executive Settings.');
        }

        $payload = [
            'model' => $model,
            'messages' => $messages,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
        }

        $response = Http::withHeaders([
            'Authorization' => "Bearer {$key}",
            'HTTP-Referer' => 'https://tadbir.dpik.com.my',
            'X-Title' => 'Tadbir AI Copilot',
            'Content-Type' => 'application/json',
        ])->timeout(30)->post('https://openrouter.ai/api/v1/chat/completions', $payload);

        if (! $response->successful()) {
            $body = $response->json() ?? [];
            $errorDesc = is_array($body['error'] ?? null)
                ? (string) ($body['error']['message'] ?? $response->body())
                : (string) ($body['error'] ?? $response->body());

            throw new RuntimeException("OpenRouter API error (HTTP {$response->status()}): {$errorDesc}");
        }

        /** @var array{choices?: list<array{message?: array{content?: string, tool_calls?: list<array{id?: string, function?: array{name?: string, arguments?: string}}>}}>} $data */
        $data = $response->json() ?? [];
        $choice = $data['choices'][0]['message'] ?? [];
        $content = (string) ($choice['content'] ?? '');
        $toolCalls = [];

        if (! empty($choice['tool_calls'])) {
            foreach ($choice['tool_calls'] as $tc) {
                $rawArgs = (string) ($tc['function']['arguments'] ?? '{}');
                $toolCalls[] = [
                    'id' => (string) ($tc['id'] ?? uniqid('call_')),
                    'name' => (string) ($tc['function']['name'] ?? ''),
                    'arguments' => (array) (json_decode($rawArgs, true) ?? []),
                ];
            }
        }

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'provider' => 'openrouter',
            'model' => $model,
        ];
    }

    /**
     * Performs a live connection and credential verification probe against OpenRouter.
     *
     * @return array{
     *     success: bool,
     *     status: string,
     *     latency_ms: int,
     *     error_code: ?string,
     *     error_message: ?string,
     *     remediation: ?string
     * }
     */
    public function probeOpenRouterKey(?string $apiKey): array
    {
        $key = trim((string) $apiKey);

        if (empty($key)) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'latency_ms' => 0,
                'error_code' => 'MISSING_API_KEY',
                'error_message' => 'Client Error: OpenRouter API key is required.',
                'remediation' => 'Provide a valid OpenRouter API key starting with "sk-or-v1-".',
            ];
        }

        if (! str_starts_with($key, 'sk-or-v1-')) {
            return [
                'success' => false,
                'status' => 'invalid_format',
                'latency_ms' => 0,
                'error_code' => 'INVALID_KEY_FORMAT',
                'error_message' => 'Format Error: OpenRouter API key must begin with "sk-or-v1-".',
                'remediation' => 'Generate and copy your key from OpenRouter at https://openrouter.ai/keys.',
            ];
        }

        $startTime = microtime(true);

        try {
            $response = Http::withHeaders([
                'Authorization' => "Bearer {$key}",
                'HTTP-Referer' => 'https://tadbir.dpik.com.my',
                'X-Title' => 'Tadbir AI Copilot',
            ])->timeout(8)->get('https://openrouter.ai/api/v1/auth/key');

            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'connected',
                    'latency_ms' => $latencyMs,
                    'error_code' => null,
                    'error_message' => null,
                    'remediation' => null,
                ];
            }

            $body = $response->json() ?? [];
            $errorDesc = is_array($body['error'] ?? null)
                ? (string) ($body['error']['message'] ?? $response->body())
                : (string) ($body['error'] ?? $response->body());
            $errorCode = is_array($body['error'] ?? null)
                ? (string) ($body['error']['code'] ?? 'AUTH_ERROR')
                : 'AUTH_ERROR';

            return [
                'success' => false,
                'status' => 'auth_failed',
                'latency_ms' => $latencyMs,
                'error_code' => $errorCode,
                'error_message' => "HTTP {$response->status()}: {$errorDesc}",
                'remediation' => 'Verify your OpenRouter API key and credit balance at https://openrouter.ai/credits.',
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'status' => 'network_error',
                'latency_ms' => $latencyMs,
                'error_code' => 'CONNECTION_FAILED',
                'error_message' => 'Network / Connection error: '.$e->getMessage(),
                'remediation' => 'Ensure the server has outbound internet connectivity to openrouter.ai.',
            ];
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    protected function mockCompletion(array $messages, array $tools): array
    {
        $lastUserMsg = '';
        foreach (array_reverse($messages) as $m) {
            if ($m['role'] === 'user') {
                $lastUserMsg = $m['content'];
                break;
            }
        }

        if (stripos($lastUserMsg, 'delta') !== false || stripos($lastUserMsg, 'inbox') !== false) {
            return [
                'content' => 'I have checked your Outlook mailbox for new unread messages.',
                'tool_calls' => [
                    [
                        'id' => 'call_'.uniqid(),
                        'name' => 'outlook_list_inbox_delta',
                        'arguments' => ['lookback_hours' => 24, 'concise' => true],
                    ],
                ],
            ];
        }

        if (stripos($lastUserMsg, 'draft') !== false) {
            return [
                'content' => 'I am preparing a draft email for your review.',
                'tool_calls' => [
                    [
                        'id' => 'call_'.uniqid(),
                        'name' => 'propose_action_card',
                        'arguments' => [
                            'action_type' => 'outlook_draft',
                            'title' => 'Draft Reply: Mesyuarat Projek FT264',
                            'summary' => 'Menyatakan pengesahan kehadiran ke tapak projek pada tarikh yang dicadangkan.',
                            'payload' => [
                                'subject' => 'RE: Mesyuarat Kemajuan Projek FT264',
                                'body' => 'Tuan, pihak DPIK mengesahkan kehadiran.',
                                'to_recipients' => ['jkr_sarawak@jkr.gov.my'],
                            ],
                        ],
                    ],
                ],
            ];
        }

        return [
            'content' => 'DPIK Tadbir Copilot ready. How can I assist you with your executive inbox or project records today?',
        ];
    }
}
