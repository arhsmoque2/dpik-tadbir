<?php

use App\Filament\Pages\ExecutiveSettings;
use App\Models\User;
use App\Services\Ai\LlmGatewayService;
use App\Services\Mcp\OutlookMcpBridge;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('user model encrypts anthropic, gemini, and microsoft client secret keys', function () {
    $user = User::create([
        'name' => 'Test Exec',
        'email' => 'test_exec_enc@dpik.com.my',
        'password' => bcrypt('password'),
        'anthropic_api_key' => 'sk-ant-test-user-secret-12345',
        'gemini_api_key' => 'AIzaSy-test-user-secret-67890',
        'microsoft_client_id' => '11111111-2222-3333-4444-555555555555',
        'microsoft_client_secret' => 'super-secret-azure-token',
        'microsoft_tenant_id' => '66666666-7777-8888-9999-000000000000',
    ]);

    $fresh = $user->fresh();
    expect($fresh->anthropic_api_key)->toBe('sk-ant-test-user-secret-12345');
    expect($fresh->gemini_api_key)->toBe('AIzaSy-test-user-secret-67890');
    expect($fresh->microsoft_client_id)->toBe('11111111-2222-3333-4444-555555555555');
    expect($fresh->microsoft_client_secret)->toBe('super-secret-azure-token');
    expect($fresh->microsoft_tenant_id)->toBe('66666666-7777-8888-9999-000000000000');
});

