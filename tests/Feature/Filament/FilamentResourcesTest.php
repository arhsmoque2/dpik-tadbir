<?php

use App\DTOs\AiTurnResponse;
use App\Filament\Pages\ExecutiveAssistant;
use App\Filament\Pages\ExecutiveSettings;
use App\Filament\Resources\AllowedRegistrationEmailResource;
use App\Filament\Resources\ExecutivePresetResource;
use App\Filament\Resources\PersonalNoteResource;
use App\Filament\Resources\PersonalTaskResource;
use App\Filament\Resources\ProjectRegisterResource;
use App\Filament\Widgets\ExecutiveStatsOverview;
use App\Mcp\ToolRegistry;
use App\Models\User;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

test('filament resources and pages provide valid forms and tables configurations', function () {
    $user = User::create([
        'name' => 'Filament Tester',
        'email' => 'ft@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'super_admin',
    ]);
    test()->actingAs($user);

    // Resources and Schemas
    $schema1 = AllowedRegistrationEmailResource::form(new Schema);
    expect($schema1->getComponents())->not->toBeEmpty();

    $schema2 = ExecutivePresetResource::form(new Schema);
    expect($schema2->getComponents())->not->toBeEmpty();

    $schema3 = PersonalNoteResource::form(new Schema);
    expect($schema3->getComponents())->not->toBeEmpty();

    $schema4 = PersonalTaskResource::form(new Schema);
    expect($schema4->getComponents())->not->toBeEmpty();

    $schema5 = ProjectRegisterResource::form(new Schema);
    expect($schema5->getComponents())->not->toBeEmpty();

    // Eloquent Queries
    expect(ExecutivePresetResource::getEloquentQuery()->count())->toBeGreaterThanOrEqual(0);
    expect(PersonalNoteResource::getEloquentQuery()->count())->toBeGreaterThanOrEqual(0);
    expect(PersonalTaskResource::getEloquentQuery()->count())->toBeGreaterThanOrEqual(0);

    // Tables
    $t1 = AllowedRegistrationEmailResource::table(Table::make(new AllowedRegistrationEmailResource\Pages\ListAllowedRegistrationEmails));
    expect($t1->getColumns())->not->toBeEmpty();

    $t2 = ExecutivePresetResource::table(Table::make(new ExecutivePresetResource\Pages\ListExecutivePresets));
    expect($t2->getColumns())->not->toBeEmpty();

    $t3 = PersonalNoteResource::table(Table::make(new PersonalNoteResource\Pages\ListPersonalNotes));
    expect($t3->getColumns())->not->toBeEmpty();

    $t4 = PersonalTaskResource::table(Table::make(new PersonalTaskResource\Pages\ListPersonalTasks));
    expect($t4->getColumns())->not->toBeEmpty();

    $t5 = ProjectRegisterResource::table(Table::make(new ProjectRegisterResource\Pages\ListProjectRegisters));
    expect($t5->getColumns())->not->toBeEmpty();

    // Page Class Instantiations & Actions
    expect(AllowedRegistrationEmailResource::getPages())->toBeArray();
    expect(ExecutivePresetResource::getPages())->toBeArray();
    expect(PersonalNoteResource::getPages())->toBeArray();
    expect(PersonalTaskResource::getPages())->toBeArray();
    expect(ProjectRegisterResource::getPages())->toBeArray();

    expect(ExecutiveAssistant::getNavigationLabel())->not->toBeEmpty();
    expect(ExecutiveSettings::getNavigationLabel())->not->toBeEmpty();

    // Test Page Hook Mutators
    $createEmailPage = new class extends AllowedRegistrationEmailResource\Pages\CreateAllowedRegistrationEmail
    {
        /**
         * @param  array<string, mixed>  $d
         * @return array<string, mixed>
         */
        public function testMutate(array $d): array
        {
            return $this->mutateFormDataBeforeCreate($d);
        }
    };
    expect($createEmailPage->testMutate([]))->toHaveKey('created_by_user_id');

    $createPresetPage = new class extends ExecutivePresetResource\Pages\CreateExecutivePreset
    {
        /**
         * @param  array<string, mixed>  $d
         * @return array<string, mixed>
         */
        public function testMutate(array $d): array
        {
            return $this->mutateFormDataBeforeCreate($d);
        }
    };
    expect($createPresetPage->testMutate([]))->toHaveKey('user_id');

    $createNotePage = new class extends PersonalNoteResource\Pages\CreatePersonalNote
    {
        /**
         * @param  array<string, mixed>  $d
         * @return array<string, mixed>
         */
        public function testMutate(array $d): array
        {
            return $this->mutateFormDataBeforeCreate($d);
        }
    };
    expect($createNotePage->testMutate([]))->toHaveKey('user_id');

    $createTaskPage = new class extends PersonalTaskResource\Pages\CreatePersonalTask
    {
        /**
         * @param  array<string, mixed>  $d
         * @return array<string, mixed>
         */
        public function testMutate(array $d): array
        {
            return $this->mutateFormDataBeforeCreate($d);
        }
    };
    expect($createTaskPage->testMutate([]))->toHaveKey('user_id');

    $createProjPage = new class extends ProjectRegisterResource\Pages\CreateProjectRegister
    {
        /**
         * @param  array<string, mixed>  $d
         * @return array<string, mixed>
         */
        public function testMutate(array $d): array
        {
            return $this->mutateFormDataBeforeCreate($d);
        }
    };
    expect($createProjPage->testMutate([]))->toHaveKey('user_id');

    // Page Header Actions
    $listEmailsPage = new class extends AllowedRegistrationEmailResource\Pages\ListAllowedRegistrationEmails
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($listEmailsPage->testActions())->toBeArray();

    $listPresetsPage = new class extends ExecutivePresetResource\Pages\ListExecutivePresets
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($listPresetsPage->testActions())->toBeArray();

    $editPresetPage = new class extends ExecutivePresetResource\Pages\EditExecutivePreset
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($editPresetPage->testActions())->toBeArray();

    $listNotesPage = new class extends PersonalNoteResource\Pages\ListPersonalNotes
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($listNotesPage->testActions())->toBeArray();

    $editNotePage = new class extends PersonalNoteResource\Pages\EditPersonalNote
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($editNotePage->testActions())->toBeArray();

    $listTasksPage = new class extends PersonalTaskResource\Pages\ListPersonalTasks
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($listTasksPage->testActions())->toBeArray();

    $editTaskPage = new class extends PersonalTaskResource\Pages\EditPersonalTask
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($editTaskPage->testActions())->toBeArray();

    $listProjPage = new class extends ProjectRegisterResource\Pages\ListProjectRegisters
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($listProjPage->testActions())->toBeArray();

    $editProjPage = new class extends ProjectRegisterResource\Pages\EditProjectRegister
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($editProjPage->testActions())->toBeArray();

    $viewProjPage = new class extends ProjectRegisterResource\Pages\ViewProjectRegister
    {
        /**
         * @return list<\Filament\Actions\Action|\Filament\Actions\ActionGroup>
         */
        public function testActions(): array
        {
            return $this->getHeaderActions();
        }
    };
    expect($viewProjPage->testActions())->toBeArray();

    // Widget Stats
    $widget = new class extends ExecutiveStatsOverview
    {
        /**
         * @return list<\Filament\Widgets\StatsOverviewWidget\Stat>
         */
        public function callGetStats(): array
        {
            return $this->getStats();
        }
    };
    $stats = $widget->callGetStats();
    expect($stats)->toBeArray()->toHaveCount(4);

    // AI Turn Response DTO
    $dto = new AiTurnResponse('Content', 'suspended', ['tool' => 'test']);
    expect($dto->isSuspended())->toBeTrue();
    $dtoCompleted = new AiTurnResponse('Content', 'completed');
    expect($dtoCompleted->isSuspended())->toBeFalse();

    // Tool Registry
    $registry = app(ToolRegistry::class);
    expect($registry->has('outlook_create_draft'))->toBeTrue();
    expect($registry->has('non_existent_tool'))->toBeFalse();
    expect($registry->all())->toBeArray()->toHaveCount(12);
    expect(fn () => $registry->get('invalid_tool'))->toThrow(InvalidArgumentException::class);
});
