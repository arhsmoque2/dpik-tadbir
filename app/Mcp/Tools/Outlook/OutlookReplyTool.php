<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Mcp\Tools\Concerns\ScopesMailBridge;
use App\Services\Ai\ActionApprovalService;
use App\Services\Audit\ActionMemoryService;
use App\Services\Mail\MailBridge;
use Illuminate\Auth\Access\AuthorizationException;

class OutlookReplyTool extends BaseTool
{
    use ScopesMailBridge;

    protected string $name = 'outlook_reply';

    protected string $description = 'Dispatches a contextual reply to an existing Outlook thread. Requires human confirmation.';

    public function __construct(
        protected MailBridge $bridge,
        protected ActionApprovalService $approvals
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
        $user = auth()->user();
        $token = (string) ($arguments['approval_token'] ?? '');
        if (! $user || ! $this->approvals->consume($token, 'outlook_reply', $user)) {
            throw new AuthorizationException('Write-safety invariant violation: Missing, invalid, expired, or already-used approval token for email reply.');
        }

        $messageId = (string) ($arguments['message_id'] ?? '');
        $body = (string) ($arguments['body'] ?? '');
        /** @var list<string> $attachments */
        $attachments = (array) ($arguments['attachments'] ?? []);

        $success = $this->scopedBridge($this->bridge)->sendReply($messageId, $body, $attachments);

        if ($success) {
            app(ActionMemoryService::class)->logReceipt(
                user: $user,
                actionType: 'outlook_reply',
                description: "Sent email reply to message [{$messageId}]",
                targetRecipients: null,
                payload: ['message_id' => $messageId, 'body' => $body, 'attachments' => $attachments],
                status: 'executed',
                approvalToken: $token
            );
        }

        return [
            'status' => $success ? 'sent' : 'failed',
            'message_id' => $messageId,
            'success' => $success,
        ];
    }
}
