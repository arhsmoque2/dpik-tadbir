<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\User;

class AutoPromotionService
{
    /**
     * Retrieve projects meeting the auto-promotion threshold (e.g. >= 3 retrievals in 7 days).
     *
     * @return array<int, array{project_code: string, count: int}>
     */
    public function getPromotedProjects(User $user, int $days = 7, int $threshold = 3): array
    {
        return Bundle::query()
            ->where('user_id', $user->id)
            ->whereNotNull('project_code')
            ->where('retrieved_at', '>=', now()->subDays($days))
            ->selectRaw('project_code, COUNT(*) as count')
            ->groupBy('project_code')
            ->havingRaw('COUNT(*) >= ?', [$threshold])
            ->get()
            ->map(function ($row): array {
                /** @var object{project_code: string, count: int|string} $row */
                return [
                    'project_code' => (string) $row->project_code,
                    'count' => (int) $row->count,
                ];
            })
            ->toArray();
    }
}
