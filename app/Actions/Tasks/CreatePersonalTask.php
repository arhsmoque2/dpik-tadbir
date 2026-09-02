<?php

declare(strict_types=1);

namespace App\Actions\Tasks;

use App\Models\PersonalTask;
use App\Models\User;
use App\Support\TenantFkValidator;

/**
 * Domain Action: Creates a personal task for the executive.
 */
final readonly class CreatePersonalTask
{
    /**
     * @param  User  $user
     * @param  array{
     *     title: string,
     *     project_code?: string|null,
     *     description?: string|null,
     *     status?: string|null,
     *     due_date?: string|\DateTimeInterface|null
     * }  $data
     * @return PersonalTask
     */
    public function execute(User $user, array $data): PersonalTask
    {
        TenantFkValidator::assertOwned($user, $data, []);

        return PersonalTask::query()->create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'project_code' => $data['project_code'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'due_date' => $data['due_date'] ?? null,
        ]);
    }
}
