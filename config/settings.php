<?php

use App\Settings\AiSettings;
use App\Settings\GeneralSettings;
use App\Settings\OutlookSettings;
use App\Settings\SafetySettings;
use Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository;

return [
    'settings' => [
        GeneralSettings::class,
        AiSettings::class,
        OutlookSettings::class,
        SafetySettings::class,
    ],

    'repositories' => [
        'database' => [
            'type' => DatabaseSettingsRepository::class,
            'model' => null,
            'table' => null,
            'connection' => null,
        ],
    ],

    'default_repository' => 'database',
    'encoder' => null,
    'decoder' => null,
    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', true),
        'store' => null,
        'prefix' => null,
        'ttl' => null,
    ],
];
