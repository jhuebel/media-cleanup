<?php

namespace Tests\Feature;

use App\Console\ScheduleConfigurator;
use App\Models\Setting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduledTasksTest extends TestCase
{
    use RefreshDatabase;

    public function test_schedule_uses_the_configured_cron_expressions(): void
    {
        Setting::current()->update([
            'convert_schedule' => '*/15 * * * *',
            'delete_schedule' => '30 4 * * 0',
        ]);

        $schedule = new Schedule;
        ScheduleConfigurator::configure($schedule);

        $commands = collect($schedule->events())->mapWithKeys(fn ($event) => [$event->command => $event->expression]);

        $this->assertSame('*/15 * * * *', $commands->first(fn ($expr, $command) => str_contains($command, 'videos:convert')));
        $this->assertSame('30 4 * * 0', $commands->first(fn ($expr, $command) => str_contains($command, 'episodes:delete-expired')));
    }

    public function test_blank_schedule_disables_that_task(): void
    {
        Setting::current()->update([
            'convert_schedule' => null,
            'delete_schedule' => '0 3 * * *',
        ]);

        $schedule = new Schedule;
        ScheduleConfigurator::configure($schedule);

        $commands = collect($schedule->events())->map(fn ($event) => $event->command);

        $this->assertFalse($commands->contains(fn ($command) => str_contains($command, 'videos:convert')));
        $this->assertTrue($commands->contains(fn ($command) => str_contains($command, 'episodes:delete-expired')));
    }

    public function test_next_run_for_returns_null_for_blank_or_invalid_expressions(): void
    {
        $this->assertNull(Setting::nextRunFor(null));
        $this->assertNull(Setting::nextRunFor(''));
        $this->assertNull(Setting::nextRunFor('not a cron expression'));
    }

    public function test_next_run_for_computes_the_next_occurrence(): void
    {
        $next = Setting::nextRunFor('0 2 * * *');

        $this->assertNotNull($next);
        $this->assertSame(2, $next->hour);
        $this->assertSame(0, $next->minute);
        $this->assertTrue($next->isFuture());
    }
}
