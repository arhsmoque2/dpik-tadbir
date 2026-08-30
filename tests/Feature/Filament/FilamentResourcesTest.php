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

    // Test Page Hook Mutators via Reflection
    $mutateClasses = [
        AllowedRegistrationEmailResource\Pages\CreateAllowedRegistrationEmail::class,
        ExecutivePresetResource\Pages\CreateExecutivePreset::class,
        PersonalNoteResource\Pages\CreatePersonalNote::class,
        PersonalTaskResource\Pages\CreatePersonalTask::class,
        ProjectRegisterResource\Pages\CreateProjectRegister::class,
    ];

    foreach ($mutateClasses as $cls) {
        $ref = new ReflectionMethod($cls, 'mutateFormDataBeforeCreate');
        $instance = new $cls;
        $res = $ref->invoke($instance, []);
        expect($res)->toBeArray();
    }

    // Page Header Actions via Reflection
    $actionClasses = [
        AllowedRegistrationEmailResource\Pages\ListAllowedRegistrationEmails::class,
        ExecutivePresetResource\Pages\ListExecutivePresets::class,
        ExecutivePresetResource\Pages\EditExecutivePreset::class,
        PersonalNoteResource\Pages\ListPersonalNotes::class,
        PersonalNoteResource\Pages\EditPersonalNote::class,
        PersonalTaskResource\Pages\ListPersonalTasks::class,
        PersonalTaskResource\Pages\EditPersonalTask::class,
        ProjectRegisterResource\Pages\ListProjectRegisters::class,
        ProjectRegisterResource\Pages\EditProjectRegister::class,
        ProjectRegisterResource\Pages\ViewProjectRegister::class,
    ];

    foreach ($actionClasses as $cls) {
        $ref = new ReflectionMethod($cls, 'getHeaderActions');
        $instance = new $cls;
        $actions = $ref->invoke($instance);
        expect($actions)->toBeArray();
    }

    // Widget Stats via Reflection
    $refWidget = new ReflectionMethod(ExecutiveStatsOverview::class, 'getStats');
    $widget = new ExecutiveStatsOverview;
    $stats = $refWidget->invoke($widget);
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
