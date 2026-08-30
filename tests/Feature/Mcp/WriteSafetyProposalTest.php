<?php

use App\Mcp\Tools\Outlook\OutlookForwardTool;
use App\Mcp\Tools\Outlook\OutlookReplyTool;
use App\Services\Mcp\OutlookMcpBridge;
use Illuminate\Auth\Access\AuthorizationException;

test('outlook reply fails closed when approval token is missing', function () {
    $bridge = app(OutlookMcpBridge::class);
    $tool = new OutlookReplyTool($bridge);

    $tool->handle([
        'message_id' => 'msg_123',
        'body' => 'Approved response',
    ]);
})->throws(AuthorizationException::class);

test('outlook reply fails closed when approval token is invalid', function () {
    $bridge = app(OutlookMcpBridge::class);
    $tool = new OutlookReplyTool($bridge);

    $tool->handle([
        'message_id' => 'msg_123',
        'body' => 'Approved response',
        'approval_token' => 'forged_or_invalid_token',
    ]);
})->throws(AuthorizationException::class);

test('outlook reply succeeds with valid approval token', function () {
    $bridge = app(OutlookMcpBridge::class);
    $tool = new OutlookReplyTool($bridge);

    $res = $tool->handle([
        'message_id' => 'msg_123',
        'body' => 'Approved response',
        'approval_token' => 'act_tok_valid_token_string_12345',
    ]);

    expect($res['status'])->toBe('sent');
    expect($res['success'])->toBeTrue();
});

test('outlook forward fails closed without approval token', function () {
    $bridge = app(OutlookMcpBridge::class);
    $tool = new OutlookForwardTool($bridge);

    $tool->handle([
        'message_id' => 'msg_123',
        'to_recipients' => ['test@example.com'],
    ]);
})->throws(AuthorizationException::class);
