<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Services\Mcp\OutlookMcpBridge;
use Illuminate\Auth\Access\AuthorizationException;

class OutlookForwardTool extends BaseTool
{
    protected string $name = 'outlook_forward';

    protected string $description = 'Forwards an existing Outlook email message to specified recipients. Requires human confirmation.';

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
            'required' => ['message_id', 'to_recipients', 'approval_token'],
            'properties' => [
                'message_id' => ['type' => 'string'],
                'to_recipients' => ['type' => 'array', 'items' => ['type' => 'string']],
                'comment' => ['type' => 'string'],
                'approval_token' => ['type' => 'string'],
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
            throw new AuthorizationException('Write-safety invariant violation: Missing or invalid approval token for email forward.');
        }

        $messageId = (string) ($arguments['message_id'] ?? '');
        /** @var list<string> $toRecipients */
        $toRecipients = (array) ($arguments['to_recipients'] ?? []);
        $comment = (string) ($arguments['comment'] ?? '');

        $success = $this->bridge->forwardMessage($messageId, $toRecipients, $comment);

        return [
            'status' => $success ? 'forwarded' : 'failed',
            'message_id' => $messageId,
            'success' => $success,
        ];
    }
}
