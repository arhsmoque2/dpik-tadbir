<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * Validates that foreign keys passed in Action payloads or AI tool arguments
 * strictly belong to the authenticated executive's sovereign workspace.
 */
final readonly class TenantFkValidator
{
    /**
     * Assert that foreign key models belong to the expected user/tenant.
     *
     * @param  User  $user
     * @param  array<string, mixed>  $data
     * @param  array<string, class-string<Model>>  $foreignKeyModelMap  e.g. ['project_id' => Project::class]
     * @param  string  $ownerColumn  defaults to 'user_id'
     *
     * @throws ValidationException
     */
    public static function assertOwned(
        User $user,
        array $data,
        array $foreignKeyModelMap,
        string $ownerColumn = 'user_id'
    ): void {
        foreach ($foreignKeyModelMap as $key => $modelClass) {
            if (! isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
                continue;
            }

            $id = $data[$key];
            if (! is_string($id) && ! is_int($id)) {
                continue;
            }

            // Skip plan reference placeholders ($ref:...) as they are resolved at approval time
            if (is_string($id) && str_starts_with($id, '$ref:')) {
                continue;
            }

            /** @var Model|null $record */
            $record = $modelClass::query()->find($id);

            if ($record === null) {
                throw ValidationException::withMessages([
                    $key => [sprintf('The referenced %s does not exist.', class_basename($modelClass))],
                ]);
            }

            // If the model has the owner column and user is not superadmin, enforce isolation
            if (isset($record->{$ownerColumn}) && (int) $record->{$ownerColumn} !== (int) $user->id) {
                // Allow partners/superadmins if explicitly authorized, otherwise reject cross-tenant writes
                if (! method_exists($user, 'isSuperAdmin') || ! $user->isSuperAdmin()) {
                    throw ValidationException::withMessages([
                        $key => [sprintf('You do not have permission to attach this %s.', class_basename($modelClass))],
                    ]);
                }
            }
        }
    }
}
