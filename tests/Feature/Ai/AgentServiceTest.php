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

test('agent service resolves per-session context_mode token budgets, defaulting unknown modes to general', function () {
    // ADR-021: ChatSession.context_mode existed but was never read by
    // AgentService — every session got the same 40-message/4096-token
    // budget regardless of what it was actually for.
    $agent = app(AgentService::class);
    $reflection = new ReflectionClass($agent);
    $method = $reflection->getMethod('contextProfileFor');
    $method->setAccessible(true);

    $user = User::create([
        'name' => 'Context Mode User',
        'email' => 'context_mode@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $triage = ChatSession::create(['user_id' => $user->id, 'title' => 'Triage', 'context_mode' => 'inbox_triage']);
    expect($method->invoke($agent, $triage))->toBe(['history_limit' => 20, 'max_tokens' => 1024]);

    $deepdive = ChatSession::create(['user_id' => $user->id, 'title' => 'Deep dive', 'context_mode' => 'project_deepdive']);
    expect($method->invoke($agent, $deepdive))->toBe(['history_limit' => 60, 'max_tokens' => 4096]);

    $executive = ChatSession::create(['user_id' => $user->id, 'title' => 'Executive', 'context_mode' => 'executive']);
    expect($method->invoke($agent, $executive))->toBe(['history_limit' => 40, 'max_tokens' => 4096]);

    // An unrecognized mode falls back to 'general' rather than throwing or
    // silently using a 0/empty budget.
    $unknown = ChatSession::create(['user_id' => $user->id, 'title' => 'Unknown', 'context_mode' => 'something_new']);
    expect($method->invoke($agent, $unknown))->toBe(['history_limit' => 40, 'max_tokens' => 4096]);
});

test('agent service persists real anthropic token usage on AiRun instead of the strlen estimate', function () {
    // ADR-021: LlmGatewayService::invokeAnthropic() already returned real
    // input_tokens/output_tokens from Anthropic's own usage field, but
    // AgentService never threaded them into AiRunRecorder — every AiRun's
    // token/cost figures were a strlen($prompt)/4 estimate of the raw user
    // prompt only, ignoring the system prompt, tool schemas, and history.
    $user = User::create([
        'name' => 'Token Telemetry User',
        'email' => 'token_telemetry@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create(['user_id' => $user->id, 'title' => 'Telemetry Session']);

    Http::fake([
        'https://api.anthropic.com/v1/messages' => Http::response([
            'content' => [['type' => 'text', 'text' => 'The register shows no open items.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 733, 'output_tokens' => 41],
        ], 200),
    ]);

    $agent = app(AgentService::class);
    $response = $agent->handleUserTurn($session, 'Anything outstanding?', [
        'live' => true,
        'provider' => 'anthropic',
        'model' => 'claude-3-7-sonnet-20250219',
        // Overrides the auth()->user() fallback LlmGatewayService would
        // otherwise resolve for key lookup — that user has no
        // anthropic_api_key set, which would fail before the fake HTTP
        // response is ever reached.
        'user' => new User(['anthropic_api_key' => 'sk-ant-my-mock-key']), // gitleaks:allow
    ]);

    expect($response->status)->toBe('completed');

    $run = AiRun::latest('id')->first();
    expect($run->prompt_tokens)->toBe(733);
    expect($run->completion_tokens)->toBe(41);
    expect($run->total_tokens)->toBe(774);
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

test('resumeWithToolResult suppresses duplicate user message and guarantees strict role alternation for Anthropic wire format', function () {
    $user = User::create([
        'name' => 'Alternation Test User',
        'email' => 'alternation@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Alternation Test Session',
    ]);

    // Turn 1: Assistant suspends on propose_action_card
    LlmGatewayService::fakeSequence([
        [
            'content' => 'I have drafted the email response for your approval.',
            'tool_calls' => [
                [
                    'id' => 'call_suspend_alt_1',
                    'name' => 'propose_action_card',
                    'arguments' => [
                        'action_type' => 'outlook_draft',
                        'title' => 'Draft Reply',
                        'summary' => 'Confirm attendance',
                        'payload' => ['subject' => 'RE: Meeting'],
                    ],
                ],
            ],
            'stop_reason' => 'tool_use',
        ],
        [
            'content' => 'Action card approved. Draft has been finalized.',
            'stop_reason' => 'end_turn',
        ],
    ]);

    $agent = app(AgentService::class);
    $suspendedResponse = $agent->handleUserTurn($session, 'Draft an email reply');
    expect($suspendedResponse->status)->toBe('suspended');

    // Turn 2: Operator confirms Action Card -> resumeWithToolResult
    $resumedResponse = $agent->resumeWithToolResult($session, 'call_suspend_alt_1', ['approved' => true]);
    expect($resumedResponse->status)->toBe('completed');
    expect($resumedResponse->content)->toBe('Action card approved. Draft has been finalized.');

    // Assert database messages sequence: user -> assistant(suspended) -> tool(result) -> assistant(final)
    $messages = $session->messages()->orderBy('id', 'asc')->get();
    expect($messages)->toHaveCount(4);

    $roles = $messages->pluck('role')->toArray();
    expect($roles)->toBe(['user', 'assistant', 'tool', 'assistant']);

    // Crucial check: verify there are NO consecutive user-role entries in database
    for ($i = 0; $i < count($roles) - 1; $i++) {
        expect($roles[$i] === 'user' && $roles[$i + 1] === 'user')
            ->toBeFalse('Found consecutive user roles which causes HTTP 400 in Anthropic API');
    }

    // Assert gateway toAnthropicMessages wire format translation produces strictly alternating turns
    $gateway = app(LlmGatewayService::class);
    $reflectionGateway = new ReflectionClass($gateway);
    $toAnthropicMethod = $reflectionGateway->getMethod('toAnthropicMessages');
    $toAnthropicMethod->setAccessible(true);

    $reflectionAgent = new ReflectionClass($agent);
    $toNeutralMethod = $reflectionAgent->getMethod('toNeutralMessages');
    $toNeutralMethod->setAccessible(true);

    $neutralMessages = $toNeutralMethod->invoke($agent, $messages);
    $anthropicWire = $toAnthropicMethod->invoke($gateway, $neutralMessages);

    $anthropicRoles = array_column($anthropicWire, 'role');
    // In Anthropic wire format: user(prompt) -> assistant(tool_use) -> user(tool_result) -> assistant(final)
    expect($anthropicRoles)->toBe(['user', 'assistant', 'user', 'assistant']);

    for ($i = 0; $i < count($anthropicRoles) - 1; $i++) {
        expect($anthropicRoles[$i])->not->toBe($anthropicRoles[$i + 1], "Anthropic wire messages must strictly alternate roles (index {$i} and index ".($i + 1).')');
    }

    // Assert tool_name was resolved and Gemini wire format also produces valid functionResponse
    $toGeminiMethod = $reflectionGateway->getMethod('toGeminiContents');
    $toGeminiMethod->setAccessible(true);
    $geminiWire = $toGeminiMethod->invoke($gateway, $neutralMessages);

    expect($geminiWire[2]['role'])->toBe('user');
    expect($geminiWire[2]['parts'][0]['functionResponse']['name'])->toBe('propose_action_card');
    expect($geminiWire[2]['parts'][0]['functionResponse']['response']['content'])->toBe(['approved' => true]);
});
