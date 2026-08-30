<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Services\Mcp\OutlookMcpBridge;

class OutlookCreateDraftTool extends BaseTool
{
    protected string $name = 'outlook_create_draft';

    protected string $description = 'Stages a new email draft in Outlook via Microsoft Graph API.';

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
            'required' => ['subject', 'body', 'to_recipients'],
            'properties' => [
                'subject' => ['type' => 'string'],
                'body' => ['type' => 'string'],
                'to_recipients' => ['type' => 'array', 'items' => ['type' => 'string']],
                'cc_recipients' => ['type' => 'array', 'items' => ['type' => 'string']],
                'approval_token' => ['type' => 'string'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $subject = (string) ($arguments['subject'] ?? '');
        $body = (string) ($arguments['body'] ?? '');
        /** @var list<string> $toRecipients */
        $toRecipients = (array) ($arguments['to_recipients'] ?? []);
        /** @var list<string> $ccRecipients */
        $ccRecipients = (array) ($arguments['cc_recipients'] ?? []);

        return $this->bridge->createDraft($subject, $body, $toRecipients, $ccRecipients);
    }
}
