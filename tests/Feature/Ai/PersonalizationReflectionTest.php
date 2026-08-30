<?php

use App\Models\User;
use App\Models\UserPersonalizationProfile;
use App\Services\Ai\PersonalizationReflectionService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

test('personalization reflection service calculates and updates profile', function () {
    $user = User::create([
        'name' => 'Reflecting User',
        'email' => 'reflect@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $service = new PersonalizationReflectionService;
    $profile = $service->reflectForUser($user);

    expect($profile)->toBeInstanceOf(UserPersonalizationProfile::class);
    expect($profile->user_id)->toBe($user->id);
    expect($profile->persona_summary)->not->toBeEmpty();
    expect($profile->user())->toBeInstanceOf(BelongsTo::class);
});
