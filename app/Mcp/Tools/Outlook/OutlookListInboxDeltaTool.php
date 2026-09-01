<?php

namespace App\Mcp\Tools\Outlook;

use App\Mcp\BaseTool;
use App\Mcp\Tools\Concerns\ScopesOutlookBridge;
use App\Services\BundleService;
use App\Services\Mcp\OutlookMcpBridge;

class OutlookListInboxDeltaTool extends BaseTool
{
    use ScopesOutlookBridge;

    protected string $name = 'outlook_list_inbox_delta';

    protected string $description = 'Fetches new or unread emails since lookback horizon and materializes a Bundle.';

    protected BundleService $bundleService;

    public function __construct(
        protected OutlookMcpBridge $bridge,
        ?BundleService $bundleService = null
    ) {
        $this->bundleService = $bundleService ?? app(BundleService::class);
    }

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
                'project_code' => ['type' => 'string'],
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
        $projectCode = isset($arguments['project_code']) ? (string) $arguments['project_code'] : null;

        $result = $this->scopedBridge($this->bridge)->fetchInboxDelta($lookback, $limit, $concise);

        $messages = $result['messages'] ?? $result['value'] ?? [];
        if (! is_array($messages)) {
            $messages = [];
        }

        $user = auth()->user();
        if ($user instanceof \App\Models\User && count($messages) > 0) {
            /** @var list<array<string, mixed>> $messagesList */
            $messagesList = array_values($messages);

            $bundle = $this->bundleService->createBundle(
                $user,
                ['lookback_hours' => $lookback, 'direct_only' => true],
                $messagesList,
                $projectCode
            );

            $result['bundle_id'] = $bundle->id;
            $result['filter_label'] = $bundle->filter_label;
        }

        return $result;
    }
}
