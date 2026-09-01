<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Pages\ExecutiveSettings;
use App\Models\User;
use App\Services\Ai\AiConfigurationService;
use App\Services\Mcp\MailDiagnosticService;
use Livewire\Livewire;
use Mockery;

beforeEach(function () {
    $this->superAdmin = User::create([
        'name' => 'Super Admin',
        'email' => 'admin@dpik.com.my',
        'role' => 'super_admin',
        'password' => bcrypt('password'),
        'imap_host' => 'mail.dpik.com.my',
        'imap_port' => 993,
        'imap_username' => 'admin@dpik.com.my',
        'imap_password' => 'secret',
        'smtp_host' => 'mail.dpik.com.my',
        'smtp_port' => 465,
        'smtp_password' => 'secret',
    ]);
});

it('formats valid raw ai configuration json via action', function () {
    $rawUnformatted = '{"key":"value","nested":{"count":1}}';

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->set('rawAiConfigJson', $rawUnformatted)
        ->call('formatAiConfigJson')
        ->assertSet('configError', null);
});

it('handles invalid json when calling formatAiConfigJson', function () {
    $invalidJson = '{"key": incomplete json';

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->set('rawAiConfigJson', $invalidJson)
        ->call('formatAiConfigJson')
        ->assertNotSet('configError', null);
});

it('saves valid ai configuration json successfully', function () {
    $validJson = json_encode(['system_prompt' => ['base_template' => 'Hello {executive_name}']]);

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->set('rawAiConfigJson', $validJson)
        ->call('saveAiConfiguration')
        ->assertSet('configError', null);
});

it('handles error when saving invalid ai configuration json', function () {
    $invalidJson = 'not json at all';

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->set('rawAiConfigJson', $invalidJson)
        ->call('saveAiConfiguration')
        ->assertNotSet('configError', null);
});

it('resets ai configuration to defaults successfully', function () {
    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->call('resetAiConfiguration')
        ->assertSet('configError', null);
});

it('handles exception when resetAiConfiguration fails', function () {
    $mockConfigService = Mockery::mock(AiConfigurationService::class);
    $mockConfigService->shouldReceive('getRawJson')->andReturn('{}');
    $mockConfigService->shouldReceive('resetToDefaults')->andThrow(new \RuntimeException('Storage permission denied'));
    $this->app->instance(AiConfigurationService::class, $mockConfigService);

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->call('resetAiConfiguration')
        ->assertSet('configError', 'Storage permission denied');
});

it('handles exception when mount fails to retrieve raw ai config', function () {
    $mockConfigService = Mockery::mock(AiConfigurationService::class);
    $mockConfigService->shouldReceive('getRawJson')->andThrow(new \RuntimeException('Corrupted config file'));
    $this->app->instance(AiConfigurationService::class, $mockConfigService);

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->assertSet('rawAiConfigJson', '{}');
});

it('runs testAllConnections including outlook when client id is set', function () {
    $mockMail = Mockery::mock(MailDiagnosticService::class);
    $mockMail->shouldReceive('probeImap')->andReturn(['status' => 'success', 'latency_ms' => 10, 'message' => 'OK', 'remediation' => null]);
    $mockMail->shouldReceive('probeSmtp')->andReturn(['status' => 'success', 'latency_ms' => 10, 'message' => 'OK', 'remediation' => null]);
    $this->app->instance(MailDiagnosticService::class, $mockMail);

    Livewire::actingAs($this->superAdmin)
        ->test(ExecutiveSettings::class)
        ->set('microsoft_client_id', 'azure-client-id-123')
        ->call('testAllConnections')
        ->assertSet('imapProbeStatus', 'success')
        ->assertSet('smtpProbeStatus', 'success');
});
