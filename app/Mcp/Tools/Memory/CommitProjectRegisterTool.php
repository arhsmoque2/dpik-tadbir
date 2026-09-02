<?php

namespace App\Mcp\Tools\Memory;

use App\Mcp\BaseTool;
use App\Models\ProjectRegistryEntry;
use App\Services\Memory\DecisionMarkerExtractor;

class CommitProjectRegisterTool extends BaseTool
{
    protected string $name = 'commit_project_register';

    protected string $description = 'Commits a key decision, commitment, or technical summary into the enterprise Project Register.';

    public function __construct(
        protected DecisionMarkerExtractor $extractor
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        return [
            'type' => 'object',
            'required' => ['project_code', 'summary'],
            'properties' => [
                'project_code' => ['type' => 'string'],
                'project_name' => ['type' => 'string'],
                'summary' => ['type' => 'string'],
                'source_type' => ['type' => 'string', 'default' => 'email_summary'],
                'user_id' => ['type' => 'integer'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $projectCode = (string) ($arguments['project_code'] ?? 'GENERAL');
        $projectName = isset($arguments['project_name']) ? (string) $arguments['project_name'] : null;
        $summary = (string) ($arguments['summary'] ?? '');
        $sourceType = (string) ($arguments['source_type'] ?? 'email_summary');
        $userId = isset($arguments['user_id']) ? (int) $arguments['user_id'] : auth()->id();
        if ($userId === null || $userId === 0) {
            throw new \RuntimeException('Cannot commit to project register outside an authenticated session.');
        }

        $extracted = $this->extractor->extract($summary);

        $entry = ProjectRegistryEntry::create([
            'project_code' => $projectCode,
            'project_name' => $projectName,
            'summary' => $summary,
            'decisions' => $extracted['decisions'],
            'commitments' => $extracted['commitments'],
            'source_type' => $sourceType,
            'user_id' => $userId,
            'recorded_at' => now(),
        ]);

        return [
            'status' => 'committed',
            'id' => $entry->id,
            'project_code' => $entry->project_code,
            'decisions_count' => count($extracted['decisions']),
            'commitments_count' => count($extracted['commitments']),
        ];
    }
}
