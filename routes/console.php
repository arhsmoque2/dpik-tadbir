<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// CAP-015 weekly executive personalization reflection. Requires the
// deployed environment to actually run `php artisan schedule:run` on a
// cron (Cloud Scheduler hitting a Cloud Run Job, or an in-container cron) —
// declaring the schedule here makes it correct, not active; verify the
// deploy pipeline actually invokes schedule:run before relying on it firing
// in production.
Schedule::command('ai:reflect-personalization')->weekly();
