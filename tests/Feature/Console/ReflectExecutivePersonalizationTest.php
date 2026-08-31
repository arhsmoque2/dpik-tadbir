<?php

use App\Models\User;
use App\Models\UserPersonalizationProfile;

test('ai:reflect-personalization refreshes every executive personalization profile', function () {
    $userA = User::create([
        'name' => 'Executive A',
        'email' => 'exec_a@dpik.com.my',
        'password' => bcrypt('password'),
    ]);
    $userB = User::create([
        'name' => 'Executive B',
        'email' => 'exec_b@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $this->artisan('ai:reflect-personalization')
        ->expectsOutputToContain('Reflected personalization for 2 executive(s).')
        ->assertExitCode(0);

    expect(UserPersonalizationProfile::where('user_id', $userA->id)->exists())->toBeTrue();
    expect(UserPersonalizationProfile::where('user_id', $userB->id)->exists())->toBeTrue();
});
