<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\PersonalTaskResource\Pages\CreatePersonalTask;
use App\Filament\Resources\PersonalTaskResource\Pages\EditPersonalTask;
use App\Filament\Resources\PersonalTaskResource\Pages\ListPersonalTasks;
use App\Models\PersonalTask;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
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
            'role' => 'executive',
        ]);

        $user2 = User::create([
            'name' => 'Executive Two',
            'email' => 'exec2.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
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

    public function test_executive_can_search_and_sort_tasks_table(): void
    {
        $user = User::create([
            'name' => 'Search Task Executive',
            'email' => 'search.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $task1 = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Review Pier 4 Geotechnical Report',
            'status' => 'pending',
            'project_code' => 'PC-2023-011',
        ]);

        $task2 = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Review Pan Borneo Highway Tender',
            'status' => 'completed',
            'project_code' => 'PC-2024-001',
        ]);

        Livewire::actingAs($user)
            ->test(ListPersonalTasks::class)
            ->searchTable('Pier 4')
            ->assertCanSeeTableRecords([$task1])
            ->assertCanNotSeeTableRecords([$task2])
            ->searchTable('PC-2024-001')
            ->assertCanSeeTableRecords([$task2])
            ->assertCanNotSeeTableRecords([$task1]);
    }

    public function test_executive_can_create_task_with_form_validation(): void
    {
        $user = User::create([
            'name' => 'Executive Creator',
            'email' => 'exec.create@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePersonalTask::class)
            ->fillForm([
                'title' => 'Sign JKR Extension Letter',
                'status' => 'in_progress',
                'project_code' => 'PC-2023-011',
                'description' => 'Awaiting final geotechnical annexure.',
                'due_date' => '2026-09-30',
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
            'role' => 'executive',
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
            'role' => 'executive',
        ]);

        $task = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Task To Complete',
            'status' => 'pending',
            'project_code' => 'PC-2023-011',
            'description' => 'Pending signoff',
        ]);

        Livewire::actingAs($user)
            ->test(EditPersonalTask::class, ['record' => $task->id])
            ->fillForm([
                'title' => 'Completed Task Title',
                'status' => 'completed',
                'description' => 'Completed Pier 4 signoff with JKR.',
                'due_date' => '2026-10-15',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('personal_tasks', [
            'id' => $task->id,
            'title' => 'Completed Task Title',
            'status' => 'completed',
            'description' => 'Completed Pier 4 signoff with JKR.',
        ]);
    }

    public function test_executive_can_toggle_task_completion_via_inline_table_action(): void
    {
        $user = User::create([
            'name' => 'Toggle Task Executive',
            'email' => 'toggle.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $task = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Task To Toggle',
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(ListPersonalTasks::class)
            ->callAction(TestAction::make('toggle_complete')->table($task));

        $this->assertDatabaseHas('personal_tasks', [
            'id' => $task->id,
            'status' => 'completed',
        ]);

        Livewire::actingAs($user)
            ->test(ListPersonalTasks::class)
            ->callAction(TestAction::make('toggle_complete')->table($task));

        $this->assertDatabaseHas('personal_tasks', [
            'id' => $task->id,
            'status' => 'pending',
        ]);
    }

    public function test_executive_can_filter_tasks_by_status(): void
    {
        $user = User::create([
            'name' => 'Filter Task Executive',
            'email' => 'filter.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $pendingTask = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Pending Geotechnical Review',
            'status' => 'pending',
        ]);

        $completedTask = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Completed Pier 4 Inspection',
            'status' => 'completed',
        ]);

        Livewire::actingAs($user)
            ->test(ListPersonalTasks::class)
            ->filterTable('status', 'completed')
            ->assertCanSeeTableRecords([$completedTask])
            ->assertCanNotSeeTableRecords([$pendingTask])
            ->filterTable('status', 'pending')
            ->assertCanSeeTableRecords([$pendingTask])
            ->assertCanNotSeeTableRecords([$completedTask]);
    }

    public function test_executive_can_delete_task_via_action(): void
    {
        $user = User::create([
            'name' => 'Task Deleter',
            'email' => 'delete.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $task = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Task To Delete',
            'status' => 'pending',
        ]);

        Livewire::actingAs($user)
            ->test(EditPersonalTask::class, ['record' => $task->id])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('personal_tasks', [
            'id' => $task->id,
        ]);
    }

    public function test_unauthenticated_guest_is_redirected_from_personal_tasks_pages(): void
    {
        config(['auth.enabled' => true]);

        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing.task@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $task = PersonalTask::create([
            'user_id' => $user->id,
            'title' => 'Protected Task',
            'status' => 'pending',
        ]);

        $this->get('/admin/personal-tasks')->assertRedirect('/admin/login');
        $this->get('/admin/personal-tasks/create')->assertRedirect('/admin/login');
        $this->get("/admin/personal-tasks/{$task->id}/edit")->assertRedirect('/admin/login');
    }
}
