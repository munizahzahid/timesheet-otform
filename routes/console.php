<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Daily Desknet sync at 1:00 AM
Schedule::command('desknet:sync --type=all')
    ->dailyAt('01:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/desknet-sync.log'));

// Daily timesheet reminder at 9:00 AM
Schedule::command('timesheet:send-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/timesheet-reminders.log'));

// Daily project plan progress recalculation at midnight
Schedule::command('project:recalculate-progress')
    ->dailyAt('00:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/project-progress.log'));

// Daily project task reminders at 9:00 AM
Schedule::command('project:send-task-reminders')
    ->dailyAt('09:00')
    ->withoutOverlapping()
    ->appendOutputTo(storage_path('logs/task-reminders.log'));
