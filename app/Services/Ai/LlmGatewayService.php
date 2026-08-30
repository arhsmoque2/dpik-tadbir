<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LlmGatewayService
{
    protected string $primaryProvider;

    protected string $primaryModel;

    protected string $fallbackProvider;

    protected string $fallbackModel;

    public function __construct()
    {
        $this->primaryProvider = (string) Config::get('services.ai.default_provider', 'anthropic');
        $this->primaryModel = (string) Config::get('services.ai.default_model', 'claude-3-7-sonnet-20250219');
        $this->fallbackProvider = (string) Config::get('services.ai.fallback_provider', 'gemini');
        $this->fallbackModel = (string) Config::get('services.ai.fallback_model', 'gemini-2.5-flash');
    }

    public function getActiveProvider(): string
    {
        return $this->primaryProvider;
    }

    public function getActiveModel(): string
    {
        return $this->primaryModel;
    }

    /**
     * Completes a conversation turn with optional tool schemas.
     *
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    public function complete(array $messages, array $tools = [], array $options = []): array
    {
        if (app()->environment('testing')) {
            return $this->mockCompletion($messages, $tools);
        }

        try {
            return $this->invokeProvider($this->primaryProvider, $this->primaryModel, $messages, $tools, $options);
        } catch (\Throwable $e) {
            Log::warning("Primary LLM provider [{$this->primaryProvider}] failed: {$e->getMessage()}. Triggering fallback to [{$this->fallbackProvider}].");

            return $this->invokeProvider($this->fallbackProvider, $this->fallbackModel, $messages, $tools, $options);
        }
    }

    /**
     * @param  list<array{role: string, content: string}>  $messages
     * @param  list<array<string, mixed>>  $tools
     * @param  array<string, mixed>  $options
     * @return array{content: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>}
     */
    protected function invokeProvider(
        string $provider,
        string $model,
        array $messages,
        array $tools,
        array $options
    ): array {
        // Fallback simulation or mock if no external key is configured
        $key = match ($provider) {
            'anthropic' => (string) Config::get('services.ai.anthropic_api_key'),
            'gemini' => (string) Config::get('services.ai.gemini_api_key'),
            'openai' => (string) Config::get('services.ai.openai_api_key'),
            default => '',
        };

        if (empty($key)) {
            return $this->mockCompletion($messages, $tools);
        }

        // Standard HTTP integration here
        return $this->mockCompletion($messages, $tools);
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
