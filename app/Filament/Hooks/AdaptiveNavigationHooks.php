<?php

declare(strict_types=1);

namespace App\Filament\Hooks;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

final class AdaptiveNavigationHooks
{
    public static function register(Panel $panel): Panel
    {
        return $panel->renderHook(
            PanelsRenderHook::BODY_END,
            fn (): string => auth()->check() ? Blade::render("@include('filament.hooks.bottom-nav')") : ''
        );
    }
}
