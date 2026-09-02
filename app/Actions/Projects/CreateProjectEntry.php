<?php

declare(strict_types=1);

namespace App\Actions\Projects;

use App\Models\ProjectRegistryEntry;
use App\Models\User;
use App\Support\TenantFkValidator;

/**
 * Domain Action: Creates a new project registry record in the sovereign database.
 */
final readonly class CreateProjectEntry
{
    /**
     * @param  User  $user
     * @param  array{
     *     project_code: string,
     *     project_name: string,
     *     summary?: string|null,
     *     decisions?: array<int, string>|null,
     *     commitments?: array<int, string>|null,
     *     source_type?: string|null,
     *     recorded_at?: string|\DateTimeInterface|null
     * }  $data
     * @return ProjectRegistryEntry
     */
    public function execute(User $user, array $data): ProjectRegistryEntry
    {
        TenantFkValidator::assertOwned($user, $data, []);

        return ProjectRegistryEntry::query()->create([
            'user_id' => $user->id,
            'project_code' => $data['project_code'],
            'project_name' => $data['project_name'],
            'summary' => $data['summary'] ?? null,
            'decisions' => $data['decisions'] ?? [],
            'commitments' => $data['commitments'] ?? [],
            'source_type' => $data['source_type'] ?? 'executive_copilot',
            'recorded_at' => $data['recorded_at'] ?? now(),
        ]);
    }
}
