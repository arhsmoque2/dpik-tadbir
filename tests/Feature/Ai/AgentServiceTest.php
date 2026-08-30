<?php

use App\Models\AiRun;
use App\Models\ChatSession;
use App\Models\ProjectRegistryEntry;
use App\Models\User;
use App\Services\Ai\AgentService;

test('agent service handles user turn and resumes tool interaction', function () {
    $user = User::create([
        'name' => 'MD Agent User',
        'email' => 'md_agent@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    ProjectRegistryEntry::create([
        'project_code' => 'PC-2023-011',
        'project_name' => 'Sungai Udang Bridge',
        'summary' => 'Bridge repair milestone 1 completed on time.',
        'user_id' => $user->id,
    ]);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Executive Chat',
    ]);

    $agent = app(AgentService::class);

    // 1. Normal prompt
    $turn1 = $agent->handleUserTurn($session, 'Hello, what is the status of Sungai Udang?');
    expect($turn1->status)->toBe('completed');
    expect($turn1->content)->not->toBeEmpty();
    expect($session->messages()->count())->toBe(2);

    // 2. Draft action proposal prompt (suspension)
    $turn2 = $agent->handleUserTurn($session, 'Draft an email reply for Sungai Udang');
    expect($turn2->status)->toBe('suspended');
    expect($turn2->suspendedToolCall)->not->toBeNull();

    // 3. Resume with tool result
    $turn3 = $agent->resumeWithToolResult($session, 'call_test_123', ['approved' => true]);
    expect($turn3->status)->toBe('completed');

    // 4. Assert AiRun telemetry records were persisted
    expect(AiRun::count())->toBeGreaterThanOrEqual(2);
    $latestRun = AiRun::latest('id')->first();
    expect($latestRun)->not->toBeNull();
    expect($latestRun->total_tokens)->toBeGreaterThan(0);
    expect($latestRun->cost_usd)->toBeGreaterThanOrEqual(0);
    expect($latestRun->provider)->toBe('anthropic');
});
