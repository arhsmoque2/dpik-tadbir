<?php

use App\Models\PersonalTask;
use App\Models\User;
use App\Policies\PersonalTaskPolicy;

test('user can manage their own personal tasks', function () {
    $user1 = User::create([
        'name' => 'User One',
        'email' => 'user1@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $task = PersonalTask::create([
        'user_id' => $user1->id,
        'title' => 'Follow up on JKR letter',
        'status' => 'pending',
    ]);

    $policy = new PersonalTaskPolicy;

    expect($policy->view($user1, $task))->toBeTrue();
    expect($policy->update($user1, $task))->toBeTrue();
    expect($policy->delete($user1, $task))->toBeTrue();
});

test('user cannot view or update another user personal tasks', function () {
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

    $task = PersonalTask::create([
        'user_id' => $user1->id,
        'title' => 'Personal Task User 1',
        'status' => 'pending',
    ]);

    $policy = new PersonalTaskPolicy;

    expect($policy->view($user2, $task))->toBeFalse();
    expect($policy->update($user2, $task))->toBeFalse();
    expect($policy->delete($user2, $task))->toBeFalse();
});
