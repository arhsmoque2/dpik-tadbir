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
     * $messages carries the gateway's neutral shape
     * ({role, content, tool_calls?, tool_call_id?, is_error?}) — AgentService's
     * tool loop needs the wider shape (not just {role, content}) once a turn
     * has more than one round-trip, so this and every method it delegates to
     * accept the general array<string, mixed> per message rather than the
     * old plain-text-only shape.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason: string, provider: string, model: string, input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int}
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

                /** @var array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string, input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int} $step */
                return $this->normalizeStopReason($step);
            }
        }

        try {
            $this->activeProvider = $targetProvider;
            $this->activeModel = $targetModel;

            return $this->normalizeStopReason($this->invokeProvider($targetProvider, $targetModel, $messages, $tools, $options));
        } catch (Throwable $e) {
            Log::warning("Primary LLM provider [{$targetProvider}] failed: {$e->getMessage()}. Triggering fallback to [{$this->fallbackProvider}].");

            $this->activeProvider = $this->fallbackProvider;
            $this->activeModel = $this->fallbackModel;

            return $this->normalizeStopReason($this->invokeProvider($this->fallbackProvider, $this->fallbackModel, $messages, $tools, $options));
        }
    }

    /**
     * Ensures every completion — whatever path produced it (a real
     * provider call, mockCompletion(), or a developer-supplied ::fake()/
     * ::fakeSequence() fixture that predates stop_reason existing on this
     * contract) — carries a stop_reason AgentService's tool loop can rely
     * on without every call site needing to know which path was taken.
     *
     * The input is loosely typed (each of complete()'s branches — a
     * developer fake, mockCompletion(), a live provider call — has its own
     * shape) but every path is known to already carry content/provider/model
     * by the time it reaches here, so the post-mutation @var below asserts
     * the shape complete() actually promises rather than trying to thread a
     * PHPStan generic template through a key-adding array mutation (which
     * loses the template correlation regardless).
     *
     * @param  array<string, mixed>  $result
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason: string, provider: string, model: string, input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int}
     */
    private function normalizeStopReason(array $result): array
    {
        $result['stop_reason'] ??= empty($result['tool_calls']) ? 'end_turn' : 'tool_use';

        /** @var array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason: string, provider: string, model: string, input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int} $result */
        return $result;
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason?: string, provider: string, model: string}
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

        $liveGate = ! app()->environment('testing') || ! empty($options['live']);

        if ($provider === 'openrouter' && $liveGate) {
            return $this->invokeOpenRouter($model, $messages, $tools, $key, $options);
        }

        if ($provider === 'anthropic' && $liveGate) {
            return $this->invokeAnthropic($model, $messages, $tools, $key, $options);
        }

        if ($provider === 'gemini' && $liveGate) {
            return $this->invokeGemini($model, $messages, $tools, $key, $options);
        }

        if ($liveGate) {
            throw new RuntimeException("No live integration configured for provider '{$provider}' — configure an API key in Executive Settings.");
        }

        $mock = $this->mockCompletion($messages, $tools);
        $mock['provider'] = $provider;
        $mock['model'] = $model;

        return $mock;
    }

    /**
     * Invoke OpenRouter OpenAI-compatible completions endpoint.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason?: string, provider: string, model: string}
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
            'messages' => $this->toOpenAiMessages($messages, (string) ($options['system'] ?? '')),
        ];

        if ($tools !== []) {
            $payload['tools'] = $this->toOpenAiTools($tools);
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

        /** @var array{choices?: list<array{message?: array{content?: string, tool_calls?: list<array{id?: string, function?: array{name?: string, arguments?: string}}>}, finish_reason?: string}>} $data */
        $data = $response->json() ?? [];
        $choice = $data['choices'][0]['message'] ?? [];
        $content = (string) ($choice['content'] ?? '');
        $toolCalls = [];

        foreach ($choice['tool_calls'] ?? [] as $tc) {
            $rawArgs = (string) ($tc['function']['arguments'] ?? '{}');
            $toolCalls[] = [
                'id' => (string) ($tc['id'] ?? uniqid('call_')),
                'name' => (string) ($tc['function']['name'] ?? ''),
                'arguments' => (array) (json_decode($rawArgs, true) ?? []),
            ];
        }

        $finishReason = (string) ($data['choices'][0]['finish_reason'] ?? '');

        return [
            'content' => $content,
            'tool_calls' => $toolCalls,
            'stop_reason' => ($finishReason === 'tool_calls' || $toolCalls !== []) ? 'tool_use' : 'end_turn',
            'provider' => 'openrouter',
            'model' => $model,
        ];
    }

    /**
     * Invoke Anthropic's native Messages API. Primary provider per ADR-002 —
     * previously the 'anthropic' branch of invokeProvider() fell straight
     * through to mockCompletion() in every environment (see
     * docs/handoffs history: an executive could configure a real
     * anthropic_api_key and the assistant would still never call it).
     *
     * The Messages API has real shape differences from the OpenAI-compatible
     * format invokeOpenRouter() speaks — system prompt is a top-level field,
     * not a role:system message; tool schemas use input_schema, not
     * parameters; and tool_use/tool_result are typed content blocks, not a
     * separate 'tool' message role — hence the dedicated toAnthropicMessages()
     * translation rather than reusing invokeOpenRouter()'s payload shape.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason: string, provider: string, model: string, input_tokens: int, output_tokens: int}
     */
    protected function invokeAnthropic(
        string $model,
        array $messages,
        array $tools,
        string $key,
        array $options
    ): array {
        if (empty($key)) {
            throw new RuntimeException('Anthropic API key is missing or unconfigured. Please configure your key in Executive Settings.');
        }

        $payload = [
            'model' => $model,
            'max_tokens' => (int) ($options['max_tokens'] ?? 4096),
            'messages' => $this->toAnthropicMessages($messages),
        ];

        // Prompt caching (ADR-021): the system prompt and tool catalog are
        // near-identical across every iteration of AgentService's tool loop
        // (up to MAX_ITERATIONS calls per user turn) and across turns within
        // the same session, yet were previously sent as plain strings/arrays
        // with no cache breakpoint — billing full input-token price on every
        // repeat. A cache_control breakpoint on the system block and on the
        // last tool definition tells Anthropic to cache everything up to and
        // including that block; a cache hit bills at ~10% of input price.
        // Below Anthropic's per-model minimum cacheable length the field is
        // silently ignored (no error, no behavior change), so this is safe
        // to always set rather than sizing the prompt first.
        $systemPrompt = (string) ($options['system'] ?? '');
        if ($systemPrompt !== '') {
            $payload['system'] = [
                ['type' => 'text', 'text' => $systemPrompt, 'cache_control' => ['type' => 'ephemeral']],
            ];
        }

        if ($tools !== []) {
            $anthropicTools = $this->toAnthropicTools($tools);
            $lastIndex = array_key_last($anthropicTools);
            $anthropicTools[$lastIndex]['cache_control'] = ['type' => 'ephemeral'];
            $payload['tools'] = $anthropicTools;
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', $payload);

        if (! $response->successful()) {
            $body = $response->json() ?? [];
            $errorDesc = is_array($body['error'] ?? null)
                ? (string) ($body['error']['message'] ?? $response->body())
                : (string) ($body['error'] ?? $response->body());

            throw new RuntimeException("Anthropic API error (HTTP {$response->status()}): {$errorDesc}");
        }

        /** @var array{content?: list<array<string, mixed>>, stop_reason?: string, usage?: array{input_tokens?: int, output_tokens?: int, cache_creation_input_tokens?: int, cache_read_input_tokens?: int}} $data */
        $data = $response->json() ?? [];
        $blocks = $data['content'] ?? [];

        $textParts = [];
        $toolCalls = [];
        foreach ($blocks as $block) {
            if (($block['type'] ?? '') === 'text') {
                $textParts[] = (string) ($block['text'] ?? '');
            } elseif (($block['type'] ?? '') === 'tool_use') {
                $toolCalls[] = [
                    'id' => (string) ($block['id'] ?? uniqid('call_')),
                    'name' => (string) ($block['name'] ?? ''),
                    'arguments' => (array) ($block['input'] ?? []),
                ];
            }
        }

        return [
            'content' => implode("\n", $textParts),
            'tool_calls' => $toolCalls,
            'stop_reason' => ((string) ($data['stop_reason'] ?? '') === 'tool_use' || $toolCalls !== []) ? 'tool_use' : 'end_turn',
            'provider' => 'anthropic',
            'model' => $model,
            'input_tokens' => (int) ($data['usage']['input_tokens'] ?? 0),
            'output_tokens' => (int) ($data['usage']['output_tokens'] ?? 0),
            // Recorded but not yet priced separately by CostCalculator (a
            // cache write briefly costs more than a normal input token, a
            // cache read much less) — kept on the result now so AiRun
            // telemetry can be extended to price them precisely without a
            // second round of LlmGatewayService changes. See ADR-021.
            'cache_creation_input_tokens' => (int) ($data['usage']['cache_creation_input_tokens'] ?? 0),
            'cache_read_input_tokens' => (int) ($data['usage']['cache_read_input_tokens'] ?? 0),
        ];
    }

    /**
     * Translate the gateway's neutral message shape
     * ({role, content, tool_calls?, tool_call_id?, is_error?}) into
     * Anthropic's typed content-block format. Anthropic requires tool
     * results as user-role tool_result blocks (not a separate 'tool' role)
     * — consecutive 'tool' turns from one agent-loop iteration are merged
     * into a single user turn carrying multiple tool_result blocks, which
     * is what the API expects when more than one tool was called in the
     * same assistant turn.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array{role: string, content: string|list<array<string, mixed>>}>
     */
    private function toAnthropicMessages(array $messages): array
    {
        $out = [];

        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');

            if ($role === 'assistant') {
                $blocks = [];
                $text = (string) ($m['content'] ?? '');
                if ($text !== '') {
                    $blocks[] = ['type' => 'text', 'text' => $text];
                }
                foreach ($m['tool_calls'] ?? [] as $tc) {
                    $blocks[] = [
                        'type' => 'tool_use',
                        'id' => $tc['id'],
                        'name' => $tc['name'],
                        'input' => $tc['arguments'],
                    ];
                }
                $out[] = ['role' => 'assistant', 'content' => $blocks !== [] ? $blocks : $text];

                continue;
            }

            if ($role === 'tool') {
                $block = [
                    'type' => 'tool_result',
                    'tool_use_id' => (string) ($m['tool_call_id'] ?? ''),
                    'content' => (string) ($m['content'] ?? ''),
                ];
                if (! empty($m['is_error'])) {
                    $block['is_error'] = true;
                }

                // PHPStan proves the array-typed 'content' here is always
                // non-empty (built only as $blocks !== [] ? $blocks : $text,
                // or as [$block]) — an explicit emptiness check was flagged
                // as dead code, so [0] is trusted directly per that proof.
                $lastIndex = array_key_last($out);
                $lastIsToolResultTurn = $lastIndex !== null
                    && $out[$lastIndex]['role'] === 'user'
                    && is_array($out[$lastIndex]['content'])
                    && $out[$lastIndex]['content'][0]['type'] === 'tool_result';

                if ($lastIsToolResultTurn) {
                    $out[$lastIndex]['content'][] = $block;
                } else {
                    $out[] = ['role' => 'user', 'content' => [$block]];
                }

                continue;
            }

            $out[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array{name: string, description: string, input_schema: array<string, mixed>}>
     */
    private function toAnthropicTools(array $tools): array
    {
        return array_map(fn (array $t): array => [
            'name' => (string) ($t['name'] ?? ''),
            'description' => (string) ($t['description'] ?? ''),
            'input_schema' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
        ], $tools);
    }

    /**
     * Translate the gateway's neutral message shape into OpenAI-compatible
     * chat-completions messages — assistant tool_calls become the
     * type:function shape, and each 'tool' turn becomes its own
     * role:tool message keyed by tool_call_id (unlike Anthropic, OpenAI
     * does not merge multiple tool results into one turn).
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array<string, mixed>>
     */
    private function toOpenAiMessages(array $messages, string $systemPrompt): array
    {
        $out = [];
        if ($systemPrompt !== '') {
            $out[] = ['role' => 'system', 'content' => $systemPrompt];
        }

        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');

            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $content = (string) ($m['content'] ?? '');
                $out[] = [
                    'role' => 'assistant',
                    'content' => $content !== '' ? $content : null,
                    'tool_calls' => array_map(fn (array $tc): array => [
                        'id' => $tc['id'],
                        'type' => 'function',
                        'function' => [
                            'name' => $tc['name'],
                            'arguments' => json_encode($tc['arguments'], JSON_THROW_ON_ERROR),
                        ],
                    ], $m['tool_calls']),
                ];

                continue;
            }

            if ($role === 'tool') {
                $out[] = [
                    'role' => 'tool',
                    'tool_call_id' => (string) ($m['tool_call_id'] ?? ''),
                    'content' => (string) ($m['content'] ?? ''),
                ];

                continue;
            }

            $out[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $tools
     * @return list<array{type: string, function: array{name: string, description: string, parameters: array<string, mixed>}}>
     */
    private function toOpenAiTools(array $tools): array
    {
        return array_map(fn (array $t): array => [
            'type' => 'function',
            'function' => [
                'name' => (string) ($t['name'] ?? ''),
                'description' => (string) ($t['description'] ?? ''),
                'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
            ],
        ], $tools);
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
     * Invoke Google Gemini generateContent REST API.
     *
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason: string, provider: string, model: string, input_tokens?: int, output_tokens?: int}
     */
    protected function invokeGemini(
        string $model,
        array $messages,
        array $tools,
        string $key,
        array $options
    ): array {
        if (empty($key)) {
            throw new RuntimeException('Google Gemini API key is missing or unconfigured. Please configure your key in Executive Settings.');
        }

        $systemPrompt = (string) ($options['system'] ?? '');
        $payload = [
            'contents' => $this->toGeminiContents($messages),
            'generationConfig' => [
                'maxOutputTokens' => (int) ($options['max_tokens'] ?? 4096),
                'temperature' => 0.2,
            ],
        ];

        if ($systemPrompt !== '') {
            $payload['systemInstruction'] = [
                'parts' => [
                    ['text' => $systemPrompt],
                ],
            ];
        }

        if ($tools !== []) {
            $payload['tools'] = [
                [
                    'functionDeclarations' => array_map(fn (array $t): array => [
                        'name' => (string) ($t['name'] ?? ''),
                        'description' => (string) ($t['description'] ?? ''),
                        'parameters' => $t['parameters'] ?? ['type' => 'object', 'properties' => (object) []],
                    ], $tools),
                ],
            ];
        }

        $cleanModel = str_starts_with($model, 'models/') ? substr($model, 7) : $model;
        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$cleanModel}:generateContent?key={$key}";

        $response = Http::withHeaders([
            'Content-Type' => 'application/json',
        ])->timeout(45)->post($url, $payload);

        if (! $response->successful()) {
            $body = $response->json() ?? [];
            $errorDesc = is_array($body['error'] ?? null)
                ? (string) ($body['error']['message'] ?? $response->body())
                : (string) ($body['error'] ?? $response->body());

            throw new RuntimeException("Google Gemini API error (HTTP {$response->status()}): {$errorDesc}");
        }

        /** @var array{candidates?: list<array{content?: array{parts?: list<array<string, mixed>>}, finishReason?: string}>, usageMetadata?: array{promptTokenCount?: int, candidatesTokenCount?: int}} $data */
        $data = $response->json() ?? [];
        $candidate = $data['candidates'][0] ?? [];
        $parts = $candidate['content']['parts'] ?? [];

        $textParts = [];
        $toolCalls = [];

        foreach ($parts as $part) {
            if (isset($part['text'])) {
                $textParts[] = (string) $part['text'];
            }
            if (isset($part['functionCall'])) {
                $fc = (array) $part['functionCall'];
                $toolCalls[] = [
                    'id' => uniqid('call_gemini_'),
                    'name' => (string) ($fc['name'] ?? ''),
                    'arguments' => (array) ($fc['args'] ?? []),
                ];
            }
        }

        $finishReason = (string) ($candidate['finishReason'] ?? '');
        $stopReason = ($finishReason === 'TOOL_CALL' || $toolCalls !== []) ? 'tool_use' : 'end_turn';

        return [
            'content' => implode("\n", $textParts),
            'tool_calls' => $toolCalls,
            'stop_reason' => $stopReason,
            'provider' => 'gemini',
            'model' => $model,
            'input_tokens' => (int) ($data['usageMetadata']['promptTokenCount'] ?? 0),
            'output_tokens' => (int) ($data['usageMetadata']['candidatesTokenCount'] ?? 0),
        ];
    }

    /**
     * Translate the gateway's neutral message shape into Google Gemini's
     * contents format. Gemini requires tool results as `functionResponse` parts
     * inside a user-role turn with correlated tool names and response objects.
     * Consecutive tool results from the same turn are merged into a single user
     * turn matching Gemini multi-tool semantics.
     *
     * @param  list<array<string, mixed>>  $messages
     * @return list<array{role: string, parts: list<array<string, mixed>>}>
     */
    private function toGeminiContents(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = (string) ($m['role'] ?? 'user');

            if ($role === 'assistant') {
                $parts = [];
                $text = (string) ($m['content'] ?? '');
                if ($text !== '') {
                    $parts[] = ['text' => $text];
                }

                if (! empty($m['tool_calls'])) {
                    foreach ($m['tool_calls'] as $tc) {
                        $parts[] = [
                            'functionCall' => [
                                'name' => (string) ($tc['name'] ?? ''),
                                'args' => $tc['arguments'] ?? (object) [],
                            ],
                        ];
                    }
                }

                if ($parts !== []) {
                    $out[] = [
                        'role' => 'model',
                        'parts' => $parts,
                    ];
                }

                continue;
            }

            if ($role === 'tool') {
                $toolName = (string) ($m['tool_name'] ?? $m['name'] ?? '');
                if ($toolName === '' && ! empty($m['tool_call_id'])) {
                    foreach (array_reverse($messages) as $prevMsg) {
                        if (! empty($prevMsg['tool_calls'])) {
                            foreach ($prevMsg['tool_calls'] as $tc) {
                                if (($tc['id'] ?? '') === $m['tool_call_id']) {
                                    $toolName = (string) ($tc['name'] ?? '');
                                    break 2;
                                }
                            }
                        }
                    }
                }
                if ($toolName === '') {
                    $toolName = 'unknown_tool';
                }

                $rawContent = (string) ($m['content'] ?? '');
                $decoded = json_decode($rawContent, true);
                $responseObject = is_array($decoded) ? $decoded : ['output' => $rawContent];

                $part = [
                    'functionResponse' => [
                        'name' => $toolName,
                        'response' => [
                            'name' => $toolName,
                            'content' => $responseObject,
                        ],
                    ],
                ];

                $lastIndex = array_key_last($out);
                $lastIsToolResultTurn = $lastIndex !== null
                    && $out[$lastIndex]['role'] === 'user'
                    && isset($out[$lastIndex]['parts'][0]['functionResponse']);

                if ($lastIsToolResultTurn) {
                    $out[$lastIndex]['parts'][] = $part;
                } else {
                    $out[] = [
                        'role' => 'user',
                        'parts' => [$part],
                    ];
                }

                continue;
            }

            $parts = [];
            $text = (string) ($m['content'] ?? '');
            if ($text !== '') {
                $parts[] = ['text' => $text];
            }

            if ($parts !== []) {
                $out[] = [
                    'role' => 'user',
                    'parts' => $parts,
                ];
            }
        }

        return $out;
    }

    /**
     * Performs a live connection and credential verification probe against Google Gemini.
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
    public function probeGeminiKey(?string $apiKey): array
    {
        $key = trim((string) $apiKey);

        if (empty($key)) {
            return [
                'success' => false,
                'status' => 'unconfigured',
                'latency_ms' => 0,
                'error_code' => 'MISSING_API_KEY',
                'error_message' => 'Client Error: Google Gemini API key is required.',
                'remediation' => 'Provide a valid Google Gemini API key starting with "AIzaSy".',
            ];
        }

        if (! str_starts_with($key, 'AIzaSy')) {
            return [
                'success' => false,
                'status' => 'invalid_format',
                'latency_ms' => 0,
                'error_code' => 'INVALID_KEY_FORMAT',
                'error_message' => 'Format Error: Google Gemini API key must begin with "AIzaSy".',
                'remediation' => 'Generate and copy your key from Google AI Studio at https://aistudio.google.com.',
            ];
        }

        $startTime = microtime(true);

        try {
            $response = Http::timeout(8)->get("https://generativelanguage.googleapis.com/v1beta/models?key={$key}");
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

            return [
                'success' => false,
                'status' => 'auth_failed',
                'latency_ms' => $latencyMs,
                'error_code' => 'AUTH_ERROR',
                'error_message' => "HTTP {$response->status()}: {$errorDesc}",
                'remediation' => 'Verify your Google Gemini API key at https://aistudio.google.com.',
            ];
        } catch (Throwable $e) {
            $latencyMs = (int) round((microtime(true) - $startTime) * 1000);

            return [
                'success' => false,
                'status' => 'network_error',
                'latency_ms' => $latencyMs,
                'error_code' => 'CONNECTION_FAILED',
                'error_message' => 'Network / Connection error: '.$e->getMessage(),
                'remediation' => 'Ensure the server has outbound internet connectivity to generativelanguage.googleapis.com.',
            ];
        }
    }

    /**
     * @param  list<array<string, mixed>>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, stop_reason: string}
     */
    protected function mockCompletion(array $messages, array $tools): array
    {
        $lastMsg = end($messages);
        if (is_array($lastMsg) && ($lastMsg['role'] ?? '') === 'tool') {
            return [
                'content' => 'Interactive action processed successfully. Dispatched action receipt recorded.',
                'stop_reason' => 'end_turn',
            ];
        }

        $lastUserMsg = '';
        foreach (array_reverse($messages) as $m) {
            if (($m['role'] ?? '') === 'user') {
                $lastUserMsg = (string) ($m['content'] ?? '');
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
                'stop_reason' => 'tool_use',
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
                'stop_reason' => 'tool_use',
            ];
        }

        return [
            'content' => 'DPIK Tadbir Copilot ready. How can I assist you with your executive inbox or project records today?',
            'stop_reason' => 'end_turn',
        ];
    }
}
