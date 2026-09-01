<?php

namespace App\Jobs;

use App\Models\AiActionReceipt;
use App\Models\PersonalNote;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateWeeklyRollupJob implements ShouldQueue
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
            ->where('executed_at', '>=', now()->subDays(7))
            ->orderBy('executed_at', 'desc')
            ->get();

        $count = $receipts->count();
        $dateStr = now()->format('d M Y');

        if ($count > 0) {
            $actionLines = [];
            foreach ($receipts as $r) {
                $time = $r->executed_at ? $r->executed_at->format('d M H:i') : '';
                $actionLines[] = "• [{$r->action_type}] {$r->description} ({$time})";
            }
            $content = "Weekly Executive Activity Rollup ({$dateStr}):\nTotal Dispatched Actions (7 Days): {$count}\n\n".implode("\n", $actionLines);

            PersonalNote::create([
                'user_id' => $user->id,
                'title' => "Weekly Activity Rollup · {$dateStr}",
                'content' => $content,
                'tags' => ['rollup', 'weekly', 'audit'],
            ]);
        }

        Log::info("Generated weekly rollup for {$user->email}", [
            'total_actions' => $count,
        ]);
    }
}
