<?php

use App\Models\AiActionReceipt;
use App\Models\AllowedRegistrationEmail;
use App\Models\ChatMessage;
use App\Models\ChatSession;
use App\Models\ExecutivePreset;
use App\Models\PersonalNote;
use App\Models\PersonalTask;
use App\Models\ProjectRegistryEntry;
use App\Models\User;
use App\Models\UserPersonalizationProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

test('domain models instantiate relationships properly', function () {
    $user = User::create([
        'name' => 'All Relationships User',
        'email' => 'allrel@dpik.com.my',
        'password' => bcrypt('password'),
        'role' => 'managing_director',
    ]);

    $whitelist = AllowedRegistrationEmail::create([
        'email' => 'target@dpik.com.my',
        'created_by_user_id' => $user->id,
        'notes' => 'Invited Director',
    ]);
    expect($whitelist->createdBy())->toBeInstanceOf(BelongsTo::class);

    $session = ChatSession::create([
        'user_id' => $user->id,
        'title' => 'Project Kickoff',
    ]);
    expect($session->user())->toBeInstanceOf(BelongsTo::class);
    expect($session->messages())->toBeInstanceOf(HasMany::class);

    $message = ChatMessage::create([
        'chat_session_id' => $session->id,
        'role' => 'user',
        'content' => 'Hello Copilot',
    ]);
    expect($message->session())->toBeInstanceOf(BelongsTo::class);

    $entry = ProjectRegistryEntry::create([
        'project_code' => 'PC-2023-011',
        'project_title' => 'Sungai Udang',
        'content' => 'Progress summary',
        'author_user_id' => $user->id,
    ]);
    expect($entry->author())->toBeInstanceOf(BelongsTo::class);

    $note = PersonalNote::create([
        'user_id' => $user->id,
        'title' => 'Note 1',
        'content' => 'Content 1',
    ]);
    expect($note->user())->toBeInstanceOf(BelongsTo::class);

    $task = PersonalTask::create([
        'user_id' => $user->id,
        'title' => 'Task 1',
    ]);
    expect($task->user())->toBeInstanceOf(BelongsTo::class);

    // User relationship helpers
    expect($user->chatSessions())->toBeInstanceOf(HasMany::class);
    expect($user->personalNotes())->toBeInstanceOf(HasMany::class);
    expect($user->personalTasks())->toBeInstanceOf(HasMany::class);
    expect($user->executivePresets())->toBeInstanceOf(HasMany::class);
    expect($user->actionReceipts())->toBeInstanceOf(HasMany::class);
    expect($user->projectEntries())->toBeInstanceOf(HasMany::class);
    expect($user->personalizationProfile())->toBeInstanceOf(HasOne::class);
});
