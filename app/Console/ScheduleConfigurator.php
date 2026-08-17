<?php

namespace App\Console;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;

class ScheduleConfigurator
{
    /**
     * Register the conversion/cleanup schedule entries using whatever cron
     * expressions are currently stored in settings. Guarded against
     * pre-migration state, since this runs on every artisan invocation.
     */
    public static function configure(Schedule $schedule): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $settings = Setting::current();

        if ($settings->convert_schedule) {
            $schedule->command('videos:convert')->cron($settings->convert_schedule)->withoutOverlapping();
        }

        if ($settings->delete_schedule) {
            $schedule->command('episodes:delete-expired')->cron($settings->delete_schedule)->withoutOverlapping();
        }
    }
}
