<?php

use App\Models\User;
use App\Services\Mcp\OutlookMcpBridge;

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
