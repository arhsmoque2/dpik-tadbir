<?php

declare(strict_types=1);

namespace App\Actions\Notes;

use App\Models\PersonalNote;
use App\Models\User;
use App\Support\TenantFkValidator;

/**
 * Domain Action: Creates a personal executive note.
 */
final readonly class CreatePersonalNote
{
    /**
     * @param  User  $user
     * @param  array{
     *     title: string,
     *     content: string,
     *     project_code?: string|null,
     *     tags?: array<int, string>|null
     * }  $data
     * @return PersonalNote
     */
    public function execute(User $user, array $data): PersonalNote
    {
        TenantFkValidator::assertOwned($user, $data, []);

        return PersonalNote::query()->create([
            'user_id' => $user->id,
            'title' => $data['title'],
            'content' => $data['content'],
            'project_code' => $data['project_code'] ?? null,
            'tags' => $data['tags'] ?? [],
        ]);
    }
}
