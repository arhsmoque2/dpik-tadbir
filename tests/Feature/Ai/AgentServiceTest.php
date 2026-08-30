<?php

use App\Models\ChatSession;
use App\Models\User;
use App\Services\Ai\AgentService;

test('agent service handles user turn and suspends on interactive proposals', function () {
    $user = User::create([
        'name' => 'MD Executive',
        'email' => 'md@dpik.com.my',
        'password' => bcrypt('secret'),
    ]);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Draft Review Session',
    ]);

    $agent = app(AgentService::class);

    $turn = $agent->handleUserTurn($session, 'Please draft a reply to JKR Sarawak');

    expect($turn->isSuspended())->toBeTrue();
    expect($turn->suspendedToolCall['name'])->toBe('propose_action_card');
    expect($turn->suspendedToolCall['suspension_payload']['approval_token'])->toStartWith('act_tok_');
});
