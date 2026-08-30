<?php

use App\Models\AllowedRegistrationEmail;
use App\Models\ChatSession;
use App\Models\PersonalNote;
use App\Models\PersonalTask;
use App\Models\User;
use App\Policies\AllowedRegistrationEmailPolicy;
use App\Policies\ChatSessionPolicy;
use App\Policies\PersonalNotePolicy;
use App\Policies\PersonalTaskPolicy;

test('allowed registration email policy enforces super admin role', function () {
    $admin = User::create([
        'name' => 'Admin User',
        'email' => 'admin_pol@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'super_admin',
    ]);

    $user = User::create([
        'name' => 'Normal User',
        'email' => 'user_pol@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'executive',
    ]);

    $email = AllowedRegistrationEmail::create([
        'email' => 'test@dpik.com.my',
        'created_by_user_id' => $admin->id,
    ]);

    $policy = new AllowedRegistrationEmailPolicy;

    expect($policy->viewAny($admin))->toBeTrue();
    expect($policy->viewAny($user))->toBeFalse();
    expect($policy->create($admin))->toBeTrue();
    expect($policy->create($user))->toBeFalse();
    expect($policy->delete($admin, $email))->toBeTrue();
    expect($policy->delete($user, $email))->toBeFalse();
});

test('chat session policy enforces ownership', function () {
    $user1 = User::create([
        'name' => 'User One',
        'email' => 'user1_chat@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $user2 = User::create([
        'name' => 'User Two',
        'email' => 'user2_chat@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $session = ChatSession::create([
        'user_id' => $user1->id,
        'title' => 'User 1 Session',
    ]);

    $policy = new ChatSessionPolicy;

    expect($policy->viewAny($user1))->toBeTrue();
    expect($policy->create($user1))->toBeTrue();
    expect($policy->view($user1, $session))->toBeTrue();
    expect($policy->view($user2, $session))->toBeFalse();
    expect($policy->update($user1, $session))->toBeTrue();
    expect($policy->update($user2, $session))->toBeFalse();
    expect($policy->delete($user1, $session))->toBeTrue();
    expect($policy->delete($user2, $session))->toBeFalse();
});

test('personal note policy enforces ownership', function () {
    $user1 = User::create([
        'name' => 'User One Note',
        'email' => 'user1_note@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $user2 = User::create([
        'name' => 'User Two Note',
        'email' => 'user2_note@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $note = PersonalNote::create([
        'user_id' => $user1->id,
        'title' => 'Private Note',
        'content' => 'Note secret',
    ]);

    $policy = new PersonalNotePolicy;

    expect($policy->viewAny($user1))->toBeTrue();
    expect($policy->create($user1))->toBeTrue();
    expect($policy->view($user1, $note))->toBeTrue();
    expect($policy->view($user2, $note))->toBeFalse();
    expect($policy->update($user1, $note))->toBeTrue();
    expect($policy->update($user2, $note))->toBeFalse();
    expect($policy->delete($user1, $note))->toBeTrue();
    expect($policy->delete($user2, $note))->toBeFalse();
});

test('personal task policy enforces ownership', function () {
    $user1 = User::create([
        'name' => 'User One Task',
        'email' => 'user1_task@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $user2 = User::create([
        'name' => 'User Two Task',
        'email' => 'user2_task@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $task = PersonalTask::create([
        'user_id' => $user1->id,
        'title' => 'Private Task',
    ]);

    $policy = new PersonalTaskPolicy;

    expect($policy->viewAny($user1))->toBeTrue();
    expect($policy->create($user1))->toBeTrue();
    expect($policy->view($user1, $task))->toBeTrue();
    expect($policy->view($user2, $task))->toBeFalse();
    expect($policy->update($user1, $task))->toBeTrue();
    expect($policy->update($user2, $task))->toBeFalse();
    expect($policy->delete($user1, $task))->toBeTrue();
    expect($policy->delete($user2, $task))->toBeFalse();
});
