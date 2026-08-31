<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ExecutiveAssistant;
use App\Models\ChatSession;
use App\Models\PersonalTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExecutiveAssistantPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_assistant_page_renders_with_auth(): void
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        ChatSession::create([
            'user_id' => $user->id,
            'title' => 'Executive Session Baseline',
            'context_mode' => 'executive',
        ]);

        $this->actingAs($user)
            ->get('/admin/executive-assistant')
            ->assertSuccessful()
            ->assertSee('DPIK TADBIR — Executive Management')
            ->assertSee('Executive AI Sessions')
            ->assertSee('Executive Session Baseline')
            ->assertSee('New Session');
    }

    public function test_start_new_session_and_delete_session(): void
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        Livewire::actingAs($user)
            ->test(ExecutiveAssistant::class)
            ->call('startNewSession')
            ->assertDispatched('open-copilot-drawer');

        $session = ChatSession::where('user_id', $user->id)->first();
        $this->assertNotNull($session);

        Livewire::actingAs($user)
            ->test(ExecutiveAssistant::class)
            ->call('deleteSession', $session->id);

        $this->assertNull(ChatSession::find($session->id));
    }

    public function test_toggle_task_status_updates_task(): void
    {
        $user = User::create([
            'name' => 'Admin Test',
            'email' => 'admin@dpik.com.my',
            'password' => bcrypt('password'),
        ]);

        $task = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Tender Document Review PC-2023-011',
            'project_code' => 'PC-2023-011',
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(ExecutiveAssistant::class)
            ->call('toggleTaskStatus', $task->id);

        $this->assertEquals('completed', $task->fresh()->status);

        Livewire::actingAs($user)
            ->test(ExecutiveAssistant::class)
            ->call('toggleTaskStatus', $task->id);

        $this->assertEquals('pending', $task->fresh()->status);
    }
}

