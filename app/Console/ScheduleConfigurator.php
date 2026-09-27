<?php

namespace App\Console;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;

class ScheduleConfigurator
{
    /**
     * Register the cleanup schedule entry using whatever cron expression is
     * currently stored in settings. Guarded against pre-migration state,
     * since this runs on every artisan invocation.
     */
    public static function configure(Schedule $schedule): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = Setting::current();

        if ($settings->delete_schedule) {
            $schedule->command('episodes:delete-expired')->cron($settings->delete_schedule)->withoutOverlapping();
        }

        // Log retention (how many days of history to keep) is configurable,
        // but the sweep itself runs on a fixed schedule.
        $schedule->command('logs:prune')->dailyAt('04:30')->withoutOverlapping();
    }
}
