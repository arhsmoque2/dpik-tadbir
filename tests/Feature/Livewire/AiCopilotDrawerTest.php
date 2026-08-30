<?php

use App\Livewire\AiCopilotDrawer;
use App\Models\AiRun;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ExecutivePreset;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Abdul Rahman Hilmi',
        'email' => 'abdulrahman@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
    ]);
});

test('copilot drawer mounts and creates active session', function () {
    Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class)
        ->assertSet('isOpen', false)
        ->assertSet('outlookStatus', 'online')
        ->assertNotSet('activeSessionId', null);

    expect(ChatSession::where('user_id', $this->user->id)->count())->toBe(1);
});

test('copilot drawer toggles open and closed state', function () {
    Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class)
        ->call('toggleDrawer')
        ->assertSet('isOpen', true)
        ->call('closeDrawer')
        ->assertSet('isOpen', false)
        ->dispatch('open-copilot-drawer')
        ->assertSet('isOpen', true);
});

test('copilot drawer sends message and handles agent response', function () {
    $component = Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class)
        ->set('inputPrompt', 'Check status of project PC-2023-011')
        ->call('sendMessage')
        ->assertSet('isProcessing', false)
        ->assertSet('inputPrompt', '');

    $session = ChatSession::where('user_id', $this->user->id)->first();
    expect($session)->not->toBeNull();
    expect(ChatMessage::where('chat_session_id', $session->id)->where('role', 'user')->count())->toBe(1);
    expect(ChatMessage::where('chat_session_id', $session->id)->where('role', 'assistant')->count())->toBe(1);
});

test('copilot drawer runs preset and renders template', function () {
    $preset = ExecutivePreset::create([
        'user_id' => $this->user->id,
        'title' => 'Today Delta',
        'prompt_template' => 'Please scan recent correspondence for {{user_name}}.',
        'category' => 'email',
        'is_active' => true,
        'sort_order' => 1,
    ]);

    Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class)
        ->call('runPreset', $preset->id)
        ->assertSet('isOpen', true);

    $session = ChatSession::where('user_id', $this->user->id)->first();
    $lastUserMsg = ChatMessage::where('chat_session_id', $session->id)->where('role', 'user')->latest('id')->first();

    expect($lastUserMsg)->not->toBeNull();
    expect($lastUserMsg->content)->toContain($this->user->name);
});

test('copilot drawer handles ask-copilot-about event', function () {
    Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class)
        ->dispatch('ask-copilot-about', subject: 'PC-2023-011', context: 'Interim Claim 4')
        ->assertSet('isOpen', true);

    $session = ChatSession::where('user_id', $this->user->id)->first();
    $lastUserMsg = ChatMessage::where('chat_session_id', $session->id)->where('role', 'user')->latest('id')->first();

    expect($lastUserMsg)->not->toBeNull();
    expect($lastUserMsg->content)->toContain('PC-2023-011');
});

test('copilot drawer handles action card suspension and approval', function () {
    $component = Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class);

    // Simulate suspended action card
    $component->set('suspendedToolCall', [
        'id' => 'call_act_001',
        'name' => 'propose_action_card',
        'arguments' => [
            'action_type' => 'outlook_reply',
            'title' => 'Dispatch Confirmation',
            'summary' => 'Confirm reply to JKR',
            'payload' => ['to' => 'jkr@gov.my', 'subject' => 'Re: Claim', 'body' => 'Approved.'],
        ],
        'suspension_payload' => [
            'status' => 'suspended',
            'approval_token' => 'act_tok_sample_123',
            'card' => [
                'action_type' => 'outlook_reply',
                'title' => 'Dispatch Confirmation',
                'summary' => 'Confirm reply to JKR',
                'payload' => ['to' => 'jkr@gov.my'],
            ],
        ],
    ]);

    $component->call('approveActionCard', 'act_tok_sample_123')
        ->assertSet('isProcessing', false);
});

test('copilot drawer handles choice question suspension and submission', function () {
    $component = Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class);

    // Simulate suspended choice question
    $component->set('suspendedToolCall', [
        'id' => 'call_ask_001',
        'name' => 'ask_user_question',
        'arguments' => [
            'question' => 'Select priority level',
            'options' => ['High', 'Normal', 'Low'],
            'is_multi_select' => false,
        ],
        'suspension_payload' => [
            'status' => 'suspended',
        ],
    ]);

    $component->set('choiceSelection', 'High')
        ->set('choiceNotes', 'Expedite please')
        ->call('submitChoice')
        ->assertSet('isProcessing', false)
        ->assertSet('choiceSelection', '');
});

