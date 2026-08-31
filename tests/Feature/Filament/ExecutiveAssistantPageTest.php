<?php

namespace Tests\Feature\Filament;

use App\Filament\Pages\ExecutiveAssistant;
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

        $this->actingAs($user)
            ->get('/admin/executive-assistant')
            ->assertSuccessful()
            ->assertSee('DPIK Executive Copilot')
            ->assertSee('Tugas Command Center')
            ->assertSee('DPIK Tugas — Active Action Registry');
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
