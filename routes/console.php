<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Reminder emails. Needs the scheduler running: see README ("Reminders").
Schedule::command('catatan:send-reminders')->everyMinute()->withoutOverlapping();
