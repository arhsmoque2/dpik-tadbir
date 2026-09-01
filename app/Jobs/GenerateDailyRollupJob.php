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
            ->orderBy('executed_at', 'desc')
            ->get();

        $count = $receipts->count();
        $dateStr = now()->format('d M Y');

        if ($count > 0) {
            $actionLines = [];
            foreach ($receipts as $r) {
                $time = $r->executed_at ? $r->executed_at->format('H:i') : '';
                $actionLines[] = "• [{$r->action_type}] {$r->description} ({$time})";
            }
            $content = "Daily Executive Activity Rollup for {$dateStr}:\nTotal Dispatched Actions: {$count}\n\n".implode("\n", $actionLines);

            \App\Models\PersonalNote::create([
                'user_id' => $user->id,
                'title' => "Daily Activity Rollup · {$dateStr}",
                'content' => $content,
                'tags' => ['rollup', 'daily', 'audit'],
            ]);
        }

        Log::info("Generated daily rollup for {$user->email}", [
            'total_actions' => $count,
        ]);
    }
}
