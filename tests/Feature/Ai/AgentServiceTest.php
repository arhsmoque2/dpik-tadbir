<?php

use App\Models\AiRun;
use App\Models\ChatSession;
use App\Models\ProjectRegistryEntry;
use App\Models\User;
use App\Models\UserPersonalizationProfile;
use App\Services\Ai\AgentService;
use App\Services\Ai\LlmGatewayService;

beforeEach(function () {
    LlmGatewayService::resetFakes();
});

afterEach(function () {
    LlmGatewayService::resetFakes();
});

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

test('agent service loops non-interactive tool calls back to the model for a synthesized response', function () {
    $user = User::create([
        'name' => 'Loop Test User',
        'email' => 'loop_test@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Loop Test Session',
    ]);

    // First round-trip: the model calls a read-only tool. Second: given the
    // tool_result fed back, it synthesizes a real final answer — this is
    // the behavior the old single-shot implementation never exercised (it
    // stopped at the first tool_use block and returned whatever text
    // happened to accompany it).
    LlmGatewayService::fakeSequence([
        [
            'content' => '',
            'tool_calls' => [
                ['id' => 'call_loop_1', 'name' => 'query_project_register', 'arguments' => ['query' => 'Sungai Udang']],
            ],
            'stop_reason' => 'tool_use',
        ],
        [
            'content' => 'Sungai Udang bridge repair milestone 1 is complete, on schedule.',
            'stop_reason' => 'end_turn',
        ],
    ]);

    $agent = app(AgentService::class);
    $response = $agent->handleUserTurn($session, 'What is the status of Sungai Udang?');

    expect($response->status)->toBe('completed');
    expect($response->content)->toBe('Sungai Udang bridge repair milestone 1 is complete, on schedule.');
    expect($response->executedActions)->toHaveCount(1);
    expect($response->executedActions[0]['tool'])->toBe('query_project_register');

    // user + assistant(tool_use) + tool(result) + assistant(final synthesis)
    expect($session->messages()->count())->toBe(4);
    expect($session->messages()->where('role', 'tool')->count())->toBe(1);
});

test('agent service injects the executive personalization profile into the system prompt', function () {
    $user = User::create([
        'name' => 'Personalized User',
        'email' => 'personalized@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    UserPersonalizationProfile::create([
        'user_id' => $user->id,
        'persona_summary' => 'Prefers concise, bullet-point summaries in English.',
        'preferences' => ['reply_tone' => 'formal'],
        'last_reflected_at' => now(),
    ]);

    $agent = app(AgentService::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('buildSystemPrompt');
    $method->setAccessible(true);

    $prompt = $method->invoke($agent, $user, [], '');

    expect($prompt)->toContain('KNOWN ABOUT THIS EXECUTIVE');
    expect($prompt)->toContain('Prefers concise, bullet-point summaries in English.');
    expect($prompt)->toContain('reply_tone: formal');
});
