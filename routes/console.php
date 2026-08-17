<?php

use App\Console\ScheduleConfigurator;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Schedule times are configurable from the Settings page (stored as cron
// expressions) - see App\Console\ScheduleConfigurator.
ScheduleConfigurator::configure(app(Schedule::class));
