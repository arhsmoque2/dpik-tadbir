<?php

use App\Livewire\Chat\ChatSidebarNav;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Abdul Rahman Hilmi',
        'email' => 'abdulrahman@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
    ]);

    $this->otherUser = User::create([
        'name' => 'Other Executive',
        'email' => 'other.exec@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'executive',
    ]);
});

// @capability(chat.sidebar-history)
test('sidebar nav mounts and lists authenticated user sessions', function () {
    $session1 = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Tender Analysis A',
        'context_mode' => 'executive',
    ]);

    $session2 = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Contract Review B',
        'context_mode' => 'executive',
    ]);

    $otherSession = ChatSession::create([
        'user_id' => $this->otherUser->id,
        'title' => 'Private Other User Session',
        'context_mode' => 'executive',
    ]);

    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->assertSee('Tender Analysis A')
        ->assertSee('Contract Review B')
        ->assertDontSee('Private Other User Session');
});

test('sidebar nav selects session and dispatches drawer open event', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Project Alpha Sync',
        'context_mode' => 'executive',
    ]);

    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->call('selectSession', $session->id)
        ->assertSet('activeSessionId', $session->id)
        ->assertDispatched('open-copilot-drawer', sessionId: $session->id);
});

test('sidebar nav creates new session and dispatches drawer open and refresh events', function () {
    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->call('createNewSession')
        ->assertDispatched('open-copilot-drawer')
        ->assertDispatched('chat-sessions-updated')
        ->assertDispatched('refresh-sidebar');

    expect(ChatSession::where('user_id', $this->user->id)->count())->toBe(1);
});

test('sidebar nav starts and saves session rename', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Initial Title',
        'context_mode' => 'executive',
    ]);

    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->call('startRenaming', $session->id)
        ->assertSet('renamingSessionId', $session->id)
        ->assertSet('renameTitle', 'Initial Title')
        ->set('renameTitle', 'Updated Project Title')
        ->call('saveRename')
        ->assertSet('renamingSessionId', null)
        ->assertDispatched('chat-sessions-updated')
        ->assertDispatched('refresh-sidebar');

    expect($session->fresh()->title)->toBe('Updated Project Title');
});

test('sidebar nav deletes session and its associated messages', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Session To Delete',
        'context_mode' => 'executive',
    ]);

    ChatMessage::create([
        'chat_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello test message',
    ]);

    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->call('deleteSession', $session->id)
        ->assertDispatched('chat-sessions-updated')
        ->assertDispatched('refresh-sidebar');

    expect(ChatSession::find($session->id))->toBeNull();
    expect(ChatMessage::where('chat_session_id', $session->id)->count())->toBe(0);
});

test('sidebar nav enforces sovereign user isolation on rename and delete', function () {
    $foreignSession = ChatSession::create([
        'user_id' => $this->otherUser->id,
        'title' => 'Foreign Session',
        'context_mode' => 'executive',
    ]);

    // Attacking user tries to rename other user's session
    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->call('startRenaming', $foreignSession->id)
        ->assertSet('renamingSessionId', null)
        ->set('renamingSessionId', $foreignSession->id)
        ->set('renameTitle', 'Hacked Title')
        ->call('saveRename');

    expect($foreignSession->fresh()->title)->toBe('Foreign Session');

    // Attacking user tries to delete other user's session
    Livewire::actingAs($this->user)
        ->test(ChatSidebarNav::class)
        ->call('deleteSession', $foreignSession->id);

    expect(ChatSession::find($foreignSession->id))->not->toBeNull();
});
