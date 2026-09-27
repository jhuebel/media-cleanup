<?php

namespace Tests\Feature;

use App\Enums\DeletionRunStatus;
use App\Models\DeletedFile;
use App\Models\DeletionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletion_stats_sum_freed_space(): void
    {
        $run = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);
        DeletedFile::create([
            'deletion_run_id' => $run->id,
            'path' => '/media/old.mp4',
            'size_bytes' => 500_000,
            'deleted_at' => now(),
        ]);
        DeletedFile::create([
            'deletion_run_id' => $run->id,
            'path' => '/media/older.mp4',
            'size_bytes' => 300_000,
            'deleted_at' => now(),
        ]);

        $component = Livewire::test('dashboard');
        $stats = $component->instance()->deletionStats();

        $this->assertSame(1, $stats['runs']);
        $this->assertSame(2, $stats['deleted']);
        $this->assertSame(800_000, $stats['spaceFreed']);
    }

    public function test_jobs_page_lists_cleanup_runs(): void
    {
        DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);

        Livewire::test('jobs')
            ->assertSee('Run #1')
            ->assertSee('Expired Cleanup Runs');
    }

    public function test_jobs_page_orders_runs_by_id_even_if_created_at_is_out_of_order(): void
    {
        $older = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);
        $newer = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);

        // Simulate a clock/timezone config change making an older row's
        // created_at read later than a genuinely newer row's - as happened
        // when the app's TZ handling was fixed mid-session.
        $older->forceFill(['created_at' => now()->addHours(5)])->save();

        $runs = Livewire::test('jobs')->viewData('deletionRuns');

        $this->assertSame([$newer->id, $older->id], $runs->pluck('id')->all());
    }
}
