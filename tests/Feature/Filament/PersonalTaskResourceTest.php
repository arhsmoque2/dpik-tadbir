<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\PersonalTaskResource\Pages\CreatePersonalTask;
use App\Filament\Resources\PersonalTaskResource\Pages\EditPersonalTask;
use App\Filament\Resources\PersonalTaskResource\Pages\ListPersonalTasks;
use App\Models\PersonalTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalTaskResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_can_render_tasks_table_with_sovereign_isolation(): void
    {
        $user1 = User::create([
            'name' => 'Executive One',
            'email' => 'exec1.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $user2 = User::create([
            'name' => 'Executive Two',
            'email' => 'exec2.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $task1 = PersonalTask::create([
            'user_id' => $user1->id,
            'title' => 'Review Pier 4 Geotechnical Report',
            'status' => 'pending',
            'project_code' => 'PC-2023-011',
        ]);

        $task2 = PersonalTask::create([
            'user_id' => $user2->id,
            'title' => 'Review Pan Borneo Highway Tender',
            'status' => 'pending',
            'project_code' => 'PC-2024-001',
        ]);

        Livewire::actingAs($user1)
            ->test(ListPersonalTasks::class)
            ->assertCanSeeTableRecords([$task1])
            ->assertCanNotSeeTableRecords([$task2])
            ->assertSee('Review Pier 4 Geotechnical Report');
    }

    public function test_executive_can_create_task_with_form_validation(): void
    {
        $user = User::create([
            'name' => 'Executive Creator',
            'email' => 'exec.create@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePersonalTask::class)
            ->fillForm([
                'title' => 'Sign JKR Extension Letter',
                'status' => 'in_progress',
                'project_code' => 'PC-2023-011',
                'description' => 'Awaiting final geotechnical annexure.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('personal_tasks', [
            'user_id' => $user->id,
            'title' => 'Sign JKR Extension Letter',
            'status' => 'in_progress',
            'project_code' => 'PC-2023-011',
        ]);
    }

    public function test_task_creation_validates_required_fields(): void
    {
        $user = User::create([
            'name' => 'Executive Validator',
            'email' => 'exec.val@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePersonalTask::class)
            ->fillForm([
                'title' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_executive_can_edit_and_complete_task(): void
    {
        $user = User::create([
            'name' => 'Executive Editor',
            'email' => 'exec.edit@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $task = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Task To Complete',
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(EditPersonalTask::class, ['record' => $task->id])
            ->fillForm([
                'status' => 'completed',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('personal_tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);
    }
}
