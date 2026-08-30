<?php

namespace App\DTOs;

class MemorySearchResult
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $id,
        public string $projectCode,
        public ?string $projectName,
        public string $summary,
        public ?string $recordedAt,
        public float $score = 1.0,
        public ?string $decisionMarker = null,
        public array $metadata = []
    ) {}
}
