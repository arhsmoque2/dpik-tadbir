<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config(['auth.enabled' => true]);
});

function makeSocialiteUser(
    string $email,
    string $id = 'google-id-12345',
    string $name = 'Google User',
    string $avatar = 'https://lh3.googleusercontent.com/a/default-user'
): SocialiteUser {
    $socialUser = Mockery::mock(SocialiteUser::class);
    $socialUser->shouldReceive('getEmail')->andReturn($email);
    $socialUser->shouldReceive('getId')->andReturn($id);
    $socialUser->shouldReceive('getName')->andReturn($name);
    $socialUser->shouldReceive('getNickname')->andReturn(null);
    $socialUser->shouldReceive('getAvatar')->andReturn($avatar);

    return $socialUser;
}

function mockSocialiteDriver(SocialiteUser $socialUser): void
{
    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andReturn($socialUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

it('redirects to Google authentication URL', function () {
    $response = $this->get(route('auth.google'));

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
});

it('signs in and assigns super_admin role for smoque@gmail.com on first login', function () {
    mockSocialiteDriver(makeSocialiteUser('smoque@gmail.com', 'google-smoque-101', 'Smoque Dev'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin');

    $user = User::where('email', 'smoque@gmail.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('super_admin')
        ->and($user->google_id)->toBe('google-smoque-101')
        ->and($user->isSuperAdmin())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('signs in and assigns super_admin role for arh.homelab@gmail.com on first login', function () {
    mockSocialiteDriver(makeSocialiteUser('arh.homelab@gmail.com', 'google-homelab-102', 'ARH Homelab'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin');

    $user = User::where('email', 'arh.homelab@gmail.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('super_admin')
        ->and($user->google_id)->toBe('google-homelab-102')
        ->and($user->isSuperAdmin())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('signs in and assigns super_admin role for rahman@dpik.com.my', function () {
    mockSocialiteDriver(makeSocialiteUser('rahman@dpik.com.my', 'google-rahman-103', 'Rahman DPIK'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin');

    $user = User::where('email', 'rahman@dpik.com.my')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('super_admin')
        ->and($user->isSuperAdmin())->toBeTrue();

    $this->assertAuthenticatedAs($user);
});

it('signs in whitelisted non-superadmin email as executive', function () {
    mockSocialiteDriver(makeSocialiteUser('hilmio@dpik.com.my', 'google-hilmio-104', 'Hilmi O'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin');

    $user = User::where('email', 'hilmio@dpik.com.my')->first();
    expect($user)->not->toBeNull()
        ->and($user->role)->toBe('executive')
        ->and($user->isSuperAdmin())->toBeFalse();

    $this->assertAuthenticatedAs($user);
});

it('links google_id and avatar to existing user upon Google login', function () {
    $user = User::create([
        'first_name' => 'Smoque',
        'last_name' => 'Owner',
        'name' => 'Smoque Owner',
        'email' => 'smoque@gmail.com',
        'role' => 'executive',
        'password' => bcrypt('secret123'),
    ]);

    mockSocialiteDriver(makeSocialiteUser('smoque@gmail.com', 'google-linked-id-999', 'Smoque Owner', 'https://avatar.com/smoque.png'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin');

    $freshUser = $user->fresh();
    expect($freshUser->google_id)->toBe('google-linked-id-999')
        ->and($freshUser->avatar_url)->toBe('https://avatar.com/smoque.png')
        ->and($freshUser->role)->toBe('super_admin');

    $this->assertAuthenticatedAs($freshUser);
});

it('rejects authentication and redirects to login when email is not whitelisted', function () {
    mockSocialiteDriver(makeSocialiteUser('unauthorized@outsider.com', 'google-outsider-999'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('error');

    expect(User::where('email', 'unauthorized@outsider.com')->exists())->toBeFalse();
    $this->assertGuest();
});

it('redirects to login with error when Google returns an error parameter', function () {
    $response = $this->get(route('auth.google.callback', ['error' => 'access_denied']));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('error', 'Google authentication was cancelled or failed.');
    $this->assertGuest();
});

it('handles exceptions from Socialite gracefully', function () {
    $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
    $provider->shouldReceive('user')->andThrow(new Exception('Connection timeout'));
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect('/admin/login');
    $response->assertSessionHas('error', 'Unable to complete Google sign-in. Please try again.');
    $this->assertGuest();
});

it('renders the Google sign-in button on admin login and register pages', function () {
    $loginResponse = $this->get('/admin/login');
    $loginResponse->assertSuccessful();
    $loginResponse->assertSee('Sign in with Google');
    $loginResponse->assertSee(route('auth.google'));

    $registerResponse = $this->get('/admin/register');
    $registerResponse->assertSuccessful();
    $registerResponse->assertSee('Sign in with Google');
    $registerResponse->assertSee(route('auth.google'));
});
