<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Mcp\Tools\Concerns\ScopesMailBridge;
use App\Services\Mail\MailBridge;

class OutlookSearchMailTool extends BaseTool
{
    use ScopesMailBridge;

    protected string $name = 'outlook_search_mail';

    protected string $description = 'Searches Outlook mailbox with concise executive mode.';

    public function __construct(
        protected MailBridge $bridge
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['query'],
            'properties' => [
                'query' => ['type' => 'string'],
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
        $query = (string) ($arguments['query'] ?? '');
        $limit = (int) ($arguments['limit'] ?? 25);
        $concise = (bool) ($arguments['concise'] ?? true);

        return $this->scopedBridge($this->bridge)->searchMail($query, $limit, $concise);
    }
}
