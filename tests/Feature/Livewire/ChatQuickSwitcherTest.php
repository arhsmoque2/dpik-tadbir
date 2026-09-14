<?php

use App\Livewire\Chat\ChatQuickSwitcher;
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

// @capability(chat.quick-switcher)
test('quick switcher opens, closes, and toggles', function () {
    Livewire::actingAs($this->user)
        ->test(ChatQuickSwitcher::class)
        ->assertSet('isOpen', false)
        ->call('open')
        ->assertSet('isOpen', true)
        ->call('close')
        ->assertSet('isOpen', false)
        ->call('toggle')
        ->assertSet('isOpen', true)
        ->dispatch('open-quick-switcher')
        ->assertSet('isOpen', true);
});

test('quick switcher filters user sessions and enforces sovereign isolation', function () {
    ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Tender Valuation 2026',
        'context_mode' => 'executive',
    ]);

    ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Board Meeting Briefing',
        'context_mode' => 'executive',
    ]);

    ChatSession::create([
        'user_id' => $this->otherUser->id,
        'title' => 'Confidential Foreign Audit',
        'context_mode' => 'executive',
    ]);

    Livewire::actingAs($this->user)
        ->test(ChatQuickSwitcher::class)
        ->set('search', 'Tender')
        ->assertSee('Tender Valuation 2026')
        ->assertDontSee('Board Meeting Briefing')
        ->assertDontSee('Confidential Foreign Audit')
        ->set('search', 'Confidential')
        ->assertDontSee('Confidential Foreign Audit');
});

test('quick switcher selects session and dispatches drawer open event', function () {
    $session = ChatSession::create([
        'user_id' => $this->user->id,
        'title' => 'Target Session',
        'context_mode' => 'executive',
    ]);

    Livewire::actingAs($this->user)
        ->test(ChatQuickSwitcher::class)
        ->call('open')
        ->call('selectSession', $session->id)
        ->assertSet('isOpen', false)
        ->assertDispatched('open-copilot-drawer', sessionId: $session->id);
});

test('quick switcher creates new session and dispatches events', function () {
    Livewire::actingAs($this->user)
        ->test(ChatQuickSwitcher::class)
        ->call('open')
        ->call('createNewSession')
        ->assertSet('isOpen', false)
        ->assertDispatched('open-copilot-drawer')
        ->assertDispatched('chat-sessions-updated')
        ->assertDispatched('refresh-sidebar');

    expect(ChatSession::where('user_id', $this->user->id)->count())->toBe(1);
});

test('quick switcher toggle resets search when closing', function () {
    Livewire::actingAs($this->user)
        ->test(ChatQuickSwitcher::class)
        ->call('open')
        ->set('search', 'something')
        ->call('toggle')
        ->assertSet('isOpen', false)
        ->assertSet('search', '');
});

test('quick switcher handles unauthenticated user gracefully', function () {
    Livewire::test(ChatQuickSwitcher::class)
        ->call('selectSession', 1)
        ->call('createNewSession');

    expect(true)->toBeTrue();
});
