<?php

use App\Models\User;
use App\Services\Mail\MailBridge;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Abdul Rahman Hilmi',
        'email' => 'abdulrahman@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
    ]);
});

test('bridge executes tools and returns mock responses in the testing environment', function () {
    $bridge = app(MailBridge::class)->forUser($this->user);

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

test('bridge fails closed when the executive has no mailbox credentials configured', function () {
    // No imap_username/imap_password on the user and no fallback in config —
    // this is the real, un-mocked path (production mode), unlike the tests
    // above which run under the testing-env mock shortcut.
    app()['env'] = 'production';

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->fetchInboxDelta();

    expect($res)->toHaveKey('status', 'unavailable');
    expect($res['error'])->toBeString();

    app()['env'] = 'testing';
});

// @capability(chat.no-raw-backend-errors)
test('bridge never leaks raw IMAP/PHP internals into the executive-facing error', function () {
    // Regression test carried over from the deployed "Outlook MCP bridge
    // error: sh: 1: exec: uv: not found" leak (issue #40 / #41): a mail
    // bridge failure must fail closed with a clean message, not raw
    // exception/stack-trace text, in the chat transcript — regardless of
    // which transport (subprocess, then; IMAP/SMTP, now) is behind it.
    app()['env'] = 'production';

    $this->user->update([
        'imap_host' => '127.0.0.1',
        'imap_port' => 9, // the "discard" port — reliably closed, fails fast
        'imap_username' => $this->user->email,
        'imap_password' => 'wrong-password',
    ]);

    $bridge = app(MailBridge::class)->forUser($this->user);
    $res = $bridge->fetchInboxDelta();

    expect($res)->toHaveKey('status', 'unavailable');
    expect($res['error'])
        ->not->toContain('Fatal error:')
        ->not->toContain('Stack trace:')
        ->not->toContain('.php on line')
        ->not->toContain('Traceback');

    app()['env'] = 'testing';
});

test('mail bridge provides fluent methods for user', function () {
    $user = User::create([
        'name' => 'MD User 2',
        'email' => 'md2@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $bridge = new MailBridge;
    $bridge->forUser($user);

    expect($bridge->checkAuthStatus())->toBeBool();
    expect($bridge->fetchInboxDelta(24, 10, true))->toBeArray();
    expect($bridge->searchMail('test', 10, true))->toBeArray();
    expect($bridge->readMessage('msg_123', true))->toBeArray();
    expect($bridge->createDraft('Subj', 'Body', ['a@b.com']))->toBeArray();
    expect($bridge->sendReply('msg_123', 'Reply text'))->toBeBool();
    expect($bridge->forwardMessage('msg_123', ['a@b.com'], 'Fwd comment'))->toBeBool();
});
