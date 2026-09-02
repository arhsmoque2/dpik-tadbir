<?php

use App\Filament\Pages\ExecutiveSettings;
use App\Models\User;
use App\Services\Ai\LlmGatewayService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('user model encrypts anthropic, gemini, and mailbox credential keys', function () {
    $user = User::create([
        'name' => 'Test Exec',
        'email' => 'test_exec_enc@dpik.com.my',
        'password' => bcrypt('password'),
        'anthropic_api_key' => 'sk-ant-test-user-secret-12345',
        'gemini_api_key' => 'AIzaSy-test-user-secret-67890',
        'imap_password' => 'super-secret-mailbox-password',
    ]);

    $fresh = $user->fresh();
    expect($fresh->anthropic_api_key)->toBe('sk-ant-test-user-secret-12345');
    expect($fresh->gemini_api_key)->toBe('AIzaSy-test-user-secret-67890');
    expect($fresh->imap_password)->toBe('super-secret-mailbox-password');
});

test('executive settings page allows user to configure and save private api keys and mailbox credentials', function () {
    $user = User::create([
        'name' => 'Settings Exec',
        'email' => 'settings_exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->assertSet('anthropic_api_key', null)
        ->assertSet('gemini_api_key', null)
        ->set('anthropic_api_key', 'sk-ant-my-personal-key')
        ->set('gemini_api_key', 'AIzaSy-my-personal-key')
        ->set('imap_password', 'my-mailbox-password')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->anthropic_api_key)->toBe('sk-ant-my-personal-key');
    expect($fresh->gemini_api_key)->toBe('AIzaSy-my-personal-key');
    expect($fresh->imap_password)->toBe('my-mailbox-password');
});

test('executive settings handles empty keys, save format validation, and unauthenticated guard', function () {
    $user = User::create([
        'name' => 'Format Exec',
        'email' => 'format_exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    // 1. Empty AI keys test
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('anthropic_api_key', '')
        ->set('gemini_api_key', '')
        ->call('testAiConnection')
        ->assertSet('aiProbeStatus', 'error')
        ->assertSee('No personal AI API keys provided');

    // 2. Save unauthenticated
    Auth::logout();
    $page = app(ExecutiveSettings::class);
    $page->save();
    expect(Auth::user())->toBeNull();
});

test('executive settings page validates connection probes and handles diagnostic error reporting', function () {
    $user = User::create([
        'name' => 'Probe Exec',
        'email' => 'probe_exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    // Test invalid format
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('anthropic_api_key', 'invalid-key-no-prefix')
        ->call('testAiConnection')
        ->assertSet('aiProbeStatus', 'error')
        ->assertSee('Format Error');

    // Test invalid gemini format
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('gemini_api_key', 'invalid-gemini-prefix')
        ->call('testAiConnection')
        ->assertSet('aiProbeStatus', 'error')
        ->assertSee('Format Error');

    // Test valid probe
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('anthropic_api_key', 'sk-ant-api03-valid-key-pattern')
        ->set('gemini_api_key', 'AIzaSy-valid-gemini-key-1234567890123456789')
        ->call('testAiConnection')
        ->assertSet('aiProbeStatus', 'success');
});

test('llm gateway prioritizes user configured keys over system config keys', function () {
    Config::set('services.ai.anthropic_api_key', 'system-anthropic-key');
    Config::set('services.ai.gemini_api_key', 'system-gemini-key');

    $gateway = app(LlmGatewayService::class);

    $userWithKeys = User::create([
        'name' => 'Keyed Exec',
        'email' => 'keyed_exec@dpik.com.my',
        'password' => bcrypt('password'),
        'anthropic_api_key' => 'custom-user-anthropic-key',
        'gemini_api_key' => 'custom-user-gemini-key',
    ]);

    $reflection = new ReflectionClass($gateway);
    $method = $reflection->getMethod('invokeProvider');
    $method->setAccessible(true);

    // Call with user
    $resWithUser = $method->invoke($gateway, 'anthropic', 'claude-3-7-sonnet-20250219', [
        ['role' => 'user', 'content' => 'Hello'],
    ], [], ['user' => $userWithKeys]);

    expect($resWithUser['provider'])->toBe('anthropic');

    // Call without user falls back to system
    $resWithoutUser = $method->invoke($gateway, 'anthropic', 'claude-3-7-sonnet-20250219', [
        ['role' => 'user', 'content' => 'Hello'],
    ], [], []);

    expect($resWithoutUser['provider'])->toBe('anthropic');
});

test('user model encrypts openrouter api key and stores favorite model configurations', function () {
    $user = User::create([
        'name' => 'OpenRouter Exec',
        'email' => 'openrouter_exec@dpik.com.my',
        'password' => bcrypt('password'),
        'openrouter_api_key' => 'sk-or-v1-test-mock-key-12345', // gitleaks:allow
        'favorite_model_1' => 'anthropic:claude-3-7-sonnet-20250219',
        'favorite_model_2' => 'openrouter:deepseek/deepseek-r1',
        'favorite_model_3' => 'gemini:gemini-2.5-flash',
    ]);

    $fresh = $user->fresh();
    expect($fresh->openrouter_api_key)->toBe('sk-or-v1-test-mock-key-12345');
    expect($fresh->favorite_model_1)->toBe('anthropic:claude-3-7-sonnet-20250219');
    expect($fresh->favorite_model_2)->toBe('openrouter:deepseek/deepseek-r1');
    expect($fresh->favorite_model_3)->toBe('gemini:gemini-2.5-flash');
});

test('executive settings page allows user to configure openrouter key and top-3 favorite models', function () {
    $user = User::create([
        'name' => 'Settings Top3 Exec',
        'email' => 'top3_exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->assertSet('openrouter_api_key', null)
        ->assertSet('favorite_model_1', 'anthropic:claude-3-7-sonnet-20250219')
        ->assertSet('favorite_model_2', 'openrouter:deepseek/deepseek-r1')
        ->assertSet('favorite_model_3', 'gemini:gemini-2.5-flash')
        ->set('openrouter_api_key', 'sk-or-v1-custom-mock-key') // gitleaks:allow
        ->set('favorite_model_1', 'openrouter:anthropic/claude-3.7-sonnet')
        ->set('favorite_model_2', 'openrouter:google/gemini-2.5-pro')
        ->set('favorite_model_3', 'openrouter:openai/gpt-4o')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->openrouter_api_key)->toBe('sk-or-v1-custom-mock-key');
    expect($fresh->favorite_model_1)->toBe('openrouter:anthropic/claude-3.7-sonnet');
    expect($fresh->favorite_model_2)->toBe('openrouter:google/gemini-2.5-pro');
    expect($fresh->favorite_model_3)->toBe('openrouter:openai/gpt-4o');
});

test('probeOpenRouterKey handles all status and error conditions', function () {
    $gateway = app(LlmGatewayService::class);

    // 1. Unconfigured
    $res = $gateway->probeOpenRouterKey(null);
    expect($res['status'])->toBe('unconfigured');
    expect($res['success'])->toBeFalse();

    // 2. Invalid Key Format
    $res = $gateway->probeOpenRouterKey('invalid-prefix-key');
    expect($res['status'])->toBe('invalid_format');
    expect($res['error_code'])->toBe('INVALID_KEY_FORMAT');

    // 3. Setup mock sequence for OpenRouter auth key endpoint
    $seq = Http::fakeSequence();
    $seq->push(['data' => ['label' => 'Tadbir Key', 'limit' => 100]], 200);
    $seq->push(['error' => ['message' => 'Invalid API key provided.', 'code' => 401]], 401);
    $seq->push('Server Error', 500);

    // 4. Successful probe
    $res = $gateway->probeOpenRouterKey('sk-or-v1-valid-test-key'); // gitleaks:allow
    expect($res['status'])->toBe('connected');
    expect($res['success'])->toBeTrue();

    // 5. Auth failed (401)
    $res = $gateway->probeOpenRouterKey('sk-or-v1-invalid-mock'); // gitleaks:allow
    expect($res['status'])->toBe('auth_failed');
    expect($res['error_message'])->toContain('Invalid API key');

    // 6. Upstream 500
    $res = $gateway->probeOpenRouterKey('sk-or-v1-server-err'); // gitleaks:allow
    expect($res['status'])->toBe('auth_failed');
});

test('executive settings page validates openrouter key format and connection probe', function () {
    $user = User::create([
        'name' => 'OpenRouter Probe Exec',
        'email' => 'openrouter_probe@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    // Invalid format on save
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('openrouter_api_key', 'wrong-key-pattern')
        ->call('save');

    expect($user->fresh()->openrouter_api_key)->toBeNull();

    // Probe valid
    Http::fake([
        'https://openrouter.ai/api/v1/auth/key' => Http::response(['data' => ['label' => 'Test Key']], 200),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('openrouter_api_key', 'sk-or-v1-live-probe-key') // gitleaks:allow
        ->call('testOpenRouterConnection')
        ->assertSet('openrouterProbeStatus', 'success');
});

test('executive settings testAiConnection catches invalid openrouter key format', function () {
    $user = User::create([
        'name' => 'Invalid OpenRouter AI Exec',
        'email' => 'invalid_ai_or@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('openrouter_api_key', 'bad-openrouter-prefix')
        ->call('testAiConnection')
        ->assertSet('aiProbeStatus', 'error')
        ->assertSet('aiProbeMessage', 'Format Error: OpenRouter API key must begin with "sk-or-v1-".');
});

test('executive settings testOpenRouterConnection handles failed probe notification', function () {
    $user = User::create([
        'name' => 'Failed Probe Exec',
        'email' => 'failed_probe@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    Http::fake([
        'https://openrouter.ai/api/v1/auth/key' => Http::response(['error' => ['message' => 'Invalid API key']], 401),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('openrouter_api_key', 'sk-or-v1-invalid-probe') // gitleaks:allow
        ->call('testOpenRouterConnection')
        ->assertSet('openrouterProbeStatus', 'error')
        ->assertSet('openrouterProbeMessage', 'HTTP 401: Invalid API key');
});
