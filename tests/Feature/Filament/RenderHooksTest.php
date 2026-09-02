<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenderHooksTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $panel = Filament::getPanel('admin');
        $panel->boot();
        Filament::setCurrentPanel($panel);
    }

    public function test_authenticated_executive_renders_copilot_drawer_and_bottom_nav_on_body_end(): void
    {
        $user = User::create([
            'name' => 'Test Executive',
            'email' => 'executive.hook@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($user);

        $output = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);

        $this->assertStringContainsString('Floating Primary Navigation', $output);
        $this->assertNotEmpty($output);
    }

    public function test_authenticated_executive_renders_copilot_topbar_trigger_on_global_search_after(): void
    {
        $user = User::create([
            'name' => 'Test Executive 2',
            'email' => 'executive2.hook@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $this->actingAs($user);

        $output = (string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER);

        $this->assertStringContainsString('data-copilot-trigger', $output);
        $this->assertStringContainsString('AI Copilot', $output);
    }

    public function test_unauthenticated_visitor_gets_empty_copilot_and_bottom_nav_hooks(): void
    {
        $bodyEnd = (string) FilamentView::renderHook(PanelsRenderHook::BODY_END);
        $this->assertSame('', trim($bodyEnd));

        $globalSearchAfter = (string) FilamentView::renderHook(PanelsRenderHook::GLOBAL_SEARCH_AFTER);
        $this->assertSame('', trim($globalSearchAfter));
    }

    public function test_auth_surfaces_render_google_sso_button_hooks(): void
    {
        $loginFormHook = (string) FilamentView::renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER);
        $this->assertStringContainsString('Sign in with Google', $loginFormHook);

        $registerFormHook = (string) FilamentView::renderHook(PanelsRenderHook::AUTH_REGISTER_FORM_AFTER);
        $this->assertStringContainsString('Sign in with Google', $registerFormHook);
    }
}
