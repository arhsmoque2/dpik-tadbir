import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Builds the custom Filament admin panel theme (dark "Warm Obsidian-Slate"
// design per docs/UI.md) so hand-authored Tailwind classes in the Blade
// views and Filament PHP classes actually compile to real CSS. Before this
// config existed, `vite`/`tailwindcss`/`laravel-vite-plugin` were installed
// as devDependencies but never wired to anything — no vite.config, no
// AdminPanelProvider::viteTheme() call — so only Filament's own pre-bundled
// dist CSS ever shipped, and every custom `bg-[#212631]`-style class in the
// app's own Blade templates rendered as plain unstyled HTML.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
