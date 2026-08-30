<?php

namespace App\Services\Audit;

use App\Models\AiActionReceipt;
use App\Models\User;

class ActionMemoryService
{
    /**
     * Records an immutable action receipt into the audit ledger.
     *
     * @param  list<string>|null  $targetRecipients
     * @param  array<string, mixed>|null  $payload
     */
    public function logReceipt(
        User $user,
        string $actionType,
        string $description,
        ?array $targetRecipients = null,
        ?array $payload = null,
        string $status = 'executed',
        ?string $approvalToken = null
    ): AiActionReceipt {
        return AiActionReceipt::create([
            'user_id' => $user->id,
            'action_type' => $actionType,
            'description' => $description,
            'target_recipients' => $targetRecipients,
            'payload' => $payload,
            'status' => $status,
            'approval_token' => $approvalToken,
            'executed_at' => now(),
        ]);
    }
}
