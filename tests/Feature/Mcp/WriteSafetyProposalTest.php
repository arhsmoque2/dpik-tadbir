<?php

use App\Mcp\Tools\Interactive\ProposeActionCardTool;
use App\Mcp\Tools\Outlook\OutlookForwardTool;
use App\Mcp\Tools\Outlook\OutlookReplyTool;
use App\Models\User;
use App\Services\Ai\ActionApprovalService;
use Illuminate\Auth\Access\AuthorizationException;

test('outlook reply fails closed when approval token is missing', function () {
    $tool = app(OutlookReplyTool::class);

    $tool->handle([
        'message_id' => 'msg_123',
        'body' => 'Approved response',
    ]);
})->throws(AuthorizationException::class);

test('outlook reply fails closed when approval token is invalid', function () {
    $tool = app(OutlookReplyTool::class);

    $tool->handle([
        'message_id' => 'msg_123',
        'body' => 'Approved response',
        'approval_token' => 'forged_or_invalid_token',
    ]);
})->throws(AuthorizationException::class);

test('outlook reply succeeds with valid approval token and logs action receipt', function () {
    $user = User::create([
        'name' => 'Write Safety Tester',
        'email' => 'writesafety@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $token = app(ActionApprovalService::class)->issue('outlook_reply', [], $user);
    $tool = app(OutlookReplyTool::class);

    $res = $tool->handle([
        'message_id' => 'msg_123',
        'body' => 'Approved reply',
        'approval_token' => $token,
    ]);

    expect($res['status'])->toBe('sent');
    expect($res['success'])->toBeTrue();

    $receipt = \App\Models\AiActionReceipt::where('user_id', $user->id)
        ->where('action_type', 'outlook_reply')
        ->first();
    expect($receipt)->not->toBeNull()
        ->and($receipt->status)->toBe('executed')
        ->and($receipt->approval_token)->toBe($token);
});

test('outlook reply rejects a token already consumed once', function () {
    $user = User::create([
        'name' => 'Write Safety Replay Tester',
        'email' => 'writesafety-replay@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $token = app(ActionApprovalService::class)->issue('outlook_reply', [], $user);
    $tool = app(OutlookReplyTool::class);

    $tool->handle(['message_id' => 'msg_123', 'body' => 'First send', 'approval_token' => $token]);

    expect(fn () => $tool->handle(['message_id' => 'msg_123', 'body' => 'Replayed send', 'approval_token' => $token]))
        ->toThrow(AuthorizationException::class);
});

test('outlook forward fails closed without approval token', function () {
    $tool = app(OutlookForwardTool::class);

    $tool->handle([
        'message_id' => 'msg_123',
        'to_recipients' => ['test@example.com'],
    ]);
})->throws(AuthorizationException::class);

test('propose action card tool refuses to issue a token outside an authenticated session', function () {
    $tool = app(ProposeActionCardTool::class);

    $tool->handle([
        'action_type' => 'outlook_reply',
        'title' => 'Unauthenticated proposal',
        'summary' => 'Should never be reachable outside a real session',
        'payload' => [],
    ]);
})->throws(RuntimeException::class, 'Cannot propose an action card outside an authenticated executive session.');
