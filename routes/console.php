<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Requires the hosting scheduler cron; does not launch a daemon or queue worker.
Schedule::command('seera:overdue-reminders --send')->dailyAt('08:00')->withoutOverlapping()
    ->when(fn () => (bool) config('seera.overdue_reminders.enabled'));
