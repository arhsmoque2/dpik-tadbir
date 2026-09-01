<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\EditProfile;
use App\Filament\Pages\Auth\Register;
use App\Filament\Pages\Dashboard;
use App\Http\Middleware\AutoLoginBypassMiddleware;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $panel = $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->registration(Register::class)
            ->profile(EditProfile::class)
            ->passwordReset();

        // Only register the compiled Vite theme when it's actually been
        // built. PHP-only CI jobs (Gate 2/3) run Pest feature tests that
        // render admin views but never run `pnpm run build`, so
        // Filament\Panel::viteTheme() would throw
        // ViteManifestNotFoundException resolving public/build/manifest.json.
        // Those jobs don't need real compiled CSS — only Gate 4 (Playwright)
        // and the production Docker image build the assets and get the
        // themed panel; everywhere else falls back to Filament's stock theme.
        if (file_exists(public_path('build/manifest.json'))) {
            $panel = $panel->viteTheme('resources/css/filament/admin/theme.css');
        }

        return $panel
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => auth()->check() ? Blade::render('@livewire(\'ai-copilot-drawer\') @include(\'filament.hooks.bottom-nav\')') : ''
            )
            ->renderHook(
                PanelsRenderHook::GLOBAL_SEARCH_AFTER,
                fn (): string => auth()->check() ? Blade::render('@include(\'filament.hooks.copilot-topbar-button\')') : ''
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AutoLoginBypassMiddleware::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
