<?php

use App\Models\User;
use App\Services\Mcp\OutlookMcpBridge;
use Illuminate\Support\Facades\Config;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Abdul Rahman Hilmi',
        'email' => 'abdulrahman@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
    ]);
});

test('bridge executes tools and returns mock or live responses', function () {
    $bridge = app(OutlookMcpBridge::class)->forUser($this->user);

    $auth = $bridge->checkAuthStatus();
    expect($auth)->toBeTrue();

    $delta = $bridge->fetchInboxDelta(lookbackHours: 12, limit: 10);
    expect($delta)->toHaveKey('messages');

    $search = $bridge->searchMail('FT264');
    expect($search)->toHaveKey('messages');

    $read = $bridge->readMessage('msg_001');
    expect($read)->toHaveKey('subject');

    $draft = $bridge->createDraft(
        subject: 'Draft Subject',
        body: 'Draft content',
        toRecipients: ['client@domain.com']
    );
    expect($draft)->toHaveKey('status', 'draft_created');

    $reply = $bridge->sendReply('msg_001', 'Reply body');
    expect($reply)->toBeTrue();

    $forward = $bridge->forwardMessage('msg_001', ['director@dpik.com.my'], 'FYI');
    expect($forward)->toBeTrue();
});

test('bridge executes tools in simulated production mode and falls back gracefully', function () {
    app()['env'] = 'production';
    Config::set('services.outlook_mcp.command', 'non_existent_mcp_command_xyz');

    $this->user->update([
        'microsoft_client_id' => '11111111-2222-3333-4444-555555555555',
        'microsoft_client_secret' => 'mock-secret',
        'microsoft_tenant_id' => 'organizations',
    ]);

    $bridge = app(OutlookMcpBridge::class)->forUser($this->user);
    $res = $bridge->callTool('outlook_auth_status');

    expect($res)->toHaveKey('status', 'unavailable');
    app()['env'] = 'testing';
});

test('bridge never leaks raw shell/process output into the executive-facing error', function () {
    // Regression test for the deployed "Outlook MCP bridge error: sh: 1: exec:
    // uv: not found" leak — a missing binary must fail closed with a clean
    // message, not the shell's raw stderr, in the chat transcript.
    app()['env'] = 'production';
    Config::set('services.outlook_mcp.command', 'non_existent_mcp_command_xyz');

    $bridge = app(OutlookMcpBridge::class)->forUser($this->user);
    $res = $bridge->callTool('outlook_auth_status');

    expect($res)->toHaveKey('status', 'unavailable');
    expect($res['error'])
        ->not->toContain('sh:')
        ->not->toContain('exec:')
        ->not->toContain('not found')
        ->not->toContain('Traceback')
        ->not->toContain('Stack trace');

    app()['env'] = 'testing';
});
