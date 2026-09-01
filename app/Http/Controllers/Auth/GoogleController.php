<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Auth\RegistrationWhitelistService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    /**
     * Redirect the user to the Google authentication page.
     */
    public function redirectToGoogle(): \Symfony\Component\HttpFoundation\RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Obtain the user information from Google and authenticate.
     */
    public function handleGoogleCallback(Request $request): RedirectResponse
    {
        if ($request->has('error')) {
            return redirect('/admin/login')->with('error', 'Google authentication was cancelled or failed.');
        }

        try {
            /** @var \Laravel\Socialite\Two\User $googleUser */
            $googleUser = Socialite::driver('google')->user();
            $email = strtolower(trim((string) $googleUser->getEmail()));
            $googleId = (string) $googleUser->getId();
            $avatar = (string) ($googleUser->getAvatar() ?: '');
            $name = trim((string) ($googleUser->getName() ?: $googleUser->getNickname() ?: $email));

            // Whitelist verification (ADR-013)
            $whitelistService = app(RegistrationWhitelistService::class);
            if (! $whitelistService->isEmailAllowed($email)) {
                return redirect('/admin/login')->with(
                    'error',
                    "Access denied. The email address '{$email}' is not authorized to sign in."
                );
            }

            $isPermanentSuperAdmin = in_array($email, RegistrationWhitelistService::UNGATED_SUPER_ADMINS, true);

            // Resolve existing user by Google ID or Email
            $user = User::query()
                ->where('google_id', $googleId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                $dirty = false;

                if (! $user->google_id) {
                    $user->google_id = $googleId;
                    $dirty = true;
                }

                if ($avatar !== '' && ! $user->avatar_url) {
                    $user->avatar_url = $avatar;
                    $dirty = true;
                }

                if ($isPermanentSuperAdmin && $user->role !== 'super_admin') {
                    $user->role = 'super_admin';
                    $dirty = true;
                }

                if ($dirty) {
                    $user->save();
                }

                Auth::login($user, remember: true);

                return redirect()->intended('/admin');
            }

            // Create new whitelisted user
            $parts = explode(' ', $name, 2);
            $firstName = $parts[0] !== '' ? $parts[0] : null;
            $lastName = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : null;

            $user = User::create([
                'first_name' => $firstName,
                'last_name' => $lastName,
                'name' => $name,
                'email' => $email,
                'google_id' => $googleId,
                'avatar_url' => $avatar !== '' ? $avatar : null,
                'role' => $isPermanentSuperAdmin ? 'super_admin' : 'executive',
                'email_verified_at' => now(),
                'password' => bcrypt(Str::random(32)),
            ]);

            Auth::login($user, remember: true);

            return redirect()->intended('/admin');

        } catch (Throwable $e) {
            Log::error('Google OAuth signin failed: '.$e->getMessage(), ['exception' => $e]);

            return redirect('/admin/login')->with(
                'error',
                'Unable to complete Google sign-in. Please try again.'
            );
        }
    }
}
