<?php

use App\Filament\Pages\ExecutiveSettings;
use App\Models\ChatSession;
use App\Models\User;
use App\Services\Ai\AgentService;
use App\Services\Ai\AiConfigurationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->superAdmin = User::create([
        'name' => 'Super Admin',
        'email' => 'smoque@gmail.com',
        'role' => 'super_admin',
        'password' => bcrypt('password123'),
    ]);

    app(AiConfigurationService::class)->resetToDefaults();
});

it('provides rich factory defaults for AI system prompt, rules, tuning, and MCP', function () {
    $service = app(AiConfigurationService::class);
    $config = $service->getConfiguration();

    expect($config)->toHaveKeys(['system_prompt', 'ai_tuning', 'memory_settings', 'mcp_servers', 'tools'])
        ->and($config['ai_tuning']['temperature'])->toBe(0.2)
        ->and(count($config['system_prompt']['rules']))->toBe(8)
        ->and($config['mcp_servers']['imap']['host'])->toBe('mail.dpik.com.my');
});

it('persists and hot-reloads customized JSON configuration', function () {
    $service = app(AiConfigurationService::class);
    $custom = $service->getConfiguration();
    $custom['ai_tuning']['temperature'] = 0.7;
    $custom['system_prompt']['rules'][] = 'Custom DPIK rule: Always mention ISO 9001 compliance.';

    $service->saveConfiguration($custom);

    $reloaded = $service->getConfiguration();
    expect($reloaded['ai_tuning']['temperature'])->toBe(0.7)
        ->and(count($reloaded['system_prompt']['rules']))->toBe(9);
});

it('rejects malformed raw JSON strings with an exception', function () {
    $service = app(AiConfigurationService::class);

    expect(fn () => $service->saveRawJson('{ invalid json string ...'))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects raw JSON that does not parse to an object/array', function () {
    $service = app(AiConfigurationService::class);

    expect(fn () => $service->saveRawJson('"just a string"'))
        ->toThrow(InvalidArgumentException::class, 'Configuration must be a valid JSON object.');
});

it('falls back to factory defaults if settings table throws an exception', function () {
    Cache::flush();
    DB::shouldReceive('table')
        ->with('settings')
        ->andThrow(new RuntimeException('Table does not exist'));

    $service = app(AiConfigurationService::class);
    $config = $service->getConfiguration();

    expect($config)->toHaveKey('system_prompt');
});

it('falls back to default system prompt template in AgentService when base_template is empty', function () {
    $service = app(AiConfigurationService::class);
    $custom = $service->getConfiguration();
    $custom['system_prompt']['base_template'] = '';
    $service->saveConfiguration($custom);

    $agent = app(AgentService::class);
    $user = User::create([
        'name' => 'Fallback User',
        'email' => 'fallback_user@dpik.com.my',
        'password' => bcrypt('secret'),
    ]);
    $session = ChatSession::create(['user_id' => $user->id, 'title' => 'Test Fallback']);

    $turn = $agent->handleUserTurn($session, 'Hello');
    expect($turn->status)->toBe('completed');
});

it('allows super admins to view, format, and save AI JSON configuration on executive settings page', function () {
    $service = app(AiConfigurationService::class);
    $initialJson = $service->getRawJson();

    $modifiedArray = json_decode($initialJson, true);
    $modifiedArray['ai_tuning']['temperature'] = 0.5;
    $modifiedJson = json_encode($modifiedArray);

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->assertSet('rawAiConfigJson', $initialJson)
        ->set('rawAiConfigJson', $modifiedJson)
        ->call('saveAiConfiguration')
        ->assertHasNoErrors();

    $saved = $service->getConfiguration();
    expect($saved['ai_tuning']['temperature'])->toBe(0.5);
});

it('allows super admin to reset configuration back to defaults in UI', function () {
    $service = app(AiConfigurationService::class);
    $custom = $service->getConfiguration();
    $custom['ai_tuning']['temperature'] = 0.99;
    $service->saveConfiguration($custom);

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->call('resetAiConfiguration')
        ->assertHasNoErrors();

    $fresh = $service->getConfiguration();
    expect($fresh['ai_tuning']['temperature'])->toBe(0.2);
});
