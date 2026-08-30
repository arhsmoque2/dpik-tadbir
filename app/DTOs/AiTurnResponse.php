<?php

namespace App\DTOs;

class AiTurnResponse
{
    /**
     * @param  array<string, mixed>|null  $suspendedToolCall
     * @param  array<int, array<string, mixed>>  $executedActions
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $content,
        public string $status = 'completed', // completed, suspended, error
        public ?array $suspendedToolCall = null,
        public array $executedActions = [],
        public array $metadata = []
    ) {}

    public function isSuspended(): bool
    {
        return $this->status === 'suspended' && $this->suspendedToolCall !== null;
    }
}
