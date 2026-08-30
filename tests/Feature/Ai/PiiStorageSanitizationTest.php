<?php

use App\Models\AiRun;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Ai\AgentService;

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
    $rawSecret = 'sk-live-1234567890abcdef1234567890';
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