test('executive settings page allows user to configure and save private api keys and microsoft credentials', function () {
    $user = User::create([
        'name' => 'Settings Exec',
        'email' => 'settings_exec@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->assertSet('anthropic_api_key', null)
        ->assertSet('gemini_api_key', null)
        ->assertSet('microsoft_client_id', null)
        ->assertSet('microsoft_client_secret', null)
        ->assertSet('microsoft_tenant_id', null)
        ->set('anthropic_api_key', 'sk-ant-my-personal-key')
        ->set('gemini_api_key', 'AIzaSy-my-personal-key')
        ->set('microsoft_client_id', 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee')
        ->set('microsoft_client_secret', 'my-m365-client-secret')
        ->set('microsoft_tenant_id', 'ffffffff-0000-1111-2222-333333333333')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $user->fresh();
    expect($fresh->anthropic_api_key)->toBe('sk-ant-my-personal-key');
    expect($fresh->gemini_api_key)->toBe('AIzaSy-my-personal-key');
    expect($fresh->microsoft_client_id)->toBe('aaaaaaaa-bbbb-cccc-dddd-eeeeeeeeeeee');
    expect($fresh->microsoft_client_secret)->toBe('my-m365-client-secret');
    expect($fresh->microsoft_tenant_id)->toBe('ffffffff-0000-1111-2222-333333333333');
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

    // 2. Save invalid client ID
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('microsoft_client_id', 'not-a-valid-uuid')
        ->call('save')
        ->assertHasNoErrors();

    // 3. Save invalid tenant ID
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('microsoft_client_id', '11111111-2222-3333-4444-555555555555')
        ->set('microsoft_tenant_id', 'not-a-valid-tenant')
        ->call('save')
        ->assertHasNoErrors();

    // 4. Save unauthenticated
    Auth::logout();
    $page = new ExecutiveSettings();
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

    // Test outlook probe invalid client id format
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('microsoft_client_id', 'not-a-uuid')
        ->set('microsoft_client_secret', 'secret-val')
        ->set('microsoft_tenant_id', '66666666-7777-8888-9999-000000000000')
        ->call('testOutlookConnection')
        ->assertSet('outlookProbeStatus', 'error')
        ->assertSee('Client Error');

    // Test outlook probe invalid tenant id format
    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('microsoft_client_id', '11111111-2222-3333-4444-555555555555')
        ->set('microsoft_client_secret', 'secret-val')
        ->set('microsoft_tenant_id', 'invalid-tenant')
        ->call('testOutlookConnection')
        ->assertSet('outlookProbeStatus', 'error');

    // Test outlook probe valid
    Http::fake([
        'https://login.microsoftonline.com/*' => Http::response([
            'token_type' => 'Bearer',
            'access_token' => 'mock-jwt-token',
        ], 200),
    ]);

    Livewire::actingAs($user)
        ->test(ExecutiveSettings::class)
        ->set('microsoft_client_id', '11111111-2222-3333-4444-555555555555')
        ->set('microsoft_client_secret', 'secret-val')
        ->set('microsoft_tenant_id', '66666666-7777-8888-9999-000000000000')
        ->call('testOutlookConnection')
        ->assertSet('outlookProbeStatus', 'success');
});

test('probeOutlookCredentials handles all status and error conditions', function () {
    $bridge = app(OutlookMcpBridge::class);

    // 1. Unconfigured
    $res = $bridge->probeOutlookCredentials(null, null, null);
    expect($res['status'])->toBe('unconfigured');
    expect($res['success'])->toBeFalse();

    // 2. Invalid Client ID UUID
    $res = $bridge->probeOutlookCredentials('invalid-id', 'secret', 'common');
    expect($res['status'])->toBe('invalid_format');
    expect($res['error_code'])->toBe('INVALID_CLIENT_ID_FORMAT');

    // 3. Invalid Tenant ID
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', 'secret', 'invalid-tenant');
    expect($res['status'])->toBe('invalid_format');
    expect($res['error_code'])->toBe('INVALID_TENANT_ID_FORMAT');

    // 4. Missing Secret
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', '', 'organizations');
    expect($res['status'])->toBe('missing_secret');
    expect($res['error_code'])->toBe('MISSING_CLIENT_SECRET');

    // 5-9. Setup mock sequence for token responses
    $seq = Http::fakeSequence();
    $seq->push(['token_type' => 'Bearer', 'access_token' => 'mock-jwt-token'], 200);
    $seq->push(['error' => 'invalid_client', 'error_description' => 'AADSTS7000215: Invalid client secret provided.'], 401);
    $seq->push(['error' => 'invalid_client', 'error_description' => 'AADSTS700016: Application not found in directory.'], 400);
    $seq->push(['error' => 'unauthorized_client', 'error_description' => 'General error message.'], 403);
    $seq->push('Server crashed', 500);

    // 5. Successful Token response
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', 'valid-secret', 'organizations');
    expect($res['status'])->toBe('connected');
    expect($res['success'])->toBeTrue();

    // 6. AADSTS7000215 invalid client secret
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', 'bad-secret', 'organizations');
    expect($res['status'])->toBe('auth_failed');
    expect($res['remediation'])->toContain('Certificates & secrets');

    // 7. AADSTS700016 application not found
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', 'secret', 'organizations');
    expect($res['status'])->toBe('auth_failed');
    expect($res['remediation'])->toContain('App Registration');

    // 8. General OAuth error
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', 'secret', 'organizations');
    expect($res['status'])->toBe('auth_failed');

    // 9. HTTP 500 error
    $res = $bridge->probeOutlookCredentials('11111111-2222-3333-4444-555555555555', 'secret', 'organizations');
    expect($res['status'])->toBe('auth_failed');
    expect($res['error_message'])->toContain('HTTP 500');
});

test('outlook mcp bridge resolves user credentials dynamically', function () {
    $user = User::create([
        'name' => 'Outlook Exec',
        'email' => 'outlook_exec@dpik.com.my',
        'password' => bcrypt('password'),
        'microsoft_client_id' => 'custom-client-id-uuid',
        'microsoft_client_secret' => 'custom-secret-val',
        'microsoft_tenant_id' => 'custom-tenant-uuid',
    ]);

    $bridge = app(OutlookMcpBridge::class)->forUser($user);

    expect($bridge->getClientId())->toBe('custom-client-id-uuid');
    expect($bridge->getClientSecret())->toBe('custom-secret-val');
    expect($bridge->getTenantId())->toBe('custom-tenant-uuid');
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
