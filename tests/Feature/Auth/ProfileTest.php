<?php

use App\Filament\Pages\Auth\EditProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

beforeEach(function () {
    config(['auth.enabled' => true]);
});

test('profile page can be rendered for authenticated user', function () {
    $user = User::create([
        'first_name' => 'Ahmad',
        'last_name' => 'Ibrahim',
        'email' => 'ahmad@dpik.com.my',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user)
        ->get('/admin/profile')
        ->assertSuccessful();
});

test('profile page allows user to update first_name and last_name with automatic name synthesis', function () {
    $user = User::create([
        'first_name' => 'Ahmad',
        'last_name' => 'Ibrahim',
        'email' => 'ahmad@dpik.com.my',
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    Livewire::test(EditProfile::class)
        ->fillForm([
            'first_name' => 'Dato Seri Ahmad',
            'last_name' => 'Ibrahim Shah',
            'email' => 'ahmad@dpik.com.my',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->first_name)->toBe('Dato Seri Ahmad')
        ->and($user->last_name)->toBe('Ibrahim Shah')
        ->and($user->name)->toBe('Dato Seri Ahmad Ibrahim Shah')
        ->and($user->getFilamentName())->toBe('Dato Seri Ahmad Ibrahim Shah');
});

test('user model getNameAttribute and getFilamentName fallback safely', function () {
    $userWithoutNames = new User(['email' => 'exec@dpik.com.my']);
    expect($userWithoutNames->getFilamentName())->toBe('exec@dpik.com.my');

    $userWithLegacyName = new User(['name' => 'Legacy Executive', 'email' => 'legacy@dpik.com.my']);
    expect($userWithLegacyName->name)->toBe('Legacy Executive')
        ->and($userWithLegacyName->getFilamentName())->toBe('Legacy Executive');

    $userWithFirstLast = new User([
        'first_name' => 'Nur',
        'last_name' => 'Aisyah',
        'email' => 'aisyah@dpik.com.my',
    ]);
    expect($userWithFirstLast->name)->toBe('Nur Aisyah')
        ->and($userWithFirstLast->getFilamentName())->toBe('Nur Aisyah');
});
