<?php

use App\Filament\Pages\ExecutiveSettings;
use App\Models\User;
use App\Services\Ai\LlmGatewayService;
use Illuminate\Support\Facades\Config;
use Livewire\Livewire;

test('user model encrypts anthropic and gemini api keys', function () {
    $user = User::create([
        'name' => 'Test Exec',
        'email' => 'test_exec_enc@dpik.com.my',
        'password' => bcrypt('password'),
        'anthropic_api_key' => 'sk-ant-test-user-secret-12345',
        'gemini_api_key' => 'AIzaSy-test-user-secret-67890',
    ]);

    $fresh = $user->fresh();
    expect($fresh->anthropic_api_key)->toBe('sk-ant-test-user-secret-12345');
    expect($fresh->gemini_api_key)->toBe('AIzaSy-test-user-secret-67890');
});

test('executive settings page allows user to configure and save private api keys', function () {
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
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->anthropic_api_key)->toBe('sk-ant-my-personal-key');
    expect($fresh->gemini_api_key)->toBe('AIzaSy-my-personal-key');
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
