<?php

namespace App\Mcp\Tools\Interactive;

use App\Mcp\BaseTool;
use Illuminate\Support\Str;

class ProposeActionCardTool extends BaseTool
{
    protected string $name = 'propose_action_card';

    protected string $description = 'Stages an actionable proposal (email draft, reply, forward) requiring human confirmation with a cryptographic one-time token.';

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['action_type', 'title', 'summary', 'payload'],
            'properties' => [
                'action_type' => ['type' => 'string', 'enum' => ['outlook_draft', 'outlook_reply', 'outlook_forward', 'memory_commit']],
                'title' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'payload' => ['type' => 'object'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $token = 'act_tok_'.Str::random(32);

        return [
            'status' => 'suspended',
            'state' => 'AWAITING_ACTION_APPROVAL',
            'approval_token' => $token,
            'card' => [
                'action_type' => $arguments['action_type'] ?? 'unknown',
                'title' => $arguments['title'] ?? '',
                'summary' => $arguments['summary'] ?? '',
                'payload' => $arguments['payload'] ?? [],
                'approval_token' => $token,
            ],
        ];
    }
}
