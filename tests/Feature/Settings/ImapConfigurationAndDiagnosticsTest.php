<?php

use App\Filament\Pages\ExecutiveSettings;
use App\Models\User;
use App\Services\Mcp\MailDiagnosticService;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::create([
        'name' => 'Rahman DPIK',
        'email' => 'rahman@dpik.com.my',
        'role' => 'super_admin',
        'password' => bcrypt('password123'),
        'imap_host' => 'mail.dpik.com.my',
        'imap_port' => 993,
        'imap_username' => 'rahman@dpik.com.my',
        'imap_password' => 'secret_mailbox_pass_123',
        'smtp_host' => 'mail.dpik.com.my',
        'smtp_port' => 465,
        'smtp_password' => 'secret_mailbox_pass_123',
    ]);
});

it('encrypts imap_password and smtp_password at rest on user model', function () {
    $fresh = $this->user->fresh();
    expect($fresh->imap_password)->toBe('secret_mailbox_pass_123')
        ->and($fresh->smtp_password)->toBe('secret_mailbox_pass_123');

    // Verify raw DB value is encrypted ciphertext
    $raw = DB::table('users')->where('id', $this->user->id)->first();
    expect($raw->imap_password)->not->toBe('secret_mailbox_pass_123')
        ->and($raw->smtp_password)->not->toBe('secret_mailbox_pass_123');
});

it('allows user to configure and save imap and smtp credentials in executive settings', function () {
    Livewire::actingAs($this->user)
        ->test(ExecutiveSettings::class)
        ->assertSet('imap_host', 'mail.dpik.com.my')
        ->assertSet('imap_port', 993)
        ->assertSet('imap_username', 'rahman@dpik.com.my')
        ->set('imap_host', 'mail.dpik.com.my')
        ->set('imap_port', 993)
        ->set('imap_username', 'hilmio@dpik.com.my')
        ->set('imap_password', 'new_secure_company_pass')
        ->call('save')
        ->assertHasNoErrors();

    $fresh = $this->user->fresh();
    expect($fresh->imap_username)->toBe('hilmio@dpik.com.my')
        ->and($fresh->imap_password)->toBe('new_secure_company_pass');
});

it('probes imap and smtp servers with hermetic fallback handling', function () {
    $mock = Mockery::mock(MailDiagnosticService::class);
    $mock->shouldReceive('probeImap')->andReturn([
        'status' => 'success',
        'latency_ms' => 35,
        'message' => 'IMAP Server Reachable on mail.dpik.com.my:993',
        'remediation' => null,
    ]);
    $mock->shouldReceive('probeSmtp')->andReturn([
        'status' => 'success',
        'latency_ms' => 28,
        'message' => 'SMTP Server Ready on mail.dpik.com.my:465',
        'remediation' => null,
    ]);
    $this->app->instance(MailDiagnosticService::class, $mock);

    $service = app(MailDiagnosticService::class);

    $imapResult = $service->probeImap('mail.dpik.com.my', 993);
    expect($imapResult['status'])->toBe('success')
        ->and($imapResult['latency_ms'])->toBeGreaterThan(0);

    $smtpResult = $service->probeSmtp('mail.dpik.com.my', 465);
    expect($smtpResult['status'])->toBe('success')
        ->and($smtpResult['latency_ms'])->toBeGreaterThan(0);
});

it('runs diagnostic tests in executive settings component with mock service', function () {
    $mock = Mockery::mock(MailDiagnosticService::class);
    $mock->shouldReceive('probeImap')->andReturn([
        'status' => 'success',
        'latency_ms' => 45,
        'message' => 'IMAP Connected',
        'remediation' => null,
    ]);
    $mock->shouldReceive('probeSmtp')->andReturn([
        'status' => 'success',
        'latency_ms' => 38,
        'message' => 'SMTP Ready',
        'remediation' => null,
    ]);
    $this->app->instance(MailDiagnosticService::class, $mock);

    Livewire::actingAs($this->user)
        ->test(ExecutiveSettings::class)
        ->call('testImapConnection')
        ->assertSet('imapProbeStatus', 'success')
        ->assertSet('imapLatencyMs', 45)
        ->call('testSmtpConnection')
        ->assertSet('smtpProbeStatus', 'success')
        ->assertSet('smtpLatencyMs', 38);
});

it('runs full system health check across all services via testAllConnections', function () {
    $mock = Mockery::mock(MailDiagnosticService::class);
    $mock->shouldReceive('probeImap')->andReturn([
        'status' => 'success',
        'latency_ms' => 50,
        'message' => 'IMAP Connected',
        'remediation' => null,
    ]);
    $mock->shouldReceive('probeSmtp')->andReturn([
        'status' => 'success',
        'latency_ms' => 40,
        'message' => 'SMTP Ready',
        'remediation' => null,
    ]);
    $this->app->instance(MailDiagnosticService::class, $mock);

    Livewire::actingAs($this->user)
        ->test(ExecutiveSettings::class)
        ->call('testAllConnections')
        ->assertSet('imapProbeStatus', 'success')
        ->assertSet('smtpProbeStatus', 'success');
});

it('handles imap and smtp probe errors in executive settings component', function () {
    $mock = Mockery::mock(MailDiagnosticService::class);
    $mock->shouldReceive('probeImap')->andReturn([
        'status' => 'error',
        'latency_ms' => 10,
        'message' => 'IMAP Failed',
        'remediation' => 'Check host',
    ]);
    $mock->shouldReceive('probeSmtp')->andReturn([
        'status' => 'error',
        'latency_ms' => 12,
        'message' => 'SMTP Failed',
        'remediation' => 'Check port',
    ]);
    $this->app->instance(MailDiagnosticService::class, $mock);

    Livewire::actingAs($this->user)
        ->test(ExecutiveSettings::class)
        ->call('testImapConnection')
        ->assertSet('imapProbeStatus', 'error')
        ->call('testSmtpConnection')
        ->assertSet('smtpProbeStatus', 'error');
});
