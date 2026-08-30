<?php

namespace App\Mcp\Tools\Memory;

use App\Mcp\BaseTool;
use App\Services\Memory\MemoryRetrievalService;

class QueryProjectRegisterTool extends BaseTool
{
    protected string $name = 'query_project_register';

    protected string $description = 'Searches enterprise domain memory and past project commitments via hybrid FTS5 and RRF.';

    public function __construct(
        protected MemoryRetrievalService $memory
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
                'project_code' => ['type' => 'string'],
                'since' => ['type' => 'string', 'default' => '30d'],
                'limit' => ['type' => 'integer', 'default' => 5],
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
        $projectCode = isset($arguments['project_code']) ? (string) $arguments['project_code'] : null;
        $since = (string) ($arguments['since'] ?? '30d');
        $limit = (int) ($arguments['limit'] ?? 5);

        $results = $this->memory->search($query, $projectCode, $since, null, $limit);
        $denseContext = $this->memory->formatAsDenseContext($results);

        return [
            'count' => $results->count(),
            'dense_context' => $denseContext,
            'items' => $results->toArray(),
        ];
    }
}