test('copilot drawer handles choice skip', function () {
    $component = Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class);

    $component->set('suspendedToolCall', [
        'id' => 'call_ask_002',
        'name' => 'ask_user_question',
        'arguments' => ['question' => 'Skip me?', 'options' => ['Yes', 'No']],
        'suspension_payload' => ['status' => 'suspended'],
    ]);

    $component->call('skipChoice')
        ->assertSet('isProcessing', false);
});

test('copilot drawer creates new session and switches session', function () {
    $component = Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class);

    $initialSessionId = $component->get('activeSessionId');

    $component->call('newSession');
    $newSessionId = $component->get('activeSessionId');

    expect($newSessionId)->not->toBe($initialSessionId);

    $component->call('switchSession', $initialSessionId);
    expect($component->get('activeSessionId'))->toBe($initialSessionId);
});

test('copilot drawer supports runtime 3-favorites model swapping and passes active model to turns', function () {
    $this->user->update([
        'favorite_model_1' => 'anthropic:claude-3-7-sonnet-20250219',
        'favorite_model_2' => 'openrouter:deepseek/deepseek-r1',
        'favorite_model_3' => 'gemini:gemini-2.5-flash',
    ]);

    $component = Livewire::actingAs($this->user)
        ->test(AiCopilotDrawer::class)
        ->assertSet('activeProvider', 'anthropic')
        ->assertSet('activeModel', 'claude-3-7-sonnet-20250219')
        ->assertSee('Anthropic · Claude 3.7 Sonnet')
        // Toggle popover
        ->call('toggleModelSwapper')
        ->assertSet('isModelSwapperOpen', true)
        // Select slot 2 (OpenRouter DeepSeek R1)
        ->call('selectModel', 'openrouter:deepseek/deepseek-r1')
        ->assertSet('activeProvider', 'openrouter')
        ->assertSet('activeModel', 'deepseek/deepseek-r1')
        ->assertSet('isModelSwapperOpen', false)
        ->assertSee('OpenRouter · DeepSeek R1');

    // Send turn with swapped model
    $component->set('inputPrompt', 'Deep mathematical breakdown of project variance')
        ->call('sendMessage')
        ->assertSet('isProcessing', false);

    // Verify AiRun records openrouter provider and deepseek model
    $latestRun = AiRun::latest('id')->first();
    expect($latestRun)->not->toBeNull();
    expect($latestRun->provider)->toBe('openrouter');
    expect($latestRun->model)->toBe('deepseek/deepseek-r1');
});

test('copilot drawer parses various model tuple formats and renders correct labels', function () {
    $component = Livewire::actingAs($this->user)->test(AiCopilotDrawer::class);

    // slash formats
    $component->call('selectModel', 'anthropic/claude-3.7-sonnet');
    expect($component->get('activeProvider'))->toBe('anthropic');
    expect($component->get('activeModel'))->toBe('claude-3-7-sonnet-20250219');
    expect($component->instance()->getActiveModelBadgeLabel())->toBe('Anthropic · Claude 3.7 Sonnet');

    $component->call('selectModel', 'google/gemini-2.5-pro');
    expect($component->get('activeProvider'))->toBe('gemini');
    expect($component->get('activeModel'))->toBe('gemini-2.5-pro');
    expect($component->instance()->getActiveModelBadgeLabel())->toBe('Google · Gemini 2.5 Pro');

    $component->call('selectModel', 'openrouter/deepseek/deepseek-r1');
    expect($component->get('activeProvider'))->toBe('openrouter');
    expect($component->get('activeModel'))->toBe('deepseek/deepseek-r1');

    $component->call('selectModel', 'meta-llama/llama-3.3-70b-instruct');
    expect($component->get('activeProvider'))->toBe('openrouter');

    $component->call('selectModel', 'custom-raw-model');
    expect($component->get('activeProvider'))->toBe('anthropic');
    expect($component->get('activeModel'))->toBe('custom-raw-model');

    // custom provider badge label
    $component->set('activeProvider', 'custom-llm');
    expect($component->instance()->getActiveModelBadgeLabel())->toContain('Custom-llm');
});

test('copilot drawer responds to copilot-model-changed event', function () {
    $component = Livewire::actingAs($this->user)->test(AiCopilotDrawer::class);

    $component->dispatch('copilot-model-changed', provider: 'gemini', model: 'gemini-2.5-flash');
    expect($component->get('activeProvider'))->toBe('gemini');
    expect($component->get('activeModel'))->toBe('gemini-2.5-flash');

    // dispatched without args re-initializes from user
    $component->dispatch('copilot-model-changed');
    expect($component->get('activeProvider'))->toBe('anthropic');
});
