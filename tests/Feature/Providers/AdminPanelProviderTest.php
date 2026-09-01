<?php

use App\Providers\Filament\AdminPanelProvider;
use Filament\Panel;

// Covers the manifest-existence guard around ->viteTheme() added to fix
// ViteManifestNotFoundException in PHP-only CI jobs (Gate 2/3) that render
// admin views but never run `pnpm run build`. See the provider for context.
test('admin panel skips the vite theme when no build manifest exists', function () {
    $manifest = public_path('build/manifest.json');
    expect($manifest)->not->toBeFile();

    $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

    expect($panel->getViteTheme())->toBeNull();
});

test('admin panel registers the vite theme when a build manifest exists', function () {
    $buildDir = public_path('build');
    $manifest = "{$buildDir}/manifest.json";

    if (! is_dir($buildDir)) {
        mkdir($buildDir, 0755, true);
    }
    file_put_contents($manifest, json_encode([
        'resources/css/filament/admin/theme.css' => ['file' => 'assets/theme-fake.css'],
    ]));

    try {
        $panel = (new AdminPanelProvider(app()))->panel(Panel::make());

        expect($panel->getViteTheme())->toBe('resources/css/filament/admin/theme.css');
    } finally {
        unlink($manifest);
        @rmdir($buildDir);
    }
});
