<?php

namespace App\Services\Ai;

use App\DTOs\AiTurnResponse;
use App\Models\AiActionReceipt;
use App\Models\ChatSession;

class AntiHallucinationGuard
{
    /**
     * Validates that any action claimed by the LLM response is backed by an actual AiActionReceipt in the current turn.
     */
    public function validateTurnResponse(AiTurnResponse $response, ChatSession $session): bool
    {
        $text = strtolower($response->content);

        // Check if the model claims to have dispatched/sent an email
        $claimsDispatch = str_contains($text, 'sent the email') ||
            str_contains($text, 'telah menghantar emel') ||
            str_contains($text, 'forwarded the message') ||
            str_contains($text, 'telah memajukan emel');

        if ($claimsDispatch) {
            // Check if a receipt was created in this session recently (within the last 2 minutes)
            $hasReceipt = AiActionReceipt::where('user_id', $session->user_id)
                ->where('created_at', '>=', now()->subMinutes(2))
                ->whereIn('action_type', ['outlook_reply', 'outlook_forward', 'outlook_send'])
                ->where('status', 'executed')
                ->exists();

            if (! $hasReceipt && count($response->executedActions) === 0) {
                return false;
            }
        }

        return true;
    }
}
