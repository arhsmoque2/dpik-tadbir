<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Resources\PersonalNoteResource\Pages\CreatePersonalNote;
use App\Filament\Resources\PersonalNoteResource\Pages\ListPersonalNotes;
use App\Models\PersonalNote;
use App\Models\User;
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
            'role' => 'super_admin',
        ]);

        $user2 = User::create([
            'name' => 'Note Owner 2',
            'email' => 'note2@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        $note1 = PersonalNote::create([
            'user_id' => $user1->id,
            'title' => 'Executive Meeting Key Decisions',
            'content' => '<p>Approved variation order RM 120k.</p>',
            'project_code' => 'PC-2023-011',
        ]);

        $note2 = PersonalNote::create([
            'user_id' => $user2->id,
            'title' => 'Confidential Partner Review',
            'content' => '<p>Partner audit notes.</p>',
            'project_code' => 'PC-2024-001',
        ]);

        Livewire::actingAs($user1)
            ->test(ListPersonalNotes::class)
            ->assertCanSeeTableRecords([$note1])
            ->assertCanNotSeeTableRecords([$note2])
            ->assertSee('Executive Meeting Key Decisions');
    }

    public function test_executive_can_create_note_with_validation(): void
    {
        $user = User::create([
            'name' => 'Note Creator',
            'email' => 'create.note@dpik.com.my',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]);

        Livewire::actingAs($user)
            ->test(CreatePersonalNote::class)
            ->fillForm([
                'title' => 'Bintulu Port Geotechnical Summary',
                'content' => '<p>Soil testing completed by Ir. Tan.</p>',
                'project_code' => 'PC-2023-011',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('personal_notes', [
            'user_id' => $user->id,
            'title' => 'Bintulu Port Geotechnical Summary',
            'project_code' => 'PC-2023-011',
        ]);
    }
}
