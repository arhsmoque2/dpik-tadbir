<?php

namespace App\Services\Presets;

use App\Models\ExecutivePreset;
use App\Models\User;

class PresetExecutionService
{
    /**
     * Interpolates dynamic prompt templates with context variables.
     *
     * @param  array<string, mixed>  $variables
     */
    public function renderPrompt(ExecutivePreset $preset, array $variables = [], ?User $user = null): string
    {
        $template = $preset->prompt_template;

        $defaults = [
            'user_name' => $user !== null ? $user->name : 'Executive',
            'current_date' => now()->format('Y-m-d'),
            'current_time' => now()->format('H:i'),
        ];

        $merged = array_merge($defaults, $variables);

        foreach ($merged as $key => $val) {
            $template = str_replace('{{'.$key.'}}', (string) $val, $template);
            $template = str_replace('{'.$key.'}', (string) $val, $template);
        }

        return $template;
    }
}
