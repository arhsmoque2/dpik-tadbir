<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Mcp\Tools\Concerns\ScopesMailBridge;
use App\Services\Ai\ActionApprovalService;
use App\Services\Audit\ActionMemoryService;
use App\Services\Mail\MailBridge;
use Illuminate\Auth\Access\AuthorizationException;

class OutlookForwardTool extends BaseTool
{
    use ScopesMailBridge;

    protected string $name = 'outlook_forward';

    protected string $description = 'Forwards an existing Outlook email message to specified recipients. Requires human confirmation.';

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
        $user = auth()->user();
        $token = (string) ($arguments['approval_token'] ?? '');
        if (! $user || ! $this->approvals->consume($token, 'outlook_forward', $user)) {
            throw new AuthorizationException('Write-safety invariant violation: Missing, invalid, expired, or already-used approval token for email forward.');
        }

        $messageId = (string) ($arguments['message_id'] ?? '');
        /** @var list<string> $toRecipients */
        $toRecipients = (array) ($arguments['to_recipients'] ?? []);
        $comment = (string) ($arguments['comment'] ?? '');

        $success = $this->scopedBridge($this->bridge)->forwardMessage($messageId, $toRecipients, $comment);

        if ($success) {
            app(ActionMemoryService::class)->logReceipt(
                user: $user,
                actionType: 'outlook_forward',
                description: "Forwarded email [{$messageId}] to ".implode(', ', $toRecipients),
                targetRecipients: $toRecipients,
                payload: ['message_id' => $messageId, 'to_recipients' => $toRecipients, 'comment' => $comment],
                status: 'executed',
                approvalToken: $token
            );
        }

        return [
            'status' => $success ? 'forwarded' : 'failed',
            'message_id' => $messageId,
            'success' => $success,
        ];
    }
}
