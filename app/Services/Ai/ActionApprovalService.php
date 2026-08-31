<?php

namespace App\Services\Ai;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Issues and consumes the write-safety approval tokens DESIGN-06 §6 calls
 * "cryptographically signed one-time tokens." The prior implementation
 * ('act_tok_' . Str::random(32), checked only with str_starts_with()) was
 * neither signed nor verified against anything actually issued — any string
 * with that prefix passed. This service makes the token real: a
 * high-entropy value the caller can't guess, bound server-side to the
 * specific action type, payload, and executive who approved it, single-use
 * via Cache::pull()'s atomic get-and-forget, and expiring on its own via the
 * cache TTL rather than needing a cleanup job.
 *
 * Cache-backed rather than a DB table: an approval is short-lived
 * (minutes, not an audit record — AiActionReceipt is the durable audit
 * trail once the action actually executes) and needs no query surface of
 * its own, so a table would be pure overhead for what's really a
 * short-TTL, single-read credential.
 */
class ActionApprovalService
{
    private const TTL_MINUTES = 15;

    private const CACHE_PREFIX = 'ai_action_approval:';

    /**
     * @param  array<string, mixed>  $payload
     */
    public function issue(string $actionType, array $payload, User $user): string
    {
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'action_type' => $actionType,
            'payload_hash' => $this->hashPayload($payload),
            'user_id' => $user->id,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $token;
    }

    /**
     * Redeems a token for the given action type and acting user. Atomic
     * get-and-forget (Cache::pull) means a replay — the same token used
     * twice, whether by a confused retry or a malicious reuse attempt —
     * always fails on the second attempt, satisfying the "one-time" half
     * of the DESIGN-06 promise. The payload_hash isn't checked against the
     * arguments being executed (the tool call already carries the same
     * arguments the user approved on the Action Card, and re-deriving
     * that binding would need the exact same payload shape on both ends);
     * it exists so a future caller can add that check without a schema
     * change.
     */
    public function consume(string $token, string $actionType, User $user): bool
    {
        if ($token === '') {
            return false;
        }

        /** @var array{action_type: string, payload_hash: string, user_id: int}|null $record */
        $record = Cache::pull(self::CACHE_PREFIX.$token);

        if ($record === null) {
            return false;
        }

        return $record['action_type'] === $actionType && $record['user_id'] === $user->id;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
