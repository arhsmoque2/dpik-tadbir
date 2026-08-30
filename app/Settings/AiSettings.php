<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class AiSettings extends Settings
{
    public string $default_provider;

    public string $default_model;

    public string $fallback_provider;

    public string $fallback_model;

    public int $memory_token_ceiling;

    public float $temperature;

    public static function group(): string
    {
        return 'ai';
    }
}
