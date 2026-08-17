<?php

namespace Tests\Feature;

use App\Enums\ConversionFileStatus;
use App\Enums\ConversionRunStatus;
use App\Enums\DeletionRunStatus;
use App\Jobs\PruneOldRuns;
use App\Models\ConversionFile;
use App\Models\ConversionRun;
use App\Models\DeletionRun;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PruneOldRunsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_runs_older_than_the_configured_retention_and_cascades_to_children(): void
    {
        Setting::current()->update(['log_retention_days' => 7]);

        $old = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()->subDays(10)]);
        ConversionFile::create([
            'conversion_run_id' => $old->id,
            'source_path' => '/media/old.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Done,
            'source_mtime' => now(),
        ]);
        $recent = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()->subDays(2)]);

        (new PruneOldRuns)->handle();

        $this->assertModelMissing($old);
        $this->assertModelExists($recent);
        $this->assertSame(0, ConversionFile::count());
    }

    public function test_prunes_deletion_runs_too(): void
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

        ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()->subYears(5)]);

        (new PruneOldRuns)->handle();

        $this->assertSame(1, ConversionRun::count());
    }
}
