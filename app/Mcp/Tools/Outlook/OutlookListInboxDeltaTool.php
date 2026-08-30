<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Services\Mcp\OutlookMcpBridge;

class OutlookListInboxDeltaTool extends BaseTool
{
    protected string $name = 'outlook_list_inbox_delta';

    protected string $description = 'Fetches new or unread emails since lookback horizon.';

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
            'properties' => [
                'lookback_hours' => ['type' => 'integer', 'default' => 24],
                'limit' => ['type' => 'integer', 'default' => 25],
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
        $lookback = (int) ($arguments['lookback_hours'] ?? 24);
        $limit = (int) ($arguments['limit'] ?? 25);
        $concise = (bool) ($arguments['concise'] ?? true);

        return $this->bridge->fetchInboxDelta($lookback, $limit, $concise);
    }
}
