<?php

namespace App\Services\Ai;

use App\Models\AiRun;
use App\Models\ChatSession;
use Illuminate\Support\Facades\Log;

class AiRunRecorder
{
    public function __construct(
        protected CostCalculator $costCalculator,
        protected PiiDetector $piiDetector
    ) {}

    /**
     * Records a completed or suspended AI turn into the telemetry database.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function record(
        ChatSession $session,
        string $provider,
        string $model,
        string $prompt,
        string $responseContent,
        int $latencyMs,
        string $status = 'completed',
        array $metadata = []
    ): AiRun {
        // Approximate token count (4 chars ~= 1 token) if not provided by mock/gateway
        $promptTokens = (int) ($metadata['prompt_tokens'] ?? max(1, (int) ceil(strlen($prompt) / 4)));
        $completionTokens = (int) ($metadata['completion_tokens'] ?? max(1, (int) ceil(strlen($responseContent) / 4)));
        $totalTokens = $promptTokens + $completionTokens;

        $cost = $this->costCalculator->calculate($model, $promptTokens, $completionTokens);
        $hasPii = $this->piiDetector->hasPii($prompt) || $this->piiDetector->hasPii($responseContent);

        $run = AiRun::create([
            'user_id' => $session->user_id,
            'chat_session_id' => $session->id,
            'provider' => $provider,
            'model' => $model,
            'prompt_tokens' => $promptTokens,
            'completion_tokens' => $completionTokens,
            'total_tokens' => $totalTokens,
            'latency_ms' => $latencyMs,
            'cost_usd' => $cost['usd'],
            'cost_myr' => $cost['myr'],
            'has_pii' => $hasPii,
            'status' => $status,
            'metadata' => array_merge($metadata, [
                'pii_detected' => $hasPii ? $this->piiDetector->detect($prompt) : [],
            ]),
        ]);

        Log::info("AI Run recorded [ID: {$run->id}]", [
            'provider' => $provider,
            'model' => $model,
            'tokens' => $totalTokens,
            'cost_usd' => $cost['usd'],
            'latency_ms' => $latencyMs,
        ]);

        return $run;
    }
}
