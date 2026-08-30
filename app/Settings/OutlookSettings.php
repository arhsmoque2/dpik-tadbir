<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class OutlookSettings extends Settings
{
    public string $mcp_command;

    public string $mcp_args;

    public int $timeout_seconds;

    public int $default_lookback_hours;

    public int $default_page_size;

    public static function group(): string
    {
        return 'outlook';
    }
}
