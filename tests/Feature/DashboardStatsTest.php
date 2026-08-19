<?php

namespace Tests\Feature;

use App\Enums\ConversionFileStatus;
use App\Enums\ConversionRunStatus;
use App\Enums\DeletionRunStatus;
use App\Models\ConversionFile;
use App\Models\ConversionRun;
use App\Models\DeletedFile;
use App\Models\DeletionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_conversion_stats_exclude_dry_runs_and_compute_space_saved(): void
    {
        $realRun = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'is_dry_run' => false, 'started_at' => now()]);
        ConversionFile::create([
            'conversion_run_id' => $realRun->id,
            'source_path' => '/media/a.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Done,
            'source_mtime' => now(),
            'source_size_bytes' => 1_000_000,
            'converted_size_bytes' => 400_000,
            'finished_at' => now(),
        ]);
        ConversionFile::create([
            'conversion_run_id' => $realRun->id,
            'source_path' => '/media/b.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Failed,
            'source_mtime' => now(),
            'finished_at' => now(),
        ]);

        $dryRun = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'is_dry_run' => true, 'started_at' => now()]);
        ConversionFile::create([
            'conversion_run_id' => $dryRun->id,
            'source_path' => '/media/c.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::WouldConvert,
            'source_mtime' => now(),
            'finished_at' => now(),
        ]);

        $component = Livewire::test('dashboard');
        $stats = $component->instance()->conversionStats();

        $this->assertSame(1, $stats['runs']);
        $this->assertSame(1, $stats['done']);
        $this->assertSame(1, $stats['failed']);
        $this->assertSame(600_000, $stats['spaceSaved']);
        $this->assertCount(14, $stats['chart']);
        $this->assertSame(1, collect($stats['chart'])->last()['count']);
    }

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

    public function test_jobs_page_lists_both_run_types(): void
    {
        ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()]);
        DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);

        Livewire::test('jobs')
            ->assertSee('Run #1')
            ->assertSee('Video Conversion Runs')
            ->assertSee('Expired Cleanup Runs');
    }

    public function test_jobs_page_orders_runs_by_id_even_if_created_at_is_out_of_order(): void
    {
        $older = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()]);
        $newer = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()]);

        // Simulate a clock/timezone config change making an older row's
        // created_at read later than a genuinely newer row's - as happened
        // when the app's TZ handling was fixed mid-session.
        $older->forceFill(['created_at' => now()->addHours(5)])->save();

        $runs = Livewire::test('jobs')->viewData('conversionRuns');

        $this->assertSame([$newer->id, $older->id], $runs->pluck('id')->all());
    }
}
