<?php

namespace App\Services;

use App\Models\Bundle;
use App\Models\BundleEmail;
use App\Models\ProjectRegistryEntry;
use App\Models\User;
use Illuminate\Support\Carbon;

class BundleService
{
    /**
     * Determine filter label automatically based on query parameters.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function determineFilterLabel(array $criteria, ?string $projectCode = null): string
    {
        if (! empty($projectCode)) {
            $project = ProjectRegistryEntry::where('project_code', $projectCode)->first();
            $title = $project instanceof ProjectRegistryEntry ? (string) $project->project_name : (string) $projectCode;

            return "{$projectCode} · {$title}";
        }

        $date = now()->format('d M Y');

        return "Direct Correspondence · {$date}";
    }

    /**
     * Persist a materialized Bundle with its lightweight email pointers.
     *
     * @param  array<string, mixed>  $criteria
     * @param  list<array<string, mixed>>  $messages
     */
    public function createBundle(
        User $user,
        array $criteria,
        array $messages,
        ?string $projectCode = null,
        ?string $notes = null
    ): Bundle {
        $filterLabel = $this->determineFilterLabel($criteria, $projectCode);

        $bundle = Bundle::create([
            'user_id' => $user->id,
            'filter_label' => $filterLabel,
            'filter_criteria' => $criteria,
            'project_code' => $projectCode,
            'retrieved_at' => now(),
            'email_count' => count($messages),
            'notes' => $notes,
        ]);

        foreach ($messages as $msg) {
            BundleEmail::create([
                'bundle_id' => $bundle->id,
                'message_id' => $msg['id'] ?? $msg['message_id'] ?? '',
                'from_name' => $msg['from_name'] ?? $msg['from']['name'] ?? null,
                'from_email' => $msg['from_email'] ?? $msg['from']['email'] ?? null,
                'subject' => $msg['subject'] ?? '(No Subject)',
                'snippet' => $msg['snippet'] ?? substr($msg['body'] ?? '', 0, 250),
                'received_at' => isset($msg['received_at']) ? Carbon::parse($msg['received_at']) : now(),
            ]);
        }

        return $bundle;
    }
}
