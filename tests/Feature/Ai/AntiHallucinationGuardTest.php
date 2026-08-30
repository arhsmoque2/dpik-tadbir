<?php

use App\DTOs\AiTurnResponse;
use App\Models\AiActionReceipt;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Ai\AntiHallucinationGuard;

test('anti hallucination guard flags unverified dispatched actions', function () {
    $user = User::create([
        'name' => 'Guard User',
        'email' => 'guard@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Triage Session',
    ]);

    $guard = new AntiHallucinationGuard;

    // 1. Normal conversational response -> valid
    $normalResponse = new AiTurnResponse(
        content: 'Good morning, here is your summary.',
        status: 'completed'
    );
    expect($guard->validateTurnResponse($normalResponse, $session))->toBeTrue();

    // 2. Response claims dispatch without receipt -> invalid
    $falseClaimResponse = new AiTurnResponse(
        content: 'I have sent the email to the client right now.',
        status: 'completed'
    );
    expect($guard->validateTurnResponse($falseClaimResponse, $session))->toBeFalse();

    // 3. Response claims dispatch WITH recent executed receipt -> valid
    AiActionReceipt::create([
        'user_id' => $user->id,
        'action_type' => 'outlook_reply',
        'description' => 'Sent reply',
        'status' => 'executed',
    ]);

    expect($guard->validateTurnResponse($falseClaimResponse, $session))->toBeTrue();
});
