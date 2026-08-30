<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Services\Mcp\OutlookMcpBridge;
use Illuminate\Auth\Access\AuthorizationException;

class OutlookReplyTool extends BaseTool
{
    protected string $name = 'outlook_reply';

    protected string $description = 'Dispatches a contextual reply to an existing Outlook thread. Requires human confirmation.';

    public function __construct(
        protected OutlookMcpBridge $bridge
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['message_id', 'body', 'approval_token'],
            'properties' => [
                'message_id' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'approval_token' => ['type' => 'string'],
                'attachments' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws AuthorizationException
     */
    public function handle(array $arguments): array
    {
        $token = (string) ($arguments['approval_token'] ?? '');
        if (empty($token) || ! str_starts_with($token, 'act_tok_')) {
            throw new AuthorizationException('Write-safety invariant violation: Missing or invalid approval token for email reply.');
        }

        $messageId = (string) ($arguments['message_id'] ?? '');
        $body = (string) ($arguments['body'] ?? '');
        /** @var list<string> $attachments */
        $attachments = (array) ($arguments['attachments'] ?? []);

        $success = $this->bridge->sendReply($messageId, $body, $attachments);

        return [
            'status' => $success ? 'sent' : 'failed',
            'message_id' => $messageId,
            'success' => $success,
        ];
    }
}
