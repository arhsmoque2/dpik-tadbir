<?php

use App\Models\ChatSession;
use App\Models\User;
use App\Policies\ChatSessionPolicy;

test('user can access their own chat sessions', function () {
    $user1 = User::create([
        'name' => 'User One',
        'email' => 'user1@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $session = ChatSession::create([
        'user_id' => $user1->id,
        'title' => 'Morning Inbox Triage',
    ]);

    $policy = new ChatSessionPolicy;

    expect($policy->view($user1, $session))->toBeTrue();
    expect($policy->update($user1, $session))->toBeTrue();
    expect($policy->delete($user1, $session))->toBeTrue();
});

test('user cannot view another user chat session', function () {
    $user1 = User::create([
        'name' => 'User One',
        'email' => 'user1@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $user2 = User::create([
        'name' => 'User Two',
        'email' => 'user2@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $session = ChatSession::create([
        'user_id' => $user1->id,
        'title' => 'Private Session',
    ]);

    $policy = new ChatSessionPolicy;

    expect($policy->view($user2, $session))->toBeFalse();
    expect($policy->update($user2, $session))->toBeFalse();
    expect($policy->delete($user2, $session))->toBeFalse();
});
