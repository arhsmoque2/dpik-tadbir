<?php

use App\Models\AllowedRegistrationEmail;
use App\Models\User;
use App\Services\Auth\RegistrationWhitelistService;
use Illuminate\Support\Facades\Config;

test('registration whitelist service validates configured and database emails', function () {
    Config::set('services.registration.allowed_emails', ['seed@dpik.com.my']);

    $service = new RegistrationWhitelistService;

    // Configured email
    expect($service->isEmailAllowed('seed@dpik.com.my'))->toBeTrue();
    expect($service->isEmailAllowed(' SEED@dpik.com.my '))->toBeTrue();

    // Not allowed yet
    expect($service->isEmailAllowed('director@dpik.com.my'))->toBeFalse();

    $admin = User::create([
        'name' => 'Admin Whitelist',
        'email' => 'admin_wl@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    // Whitelist dynamically
    $record = $service->whitelistEmail('Director@DPIK.com.my ', 'Invited board member', $admin);
    expect($record)->toBeInstanceOf(AllowedRegistrationEmail::class);
    expect($record->email)->toBe('director@dpik.com.my');
    expect($record->created_by_user_id)->toBe($admin->id);

    expect($service->isEmailAllowed('director@dpik.com.my'))->toBeTrue();

    // Revoke
    $revoked = $service->revokeEmail('Director@dpik.com.my');
    expect($revoked)->toBeTrue();
    expect($service->isEmailAllowed('director@dpik.com.my'))->toBeFalse();
});
