<?php

declare(strict_types=1);

namespace App\Filament\Hooks;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

final class CopilotUiHooks
{
    public static function register(Panel $panel): Panel
    {
        return $panel
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? Blade::render("@livewire('ai-copilot-drawer')") : ''
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => auth()->check() ? Blade::render("@include('filament.hooks.copilot-topbar-button')") : ''
            );
    }
}
