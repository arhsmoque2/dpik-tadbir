<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\PersonalNoteResource\Pages\CreatePersonalNote;
use App\Filament\Resources\PersonalNoteResource\Pages\EditPersonalNote;
use App\Filament\Resources\PersonalNoteResource\Pages\ListPersonalNotes;
use App\Models\PersonalNote;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PersonalNoteResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_executive_can_render_notes_table_with_sovereign_isolation(): void
    {
        $user1 = User::create([
            'name' => 'Note Owner 1',
            'email' => 'note1@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $user2 = User::create([
            'name' => 'Note Owner 2',
            'email' => 'note2@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $note1 = PersonalNote::create([
            'user_id' => $user1->id,
            'title' => 'Executive Meeting Key Decisions',
            'content' => '<p>Approved variation order RM 120k.</p>',
            'project_code' => 'PC-2023-011',
            'tags' => ['meeting', 'finance'],
        ]);

        $note2 = PersonalNote::create([
            'user_id' => $user2->id,
            'title' => 'Confidential Partner Review',
            'content' => '<p>Partner audit notes.</p>',
            'project_code' => 'PC-2024-001',
            'tags' => ['audit'],
        ]);

        Livewire::actingAs($user1)
            ->test(ListPersonalNotes::class)
            ->assertCanSeeTableRecords([$note1])
            ->assertCanNotSeeTableRecords([$note2])
            ->assertSee('Executive Meeting Key Decisions');
    }

    public function test_executive_can_search_and_filter_notes_table(): void
    {
        $user = User::create([
            'name' => 'Search Executive',
            'email' => 'search.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $note1 = PersonalNote::create([
            'user_id' => $user->id,
            'title' => 'Bintulu Port Geotechnical Summary',
            'content' => '<p>Soil testing completed by Ir. Tan.</p>',
            'project_code' => 'PC-2023-011',
        ]);

        $note2 = PersonalNote::create([
            'user_id' => $user->id,
            'title' => 'Kuching Treatment Plant Audit',
            'content' => '<p>Water quality parameters verified.</p>',
            'project_code' => 'PC-2024-002',
        ]);

        Livewire::actingAs($user)
            ->test(ListPersonalNotes::class)
            ->searchTable('Bintulu')
            ->assertCanSeeTableRecords([$note1])
            ->assertCanNotSeeTableRecords([$note2])
            ->searchTable('PC-2024-002')
            ->assertCanSeeTableRecords([$note2])
            ->assertCanNotSeeTableRecords([$note1]);
    }

    public function test_executive_can_create_note_with_validation(): void
    {
        $user = User::create([
            'name' => 'Note Creator',
            'email' => 'create.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePersonalNote::class)
            ->fillForm([
                'title' => 'Bintulu Port Geotechnical Summary',
                'content' => '<p>Soil testing completed by Ir. Tan.</p>',
                'project_code' => 'PC-2023-011',
                'tags' => ['geotechnical', 'bintulu'],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('personal_notes', [
            'user_id' => $user->id,
            'title' => 'Bintulu Port Geotechnical Summary',
            'project_code' => 'PC-2023-011',
        ]);
    }

    public function test_note_creation_validates_required_fields(): void
    {
        $user = User::create([
            'name' => 'Note Validator',
            'email' => 'validate.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePersonalNote::class)
            ->fillForm([
                'title' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['title' => 'required']);
    }

    public function test_executive_can_edit_and_update_note_with_rich_content_persistence(): void
    {
        $user = User::create([
            'name' => 'Note Editor',
            'email' => 'edit.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $note = PersonalNote::create([
            'user_id' => $user->id,
            'title' => 'Original Pier 4 Note',
            'content' => '<p>Initial draft.</p>',
            'project_code' => 'PC-2023-011',
            'tags' => ['draft'],
        ]);

        Livewire::actingAs($user)
            ->test(EditPersonalNote::class, ['record' => $note->id])
            ->fillForm([
                'title' => 'Updated Pier 4 Geotechnical Findings',
                'content' => '<p>Verified settlement tolerance: 15mm max under full load.</p>',
                'project_code' => 'PC-2023-099',
                'tags' => ['geotechnical', 'verified'],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('personal_notes', [
            'id' => $note->id,
            'user_id' => $user->id,
            'title' => 'Updated Pier 4 Geotechnical Findings',
            'content' => '<p>Verified settlement tolerance: 15mm max under full load.</p>',
            'project_code' => 'PC-2023-099',
        ]);
    }

    public function test_executive_can_delete_note_via_action(): void
    {
        $user = User::create([
            'name' => 'Note Deleter',
            'email' => 'delete.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $note = PersonalNote::create([
            'user_id' => $user->id,
            'title' => 'Temporary Note To Delete',
            'content' => '<p>Disposable content.</p>',
        ]);

        Livewire::actingAs($user)
            ->test(EditPersonalNote::class, ['record' => $note->id])
            ->callAction(DeleteAction::class);

        $this->assertDatabaseMissing('personal_notes', [
            'id' => $note->id,
        ]);
    }

    public function test_unauthenticated_guest_is_redirected_from_personal_notes_pages(): void
    {
        config(['auth.enabled' => true]);

        $user = User::create([
            'name' => 'Existing User',
            'email' => 'existing.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'executive',
        ]);

        $note = PersonalNote::create([
            'user_id' => $user->id,
            'title' => 'Protected Note',
            'content' => '<p>Confidential note.</p>',
        ]);

        $this->get('/admin/personal-notes')->assertRedirect('/admin/login');
        $this->get('/admin/personal-notes/create')->assertRedirect('/admin/login');
        $this->get("/admin/personal-notes/{$note->id}/edit")->assertRedirect('/admin/login');
    }
}
