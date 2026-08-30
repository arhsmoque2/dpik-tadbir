<?php

use App\Filament\Pages\ExecutiveAssistant;
use App\Filament\Pages\ExecutiveSettings;
use App\Filament\Resources\AllowedRegistrationEmailResource;
use App\Filament\Resources\ExecutivePresetResource;
use App\Filament\Resources\PersonalNoteResource;
use App\Filament\Resources\PersonalTaskResource;
use App\Filament\Resources\ProjectRegisterResource;
use App\Filament\Widgets\ExecutiveStatsOverview;
use App\Models\User;

test('filament resources and pages provide valid forms and tables configurations', function () {
    $user = User::create([
        'name' => 'Filament Tester',
        'email' => 'ft@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'super_admin',
    ]);
    test()->actingAs($user);

    expect(AllowedRegistrationEmailResource::getModel())->not->toBeEmpty();
    expect(ExecutivePresetResource::getModel())->not->toBeEmpty();
    expect(PersonalNoteResource::getModel())->not->toBeEmpty();
    expect(PersonalTaskResource::getModel())->not->toBeEmpty();
    expect(ProjectRegisterResource::getModel())->not->toBeEmpty();

    expect(ExecutiveAssistant::getNavigationLabel())->not->toBeEmpty();
    expect(ExecutiveSettings::getNavigationLabel())->not->toBeEmpty();

    $widget = new ExecutiveStatsOverview;
    expect($widget)->toBeInstanceOf(ExecutiveStatsOverview::class);
});
