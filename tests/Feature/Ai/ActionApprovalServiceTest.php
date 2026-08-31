<?php

use App\Models\User;
use App\Services\Ai\ActionApprovalService;

test('action approval service issues, consumes, and single-uses a token bound to action type and user', function () {
    $user = User::create([
        'name' => 'Approval Tester',
        'email' => 'approval_tester@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $service = app(ActionApprovalService::class);
    $token = $service->issue('outlook_reply', ['subject' => 'Test'], $user);

    expect($token)->not->toBeEmpty();
    expect($service->consume($token, 'outlook_reply', $user))->toBeTrue();
    // Single-use: the same token fails the second time.
    expect($service->consume($token, 'outlook_reply', $user))->toBeFalse();
});

test('action approval service rejects a token for the wrong action type', function () {
    $user = User::create([
        'name' => 'Wrong Action Tester',
        'email' => 'wrong_action@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $service = app(ActionApprovalService::class);
    $token = $service->issue('outlook_forward', [], $user);

    expect($service->consume($token, 'outlook_reply', $user))->toBeFalse();
});

test('action approval service rejects a token for a different user', function () {
    $issuer = User::create([
        'name' => 'Issuer',
        'email' => 'issuer@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    $other = User::create([
        'name' => 'Other Executive',
        'email' => 'other_exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $service = app(ActionApprovalService::class);
    $token = $service->issue('outlook_reply', [], $issuer);

    expect($service->consume($token, 'outlook_reply', $other))->toBeFalse();
});

test('action approval service rejects an empty token', function () {
    $user = User::create([
        'name' => 'Empty Token Tester',
        'email' => 'empty_token@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $service = app(ActionApprovalService::class);

    expect($service->consume('', 'outlook_reply', $user))->toBeFalse();
});
