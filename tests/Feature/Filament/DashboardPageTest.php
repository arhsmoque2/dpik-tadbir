<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\Dashboard;
use App\Models\ChatSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_page_renders_agreed_simplified_layout_with_auth(): void
    {
        $user = User::create([
            'name' => 'Executive Tester',
            'email' => 'exec@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Tender Analysis PC-2023-011',
            'context_mode' => 'executive',
        ]);

        $this->actingAs($user)
            ->get('/admin')
            ->assertSuccessful()
            ->assertSee('DPIK TADBIR — Executive Management')
            ->assertSee('Executive AI Sessions')
            ->assertSee('Tender Analysis PC-2023-011')
            ->assertSee('New Session')
            ->assertSee('Notes')
            ->assertSee('Projects')
            ->assertSee('Settings');
    }

    public function test_dashboard_start_new_session_and_delete_session(): void
    {
        $user = User::create([
            'name' => 'Executive Tester',
            'email' => 'exec@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('startNewSession')
            ->assertDispatched('open-copilot-drawer');

        $session = ChatSession::where('user_id', $user->id)->first();
        $this->assertNotNull($session);

        Livewire::actingAs($user)
            ->test(Dashboard::class)
            ->call('deleteSession', $session->id);

        $this->assertNull(ChatSession::find($session->id));
    }
}
