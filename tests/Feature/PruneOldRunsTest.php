<?php

namespace Tests\Feature;

use App\Enums\DeletionRunStatus;
use App\Jobs\PruneOldRuns;
use App\Models\DeletionRun;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOldRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_runs_older_than_the_configured_retention(): void
    {
        Setting::current()->update(['log_retention_days' => 7]);

        $old = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()->subDays(30)]);
        $recent = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()->subDay()]);

        (new PruneOldRuns)->handle();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
    }

    public function test_does_nothing_when_retention_is_null(): void
    {
        Setting::current()->update(['log_retention_days' => null]);

        DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()->subYears(5)]);

        (new PruneOldRuns)->handle();

        $this->assertSame(1, DeletionRun::count());
    }
}
