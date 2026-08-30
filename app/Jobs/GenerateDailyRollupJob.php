<?php

namespace App\Jobs;

use App\Models\AiActionReceipt;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDailyRollupJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId
    ) {}

    public function handle(): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            return;
        }

        $receipts = AiActionReceipt::where('user_id', $user->id)
            ->where('executed_at', '>=', now()->subHours(24))
            ->get();

        Log::info("Generated daily rollup for {$user->email}", [
            'total_actions' => $receipts->count(),
        ]);
    }
}
