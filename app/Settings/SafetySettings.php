<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class SafetySettings extends Settings
{
    public bool $require_human_confirmation_for_email_send;

    public bool $require_human_confirmation_for_email_forward;

    public bool $enable_anti_hallucination_guard;

    public int $approval_token_ttl_minutes;

    public static function group(): string
    {
        return 'safety';
    }
}
