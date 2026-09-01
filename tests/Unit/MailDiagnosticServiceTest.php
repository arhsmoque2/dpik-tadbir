<?php

use App\Services\Mcp\MailDiagnosticService;

it('handles imap socket failure gracefully on invalid host or closed port', function () {
    $service = new MailDiagnosticService;
    $result = $service->probeImap('127.0.0.1', 19999, 'user', 'pass', 1);

    expect($result['status'])->toBe('error')
        ->and($result['latency_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['remediation'])->not->toBeNull();
});

it('handles smtp socket failure gracefully on invalid host or closed port', function () {
    $service = new MailDiagnosticService;
    $result = $service->probeSmtp('127.0.0.1', 19998, 'user', 'pass', 1);

    expect($result['status'])->toBe('error')
        ->and($result['latency_ms'])->toBeGreaterThanOrEqual(0)
        ->and($result['remediation'])->not->toBeNull();
});

it('handles imap probe without credentials returning ssl greeting', function () {
    $service = new MailDiagnosticService;
    $result = $service->probeImap('mail.dpik.com.my', 993, null, null, 4);

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toContain('IMAP Server Reachable');
});

it('handles smtp probe without credentials returning ready', function () {
    $service = new MailDiagnosticService;
    $result = $service->probeSmtp('mail.dpik.com.my', 465, null, null, 4);

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toContain('SMTP Server Ready');
});
