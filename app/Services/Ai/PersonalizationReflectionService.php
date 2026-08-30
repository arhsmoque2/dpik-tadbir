<?php

namespace App\Services\Ai;

use App\Models\User;
use App\Models\UserPersonalizationProfile;

class PersonalizationReflectionService
{
    /**
     * Conducts weekly reflection on executive interactions and updates their persona summary.
     */
    public function reflectForUser(User $user): UserPersonalizationProfile
    {
        $profile = UserPersonalizationProfile::firstOrNew(['user_id' => $user->id]);

        $receiptsCount = $user->actionReceipts()->where('created_at', '>=', now()->subDays(7))->count();
        $notesCount = $user->personalNotes()->where('created_at', '>=', now()->subDays(7))->count();

        $summary = sprintf(
            'Executive %s: Focus on high-tempo inbox triage. In the last 7 days, executed %d actions and created %d personal notes.',
            $user->name,
            $receiptsCount,
            $notesCount
        );

        $profile->persona_summary = $summary;
        $profile->last_reflected_at = now();
        $profile->save();

        return $profile;
    }
}
