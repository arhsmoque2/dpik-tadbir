<?php

namespace App\Services\Auth;

use App\Models\AllowedRegistrationEmail;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class RegistrationWhitelistService
{
    /**
     * Checks if a given email is whitelisted for registration.
     */
    public function isEmailAllowed(string $email): bool
    {
        $normalized = strtolower(trim($email));

        // 1. Check environment variable seeds (fallback)
        /** @var list<string> $configured */
        $configured = (array) Config::get('services.registration.allowed_emails', []);
        $configuredNormalized = array_map(fn (string $e): string => strtolower(trim($e)), $configured);

        if (in_array($normalized, $configuredNormalized, true)) {
            return true;
        }

        // 2. Check database table
        return AllowedRegistrationEmail::whereRaw('LOWER(email) = ?', [$normalized])->exists();
    }

    /**
     * Whitelists an email address with optional notes.
     */
    public function whitelistEmail(string $email, string $notes = '', ?User $byUser = null): AllowedRegistrationEmail
    {
        $normalized = strtolower(trim($email));

        return AllowedRegistrationEmail::updateOrCreate(
            ['email' => $normalized],
            [
                'notes' => $notes,
                'created_by_user_id' => $byUser?->id,
            ]
        );
    }

    /**
     * Revokes registration whitelist access for an email.
     */
    public function revokeEmail(string $email): bool
    {
        $normalized = strtolower(trim($email));

        return (bool) AllowedRegistrationEmail::whereRaw('LOWER(email) = ?', [$normalized])->delete();
    }
}
