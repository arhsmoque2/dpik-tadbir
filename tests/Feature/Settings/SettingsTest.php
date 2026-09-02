<?php

use App\Settings\AiSettings;
use App\Settings\GeneralSettings;
use App\Settings\SafetySettings;

test('all settings classes define expected groups and default properties', function () {
    expect(AiSettings::group())->toBe('ai');
    expect(GeneralSettings::group())->toBe('general');
    expect(SafetySettings::group())->toBe('safety');
});
