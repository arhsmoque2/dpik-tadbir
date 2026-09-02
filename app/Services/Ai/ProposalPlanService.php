<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Orchestrates multi-step AI proposal plans.
 * Executes all steps sequentially inside a single database transaction, resolving
 * intermediate $ref:<action_id> foreign key dependencies along the way.
 */
final readonly class ProposalPlanService
{
    public function __construct(
        private PlanReferenceResolver $referenceResolver = new PlanReferenceResolver(),
    ) {}

    /**
     * Execute a sequence of proposed action steps atomically.
     *
     * @param  User  $user
     * @param  list<array{id: string, action: class-string, payload: array<string, mixed>}>  $steps
     * @return array<string, array<string, mixed>>  Map of actionId => result data
     *
     * @throws Throwable
     */
    public function executePlan(User $user, array $steps): array
    {
        return DB::transaction(function () use ($user, $steps): array {
            $results = [];

            foreach ($steps as $step) {
                $actionId = $step['id'];
                $actionClass = $step['action'];
                $rawPayload = $step['payload'];

                // 1. Resolve any references from earlier steps in this plan
                $resolvedPayload = $this->referenceResolver->resolve($rawPayload, $results);

                // 2. Instantiate and execute the domain action
                if (! class_exists($actionClass)) {
                    throw new RuntimeException(sprintf('Action class "%s" does not exist.', $actionClass));
                }

                $actionInstance = app($actionClass);
                if (! method_exists($actionInstance, 'execute')) {
                    throw new RuntimeException(sprintf('Action class "%s" must define an execute() method.', $actionClass));
                }

                $record = $actionInstance->execute($user, $resolvedPayload);

                // 3. Record the generated ID for downstream steps
                $results[$actionId] = [
                    'id' => is_object($record) && isset($record->id) ? $record->id : null,
                    'record' => $record,
                ];
            }

            return $results;
        });
    }
}
