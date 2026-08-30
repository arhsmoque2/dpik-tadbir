<?php

use App\Jobs\GenerateDailyRollupJob;
use App\Jobs\GenerateWeeklyRollupJob;
use App\Models\AiActionReceipt;
use App\Models\User;
use App\Services\Audit\ActionMemoryService;

test('action memory service logs receipts and generates rollups', function () {
    $user = User::create([
        'name' => 'MD Officer',
        'email' => 'mdofficer@dpik.com.my',
        'password' => bcrypt('password'),
    ]);

    $service = new ActionMemoryService;

    $receipt = $service->logReceipt(
        user: $user,
        actionType: 'outlook_reply',
        targetEntity: 'JKR Johor',
        payload: ['subject' => 'Approval Notice'],
        status: 'executed',
        actionToken: 'tok_12345'
    );

    expect($receipt)->toBeInstanceOf(AiActionReceipt::class);
    expect($receipt->user_id)->toBe($user->id);
    expect($receipt->action_type)->toBe('outlook_reply');
    expect($receipt->user())->toBeInstanceOf(Illuminate\Database\Eloquent\Relations\BelongsTo::class);

    $dailyJob = new GenerateDailyRollupJob;
    $dailyJob->handle();

    $weeklyJob = new GenerateWeeklyRollupJob;
    $weeklyJob->handle();

    expect(true)->toBeTrue();
});
