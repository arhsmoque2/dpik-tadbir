<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

class LlmGatewayService
{
    protected string $primaryProvider;

    protected string $primaryModel;

    protected string $fallbackProvider;

    protected string $fallbackModel;

    protected ?string $activeProvider = null;

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
    }

    public function getActiveProvider(): string
    {
        return $this->activeProvider ?? $this->primaryProvider;
    }

    public function getActiveModel(): string
    {
        return ($this->activeProvider ?? $this->primaryProvider) === $this->fallbackProvider
            ? $this->fallbackModel
            : $this->primaryModel;
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
        if (self::$fakeSequence !== null && count(self::$fakeSequence) > 0) {
            $step = array_shift(self::$fakeSequence);
            if ($step instanceof \Throwable) {
                throw $step;
            }
            if (is_array($step)) {
                if (! isset($step['provider'])) {
                    $step['provider'] = $this->getActiveProvider();
                    $step['model'] = $this->getActiveModel();
                }

                /** @var array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>, provider: string, model: string} $step */
                return $step;
            }
        }

        try {
            $this->activeProvider = $this->primaryProvider;

            return $this->invokeProvider($this->primaryProvider, $this->primaryModel, $messages, $tools, $options);
        } catch (\Throwable $e) {
            Log::warning("Primary LLM provider [{$this->primaryProvider}] failed: {$e->getMessage()}. Triggering fallback to [{$this->fallbackProvider}].");

            $this->activeProvider = $this->fallbackProvider;

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
            if ($fake instanceof \Throwable) {
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

        $key = match ($provider) {
            'anthropic' => ! empty($userAnthropicKey) ? (string) $userAnthropicKey : (string) Config::get('services.ai.anthropic_api_key'),
            'gemini' => ! empty($userGeminiKey) ? (string) $userGeminiKey : (string) Config::get('services.ai.gemini_api_key'),
            'openai' => (string) Config::get('services.ai.openai_api_key'),
            default => '',
        };

        $mock = $this->mockCompletion($messages, $tools);
        $mock['provider'] = $provider;
        $mock['model'] = $model;

        return $mock;
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
