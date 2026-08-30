<?php

use App\Mcp\Tools\Interactive\AskUserQuestionTool;
use App\Mcp\Tools\Interactive\ProposeActionCardTool;
use App\Mcp\Tools\Memory\CommitProjectRegisterTool;
use App\Mcp\Tools\Memory\QueryProjectRegisterTool;
use App\Mcp\Tools\Notes\CreatePersonalNoteTool;
use App\Mcp\Tools\Notes\CreatePersonalTaskTool;
use App\Mcp\Tools\Outlook\OutlookCreateDraftTool;
use App\Mcp\Tools\Outlook\OutlookForwardTool;
use App\Mcp\Tools\Outlook\OutlookListInboxDeltaTool;
use App\Mcp\Tools\Outlook\OutlookReadMessageTool;
use App\Mcp\Tools\Outlook\OutlookReplyTool;
use App\Mcp\Tools\Outlook\OutlookSearchMailTool;
use App\Models\User;
use App\Services\Mcp\OutlookMcpBridge;
use App\Services\Memory\MemoryRetrievalService;

test('all MCP tools return valid schemas and execute expected methods', function () {
    $user = User::create([
        'name' => 'MD User',
        'email' => 'md@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    test()->actingAs($user);

    $askTool = new AskUserQuestionTool;
    expect($askTool->schema())->toHaveKey('properties');
    $askRes = $askTool->handle(['question' => 'Which project?', 'options' => ['A', 'B']]);
    expect($askRes['status'])->toBe('suspended');

    $propTool = new ProposeActionCardTool;
    expect($propTool->schema())->toHaveKey('properties');
    $propRes = $propTool->handle([
        'action_type' => 'outlook_reply',
        'title' => 'Reply Proposal',
        'summary' => 'Reply to client',
        'payload' => ['subject' => 'Test'],
    ]);
    expect($propRes['status'])->toBe('suspended');
    expect($propRes['approval_token'])->not->toBeEmpty();

    $commitTool = new CommitProjectRegisterTool;
    expect($commitTool->schema())->toHaveKey('properties');
    $commitRes = $commitTool->handle([
        'project_code' => 'PC-2023-011',
        'project_title' => 'Sungai Udang',
        'content' => 'Milestone reached',
        'decision_markers' => ['dm:decision'],
    ]);
    expect($commitRes['status'])->toBe('committed');

    $queryTool = new QueryProjectRegisterTool(app(MemoryRetrievalService::class));
    expect($queryTool->schema())->toHaveKey('properties');
    $queryRes = $queryTool->handle(['query' => 'Sungai Udang']);
    expect($queryRes['results'])->toBeArray();

    $noteTool = new CreatePersonalNoteTool;
    expect($noteTool->schema())->toHaveKey('properties');
    $noteRes = $noteTool->handle(['title' => 'My Note', 'content' => 'Note body']);
    expect($noteRes['status'])->toBe('created');

    $taskTool = new CreatePersonalTaskTool;
    expect($taskTool->schema())->toHaveKey('properties');
    $taskRes = $taskTool->handle(['title' => 'My Task', 'priority' => 'high']);
    expect($taskRes['status'])->toBe('created');

    $bridge = new OutlookMcpBridge;
    $draftTool = new OutlookCreateDraftTool($bridge);
    expect($draftTool->schema())->toHaveKey('properties');
    $draftRes = $draftTool->handle(['subject' => 'Draft Subj', 'body' => 'Body', 'to_recipients' => ['test@example.com']]);
    expect($draftRes['status'])->toBe('staged');

    $forwardTool = new OutlookForwardTool($bridge);
    expect($forwardTool->schema())->toHaveKey('properties');

    $deltaTool = new OutlookListInboxDeltaTool($bridge);
    expect($deltaTool->schema())->toHaveKey('properties');

    $readTool = new OutlookReadMessageTool($bridge);
    expect($readTool->schema())->toHaveKey('properties');

    $replyTool = new OutlookReplyTool($bridge);
    expect($replyTool->schema())->toHaveKey('properties');

    $searchTool = new OutlookSearchMailTool($bridge);
    expect($searchTool->schema())->toHaveKey('properties');
});

test('outlook mcp bridge provides fluent methods for user', function () {
    $user = User::create([
        'name' => 'MD User 2',
        'email' => 'md2@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $bridge = new OutlookMcpBridge;
    $bridge->forUser($user);

    expect($bridge->checkAuthStatus())->toBeBool();
    expect($bridge->callTool('outlook_search_mail', ['query' => 'test']))->toBeArray();
    expect($bridge->fetchInboxDelta(24, 10, true))->toBeArray();
    expect($bridge->searchMail('test', 10, true))->toBeArray();
    expect($bridge->readMessage('msg_123', true))->toBeArray();
    expect($bridge->createDraft('Subj', 'Body', ['a@b.com']))->toBeArray();
    expect($bridge->sendReply('msg_123', 'Reply text'))->toBeBool();
    expect($bridge->forwardMessage('msg_123', ['a@b.com'], 'Fwd comment'))->toBeBool();
});
