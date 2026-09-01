<?php

use App\Models\AllowedRegistrationEmail;
use App\Models\User;
use App\Services\Auth\RegistrationWhitelistService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use App\Filament\Pages\Auth\Register;

test('registration page can be rendered', function () {
    $this->get('/admin/register')
        ->assertSuccessful();
});

test('registration form requires first name, last name, and email', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'password' => 'password123',
            'passwordConfirmation' => 'password123',
        ])
        ->call('register')
        ->assertHasFormErrors(['first_name', 'last_name', 'email']);
});

test('registration creates user with first_name, last_name, and synthesized name', function () {
    AllowedRegistrationEmail::create([
        'email' => 'tengku.ahmad@dpik.com.my',
        'notes' => 'Senior Partner',
    ]);

    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => 'Tengku Ahmad',
            'last_name' => 'Al-Haj',
            'email' => 'tengku.ahmad@dpik.com.my',
            'password' => 'SecurePass123!',
            'passwordConfirmation' => 'SecurePass123!',
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'tengku.ahmad@dpik.com.my')->first();
    expect($user)->not->toBeNull()
        ->and($user->first_name)->toBe('Tengku Ahmad')
        ->and($user->last_name)->toBe('Al-Haj')
        ->and($user->name)->toBe('Tengku Ahmad Al-Haj')
        ->and($user->getFilamentName())->toBe('Tengku Ahmad Al-Haj');
});

test('registration rejects non-whitelisted email in form validation', function () {
    Livewire::test(Register::class)
        ->fillForm([
            'first_name' => 'Unknown',
            'last_name' => 'Actor',
            'email' => 'unauthorized@external.com',
            'password' => 'SecurePass123!',
            'passwordConfirmation' => 'SecurePass123!',
        ])
        ->call('register')
        ->assertHasFormErrors(['email']);

    expect(User::where('email', 'unauthorized@external.com')->exists())->toBeFalse();
});
