<?php

use App\Models\PersonalNote;
use App\Models\User;
use App\Policies\PersonalNotePolicy;

test('user can view and update their own personal note', function () {
    $user1 = User::create([
        'name' => 'User One',
        'email' => 'user1@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $note = PersonalNote::create([
        'user_id' => $user1->id,
        'title' => 'Secret Strategy Note',
        'content' => 'High confidentiality strategy',
    ]);

    $policy = new PersonalNotePolicy;

    expect($policy->view($user1, $note))->toBeTrue();
    expect($policy->update($user1, $note))->toBeTrue();
    expect($policy->delete($user1, $note))->toBeTrue();
});

test('user cannot view or modify another user personal note', function () {
    $user1 = User::create([
        'name' => 'User One',
        'email' => 'user1@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $user2 = User::create([
        'name' => 'User Two',
        'email' => 'user2@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $note = PersonalNote::create([
        'user_id' => $user1->id,
        'title' => 'Secret Note User 1',
        'content' => 'Confidential data',
    ]);

    $policy = new PersonalNotePolicy;

    expect($policy->view($user2, $note))->toBeFalse();
    expect($policy->update($user2, $note))->toBeFalse();
    expect($policy->delete($user2, $note))->toBeFalse();
});
