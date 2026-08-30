<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Illuminate\Support\Facades\Auth;

use function Pest\Livewire\livewire;

test('superadmin can login with remember me and session persists with remember cookie', function () {
    $user = User::firstOrCreate(
        ['email' => 'admin@dpik.com.my'],
        [
            'name' => 'Admin DPIK',
            'password' => bcrypt('password'),
            'role' => 'super_admin',
        ]
    );

    $component = livewire(Login::class)
        ->fillForm([
            'email' => 'admin@dpik.com.my',
            'password' => 'password',
            'remember' => true,
        ])
        ->call('authenticate');

    $component->assertHasNoFormErrors();
    $component->assertRedirect('/admin');

    $user->refresh();
    expect($user->remember_token)->not->toBeEmpty();

    // Now simulate subsequent request with remember cookie after session cleared
    $recallerName = Auth::getRecallerName();
    $recallerValue = $user->id.'|'.$user->remember_token.'|'.$user->password;

    $response = $this->withUnencryptedCookie($recallerName, $recallerValue)->get('/admin');
    $response->assertSuccessful();
});
