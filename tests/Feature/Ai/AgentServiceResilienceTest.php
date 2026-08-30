<?php

use App\Models\AiRun;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Ai\AgentService;
use App\Services\Ai\LlmGatewayService;

beforeEach(function () {
    LlmGatewayService::resetFakes();
});

afterEach(function () {
    LlmGatewayService::resetFakes();
});

test('agent service falls back from primary provider to secondary when rate limited', function () {
    $user = User::create([
        'name' => 'Resilience Tester',
        'email' => 'resilience@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Fallback Test Session',
    ]);

    // Fake: Anthropic throws 429 Rate Limit, Gemini returns healthy completion
    LlmGatewayService::fake([
        'anthropic' => new RuntimeException('rate_limit_exceeded: Anthropic Tier 1 quota exhausted'),
        'gemini' => [
            'content' => 'Successfully responded via Gemini fallback model.',
        ],
    ]);

    $agent = app(AgentService::class);
    $response = $agent->handleUserTurn($session, 'Bagaimanakah status kemajuan Jambatan Sungai Udang?');

    expect($response->status)->toBe('completed');
    expect($response->content)->toBe('Successfully responded via Gemini fallback model.');

    // Assert that the recorded telemetry indicates gemini took over
    $latestRun = AiRun::latest('id')->first();
    expect($latestRun)->not->toBeNull();
    expect($latestRun->provider)->toBe('gemini');
    expect($latestRun->status)->toBe('completed');
    expect($latestRun->response)->toContain('Gemini fallback model');
});

test('agent service gracefully degrades and logs failed run when all AI providers fail', function () {
    $user = User::create([
        'name' => 'Total Failure Tester',
        'email' => 'failover@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Outage Test Session',
    ]);

    // Fake: Both providers throw exceptions
    LlmGatewayService::fake([
        'anthropic' => new RuntimeException('Anthropic 429 Rate Limit Exceeded'),
        'gemini' => new RuntimeException('Gemini 503 Service Unavailable / Gateway Timeout'),
    ]);

    $agent = app(AgentService::class);
    $prompt = 'Semak jadual mesyuarat esok pagi.';
    $response = $agent->handleUserTurn($session, $prompt);

    // Assert graceful degradation — no 500 thrown
    expect($response->status)->toBe('failed');
    expect($response->content)->toContain('experiencing high upstream traffic');

    // Assert failed run was recorded in telemetry database
    $failedRun = AiRun::latest('id')->first();
    expect($failedRun)->not->toBeNull();
    expect($failedRun->status)->toBe('failed');
    expect($failedRun->error_message)->toContain('Gemini 503 Service Unavailable');
    expect($failedRun->payload)->toBe($prompt);
    expect($failedRun->latency_ms)->toBeGreaterThanOrEqual(0);

    // Assert message in chat session reflects the failure gracefully
    $lastMsg = $session->messages()->latest('id')->first();
    expect($lastMsg)->not->toBeNull();
    expect($lastMsg->role)->toBe('assistant');
    expect($lastMsg->metadata['status'])->toBe('failed');
});
