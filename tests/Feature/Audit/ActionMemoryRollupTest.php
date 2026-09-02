<?php

use App\Jobs\GenerateDailyRollupJob;
use App\Jobs\GenerateWeeklyRollupJob;
use App\Models\AiActionReceipt;
use App\Models\PersonalNote;
use App\Models\User;
use App\Services\Audit\ActionMemoryService;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        description: 'Reply to JKR Johor on Site Valuation',
        targetRecipients: ['director@jkr.gov.my'],
        payload: ['subject' => 'Approval Notice'],
        status: 'executed',
        approvalToken: 'tok_12345'
    );

    expect($receipt)->toBeInstanceOf(AiActionReceipt::class);
    expect($receipt->user_id)->toBe($user->id);
    expect($receipt->action_type)->toBe('outlook_reply');
    expect($receipt->user())->toBeInstanceOf(BelongsTo::class);

    $dailyJob = new GenerateDailyRollupJob($user->id);
    $dailyJob->handle();

    $weeklyJob = new GenerateWeeklyRollupJob($user->id);
    $weeklyJob->handle();

    $dailyNote = PersonalNote::where('user_id', $user->id)
        ->where('title', 'like', '%Daily Activity Rollup%')
        ->first();
    expect($dailyNote)->not->toBeNull()
        ->and($dailyNote->content)->toContain('Reply to JKR Johor on Site Valuation')
        ->and($dailyNote->tags)->toContain('daily');

    $weeklyNote = PersonalNote::where('user_id', $user->id)
        ->where('title', 'like', '%Weekly Activity Rollup%')
        ->first();
    expect($weeklyNote)->not->toBeNull()
        ->and($weeklyNote->content)->toContain('Reply to JKR Johor on Site Valuation')
        ->and($weeklyNote->tags)->toContain('weekly');
});
