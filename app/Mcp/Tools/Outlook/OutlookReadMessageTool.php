<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Mcp\Tools\Concerns\ScopesOutlookBridge;
use App\Services\Mcp\OutlookMcpBridge;

class OutlookReadMessageTool extends BaseTool
{
    use ScopesOutlookBridge;

    protected string $name = 'outlook_read_message';

    protected string $description = 'Reads full message contents and attachment metadata by ID.';

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
            'required' => ['message_id'],
            'properties' => [
                'message_id' => ['type' => 'string'],
                'concise' => ['type' => 'boolean', 'default' => true],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $messageId = (string) ($arguments['message_id'] ?? '');
        $concise = (bool) ($arguments['concise'] ?? true);

        return $this->scopedBridge($this->bridge)->readMessage($messageId, $concise);
    }
}
