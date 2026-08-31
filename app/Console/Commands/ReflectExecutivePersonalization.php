<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Ai\PersonalizationReflectionService;
use Illuminate\Console\Command;

/**
 * Runs the weekly personalization reflection CAP-015 describes for every
 * executive. PersonalizationReflectionService::reflectForUser() has existed
 * since the initial scaffold but was never invoked from anywhere — this
 * command, scheduled weekly in routes/console.php, is what actually keeps
 * UserPersonalizationProfile current so AgentService::buildSystemPrompt()
 * has something real to inject.
 */
class ReflectExecutivePersonalization extends Command
{
    protected $signature = 'ai:reflect-personalization';

    protected $description = 'Refreshes every executive\'s AI personalization profile (CAP-015 weekly reflection).';

    public function handle(PersonalizationReflectionService $reflection): int
    {
        $count = 0;

        User::query()->each(function (User $user) use ($reflection, &$count) {
            $reflection->reflectForUser($user);
            $count++;
        });

        $this->info("Reflected personalization for {$count} executive(s).");

        return self::SUCCESS;
    }
}
