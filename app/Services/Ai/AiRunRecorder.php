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
     * Records a completed, suspended, or failed AI turn into the telemetry database with PII redaction.
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
        array $metadata = [],
        ?string $errorMessage = null
    ): AiRun {
        // Redact any PII before persistence to database
        $sanitizedPrompt = $this->piiDetector->redact($prompt);
        $sanitizedResponse = $this->piiDetector->redact($responseContent);
        $sanitizedMetadata = $this->piiDetector->redactArray($metadata);

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
            'payload' => $sanitizedPrompt,
            'response' => $sanitizedResponse,
            'error_message' => $errorMessage !== null ? $this->piiDetector->redact($errorMessage) : null,
            'metadata' => array_merge($sanitizedMetadata, [
                'pii_types' => $hasPii ? array_keys($this->piiDetector->detect($prompt)) : [],
                'pii_counts' => $hasPii ? $this->piiDetector->detectCounts($prompt) : [],
            ]),
        ]);

        Log::info("AI Run recorded [ID: {$run->id}]", [
            'provider' => $provider,
            'model' => $model,
            'status' => $status,
            'tokens' => $totalTokens,
            'cost_usd' => $cost['usd'],
            'latency_ms' => $latencyMs,
        ]);

        return $run;
    }

    /**
     * Records an unrecoverable AI provider failure into the telemetry database.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function recordFailure(
        ChatSession $session,
        string $provider,
        string $model,
        string $prompt,
        \Throwable $exception,
        int $latencyMs,
        array $metadata = []
    ): AiRun {
        return $this->record(
            session: $session,
            provider: $provider,
            model: $model,
            prompt: $prompt,
            responseContent: '',
            latencyMs: $latencyMs,
            status: 'failed',
            metadata: $metadata,
            errorMessage: $exception->getMessage()
        );
    }
}
