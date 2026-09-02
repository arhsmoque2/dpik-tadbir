<?php

declare(strict_types=1);

namespace App\Filament\Hooks;

use Filament\Panel;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

final class GoogleAuthHooks
{
    public static function register(Panel $panel): Panel
    {
        return $panel
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn (): string => Blade::render("@include('filament.components.google-login-button')")
            )
            ->renderHook(
                PanelsRenderHook::AUTH_REGISTER_FORM_AFTER,
                fn (): string => Blade::render("@include('filament.components.google-login-button')")
            );
    }
}
