<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Src\Application\Shared\Jobs\CheckInactiveProcessesJob;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new CheckInactiveProcessesJob)->dailyAt('08:00');

Schedule::command('tasks:send-due-date-reminders')->dailyAt('08:00');
Schedule::command('tasks:send-urgency-alerts')->dailyAt('08:00');

Schedule::command('horizon:snapshot')->everyFiveMinutes();
