<?php

namespace App\Services\Ai;

use App\DTOs\AiTurnResponse;
use App\Mcp\ToolRegistry;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Services\Memory\MemoryRetrievalService;
use Illuminate\Support\Facades\Log;

class AgentService
{
    public function __construct(
        protected LlmGatewayService $llmGateway,
        protected ToolRegistry $toolRegistry,
        protected AntiHallucinationGuard $guard,
        protected MemoryRetrievalService $memory
    ) {}

    public function handleUserTurn(ChatSession $session, string $prompt): AiTurnResponse
    {
        // 1. Record User Message
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'user',
            'content' => $prompt,
        ]);

        // 2. Fetch relevant project memory
        $memories = $this->memory->search($prompt, limit: 3);
        $denseContext = $this->memory->formatAsDenseContext($memories);

        // 3. Build Conversation History
        $history = $session->messages()->orderBy('id', 'asc')->get();
        $messages = [];

        if (! empty($denseContext)) {
            $messages[] = [
                'role' => 'system',
                'content' => "RELEVANT ENTERPRISE MEMORY (SQLite FTS5 RRF):\n".$denseContext,
            ];
        }

        foreach ($history as $msg) {
            $messages[] = [
                'role' => $msg->role,
                'content' => (string) $msg->content,
            ];
        }

        // 4. Query LLM with Tool Schemas
        $tools = $this->toolRegistry->getLlmToolDefinitions();
        $completion = $this->llmGateway->complete($messages, $tools);

        // 5. Handle Tool Calls
        $executedActions = [];
        $suspendedCall = null;
        $status = 'completed';

        if (! empty($completion['tool_calls'])) {
            foreach ($completion['tool_calls'] as $toolCall) {
                $name = $toolCall['name'];
                $args = $toolCall['arguments'];

                if ($name === 'ask_user_question' || $name === 'propose_action_card') {
                    $toolResult = $this->toolRegistry->execute($name, $args);
                    $suspendedCall = [
                        'id' => $toolCall['id'],
                        'name' => $name,
                        'arguments' => $args,
                        'suspension_payload' => $toolResult,
                    ];
                    $status = 'suspended';
                    break;
                }

                try {
                    $toolResult = $this->toolRegistry->execute($name, $args);
                    $executedActions[] = [
                        'tool' => $name,
                        'result' => $toolResult,
                    ];
                } catch (\Throwable $e) {
                    Log::error("Tool execution failed: {$name}", ['error' => $e->getMessage()]);
                }
            }
        }

        $turnResponse = new AiTurnResponse(
            content: $completion['content'],
            status: $status,
            suspendedToolCall: $suspendedCall,
            executedActions: $executedActions
        );

        // 6. Anti-Hallucination Guard Validation
        if (! $this->guard->validateTurnResponse($turnResponse, $session)) {
            $turnResponse = new AiTurnResponse(
                content: 'Notice: Action could not be verified by the write-safety ledger. Please confirm approval via an Action Card before execution.',
                status: 'completed',
                suspendedToolCall: null,
                executedActions: []
            );
        }

        // 7. Record Assistant Message
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'assistant',
            'content' => $turnResponse->content,
            'tool_calls' => $completion['tool_calls'] ?? null,
            'metadata' => [
                'status' => $turnResponse->status,
                'executed_actions_count' => count($turnResponse->executedActions),
            ],
        ]);

        return $turnResponse;
    }

    /**
     * Resumes an interactive turn after user modal input or Action Card confirmation.
     *
     * @param  array<string, mixed>  $result
     */
    public function resumeWithToolResult(ChatSession $session, string $toolUseId, array $result): AiTurnResponse
    {
        ChatMessage::create([
            'chat_session_id' => $session->id,
            'role' => 'tool',
            'content' => json_encode($result),
            'metadata' => ['tool_use_id' => $toolUseId],
        ]);

        $prompt = 'User submitted interactive response: '.json_encode($result);

        return $this->handleUserTurn($session, $prompt);
    }
}
