<?php

use App\Services\Ai\PiiDetector;

test('pii detector detects and redacts Malaysian NRIC and secrets', function () {
    $detector = new PiiDetector;

    $textWithNric = 'Sila semak dokumen untuk IC 850712-14-5543 bagi tender PC-2023-011.';
    expect($detector->hasPii($textWithNric))->toBeTrue();
    $findings = $detector->detect($textWithNric);
    expect($findings)->toHaveKey('nric_formatted');
    expect($findings['nric_formatted'])->toContain('850712-14-5543');

    $redacted = $detector->redact($textWithNric);
    expect($redacted)->toContain('[REDACTED_NRIC]');
    expect($redacted)->not->toContain('850712-14-5543');
});

test('pii detector detects secret tokens and phone numbers', function () {
    $detector = new PiiDetector;

    $textWithSecrets = 'Kunci API adalah sk-abcdef1234567890abcdef123 dan telefon 012-3456789.';
    expect($detector->hasPii($textWithSecrets))->toBeTrue();

    $redacted = $detector->redact($textWithSecrets);
    expect($redacted)->toContain('[REDACTED_SECRET]');
    expect($redacted)->toContain('[REDACTED_PHONE]');
    expect($redacted)->not->toContain('sk-abcdef1234567890abcdef123');
    expect($redacted)->not->toContain('012-3456789');
});
