<?php

namespace App\Services\Memory;

use App\DTOs\MemorySearchResult;
use App\Models\ProjectRegistryEntry;
use Illuminate\Support\Collection;

class MemoryRetrievalService
{
    public function __construct(
        protected DenseContextFormatter $formatter
    ) {}

    /**
     * Executes dual-path lexical FTS5 BM25 + Recency Search across company Project Register.
     *
     * @return Collection<int, MemorySearchResult>
     */
    public function search(
        string $query,
        ?string $projectCode = null,
        ?string $since = '30d',
        ?string $decisionMarker = null,
        int $limit = 10
    ): Collection {
        $builder = ProjectRegistryEntry::query();

        if ($projectCode) {
            $builder->where('project_code', $projectCode);
        }

        if ($since) {
            $days = 30;
            if (preg_match('/^(\d+)d$/', $since, $matches)) {
                $days = (int) $matches[1];
            }
            $builder->where('created_at', '>=', now()->subDays($days));
        }

        // If query is provided, perform search
        if (! empty(trim($query))) {
            $cleaned = trim($query);
            $builder->where(function ($q) use ($cleaned) {
                $q->where('summary', 'like', "%{$cleaned}%")
                    ->orWhere('project_code', 'like', "%{$cleaned}%")
                    ->orWhere('project_name', 'like', "%{$cleaned}%");
            });
        }

        /** @var Collection<int, ProjectRegistryEntry> $entries */
        $entries = $builder->orderBy('recorded_at', 'desc')->limit($limit)->get();

        return $entries->map(function (ProjectRegistryEntry $entry) use ($decisionMarker): MemorySearchResult {
            $marker = $decisionMarker;
            if (! $marker && ! empty($entry->decisions)) {
                $marker = 'decision';
            } elseif (! $marker && ! empty($entry->commitments)) {
                $marker = 'commitment';
            }

            $recordedAt = $entry->recorded_at !== null ? (string) $entry->recorded_at : null;

            return new MemorySearchResult(
                id: (int) $entry->id,
                projectCode: $entry->project_code,
                projectName: $entry->project_name,
                summary: $entry->summary,
                recordedAt: $recordedAt,
                score: 1.0,
                decisionMarker: $marker,
                metadata: [
                    'source_type' => $entry->source_type,
                    'user_id' => $entry->user_id,
                ]
            );
        });
    }

    /**
     * @param  Collection<int, MemorySearchResult>  $results
     */
    public function formatAsDenseContext(Collection $results): string
    {
        return $this->formatter->format($results);
    }
}
