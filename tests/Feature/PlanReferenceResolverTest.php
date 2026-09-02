<?php

declare(strict_types=1);

use App\Actions\Notes\CreatePersonalNote;
use App\Actions\Tasks\CreatePersonalTask;
use App\Models\User;
use App\Services\AI\PlanReference;
use App\Services\AI\PlanReferenceResolver;
use App\Services\AI\ProposalPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('plan reference correctly identifies and extracts targets', function () {
    $ref = PlanReference::to('step_1');
    expect(PlanReference::is($ref))->toBeTrue()
        ->and(PlanReference::target($ref))->toBe('step_1')
        ->and(PlanReference::actionId('step_1#0'))->toBe('step_1')
        ->and(PlanReference::index('step_1#0'))->toBe(0);
});

test('plan reference resolver rewrites dependent targets in payload', function () {
    $resolver = new PlanReferenceResolver();
    $payload = [
        'title' => 'Follow up on JKR Geotechnical Scope',
        'project_code' => '$ref:step_1',
    ];

    $executedResults = [
        'step_1' => [
            'id' => 'PRJ-BINTULU-01',
        ],
    ];

    $resolved = $resolver->resolve($payload, $executedResults);

    expect($resolved['project_code'])->toBe('PRJ-BINTULU-01');
});

test('proposal plan service executes multi-step actions atomically', function () {
    $user = User::factory()->create();
    $planService = new ProposalPlanService();

    $steps = [
        [
            'id' => 'step_note',
            'action' => CreatePersonalNote::class,
            'payload' => [
                'title' => 'Bintulu Site Meeting',
                'content' => 'Discussed soil consolidation requirements with JKR engineer.',
            ],
        ],
        [
            'id' => 'step_task',
            'action' => CreatePersonalTask::class,
            'payload' => [
                'title' => 'Submit soil testing report',
                'description' => 'Attached to note: $ref:step_note',
                'status' => 'pending',
            ],
        ],
    ];

    $results = $planService->executePlan($user, $steps);

    expect($results)->toHaveKey('step_note')
        ->and($results)->toHaveKey('step_task')
        ->and($results['step_note']['id'])->not->toBeNull()
        ->and($results['step_task']['id'])->not->toBeNull();

    $this->assertDatabaseHas('personal_notes', [
        'title' => 'Bintulu Site Meeting',
        'user_id' => $user->id,
    ]);

    $this->assertDatabaseHas('personal_tasks', [
        'title' => 'Submit soil testing report',
        'user_id' => $user->id,
    ]);
});
