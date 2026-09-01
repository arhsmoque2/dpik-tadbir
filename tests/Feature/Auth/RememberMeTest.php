<?php

use App\Models\User;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;

use function Pest\Livewire\livewire;

test('superadmin can login with remember me and session persists with remember cookie', function (): void {
    config(['auth.enabled' => true]);

    $user = User::updateOrCreate(
        ['email' => 'admin@dpik.com.my'],
        [
            'name' => 'Admin DPIK',
            'password' => 'password',
            'role' => 'super_admin',
        ]
    );

    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $component = livewire(Login::class)
        ->set('data.email', 'admin@dpik.com.my')
        ->set('data.password', 'password')
        ->set('data.remember', true)
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
