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

test('pii is strictly redacted from persisted ai_runs payload, response, and metadata', function () {
    $user = User::create([
        'name' => 'Executive Officer',
        'email' => 'exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Tender Review Session',
    ]);

    $agent = app(AgentService::class);

    $rawNric = '850712-14-5543';
    $rawCard = '4111222233334444';
    $rawSecret = 'sk-live-1234567890abcdef1234567890'; // gitleaks:allow
    $prompt = "Sila semak invois kontraktor dengan IC {$rawNric}, kad kredit {$rawCard}, dan API {$rawSecret}.";

    $response = $agent->handleUserTurn($session, $prompt);
    expect($response->content)->not->toBeEmpty();

    $run = AiRun::latest('id')->first();
    expect($run)->not->toBeNull();
    expect($run->has_pii)->toBeTrue();

    // 1. Verify payload does not contain plaintext PII
    expect($run->payload)->not->toContain($rawNric);
    expect($run->payload)->not->toContain($rawCard);
    expect($run->payload)->not->toContain($rawSecret);
    expect($run->payload)->toContain('[REDACTED_NRIC]');
    expect($run->payload)->toContain('[REDACTED_CREDIT_CARD]');
    expect($run->payload)->toContain('[REDACTED_SECRET]');

    // 2. Verify response does not leak plaintext PII
    expect($run->response)->not->toContain($rawNric);
    expect($run->response)->not->toContain($rawCard);
    expect($run->response)->not->toContain($rawSecret);

    // 3. Verify metadata JSON does not contain plaintext PII
    $metadataJson = json_encode($run->metadata);
    expect($metadataJson)->not->toContain($rawNric);
    expect($metadataJson)->not->toContain($rawCard);
    expect($metadataJson)->not->toContain($rawSecret);

    // 4. Verify PII types and counts are cleanly recorded without raw values
    expect($run->metadata)->toHaveKey('pii_types');
    expect($run->metadata['pii_types'])->toContain('nric_formatted');
    expect($run->metadata['pii_types'])->toContain('credit_card');
    expect($run->metadata['pii_types'])->toContain('secret_key');
});

test('pii is strictly redacted from error messages in ai_runs and chat_messages upon upstream failure', function () {
    $user = User::create([
        'name' => 'Security Officer',
        'email' => 'sec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Error Leak Prevention Session',
    ]);

    $rawNric = '800101-10-1234';
    $rawCard = '5105105105105100';

    // Upstream exception echoes sensitive user PII
    LlmGatewayService::fake([
        'anthropic' => new RuntimeException("Anthropic 400: Upstream rejected request with IC {$rawNric}"),
        'gemini' => new RuntimeException("Gemini 500: Failed processing card payload {$rawCard}"),
    ]);

    $agent = app(AgentService::class);
    $response = $agent->handleUserTurn($session, 'Tolong semak data.');

    expect($response->status)->toBe('failed');

    // 1. Verify ai_runs error_message does not contain raw PII
    $failedRun = AiRun::latest('id')->first();
    expect($failedRun)->not->toBeNull();
    expect($failedRun->status)->toBe('failed');
    expect($failedRun->error_message)->not->toContain($rawCard);
    expect($failedRun->error_message)->toContain('[REDACTED_CREDIT_CARD]');

    // 2. Verify chat_messages metadata does not contain raw PII
    $lastMsg = $session->messages()->latest('id')->first();
    expect($lastMsg)->not->toBeNull();
    expect($lastMsg->metadata)->toHaveKey('error');
    expect($lastMsg->metadata['error'])->not->toContain($rawCard);
    expect($lastMsg->metadata['error'])->toContain('[REDACTED_CREDIT_CARD]');
});
