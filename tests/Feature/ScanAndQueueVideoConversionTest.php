<?php

namespace Tests\Feature;

use App\Enums\ConversionFileStatus;
use App\Enums\ConversionRunStatus;
use App\Jobs\ScanAndQueueVideoConversion;
use App\Models\ConversionFile;
use App\Models\ConversionRun;
use App\Models\Setting;
use App\Services\MediaScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ScanAndQueueVideoConversionTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/scan-convert-test-'.uniqid();
        mkdir("{$this->root}/Show A/Season 1", 0777, true);
        mkdir("{$this->root}/incoming", 0777, true);

        config(['media.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));

        parent::tearDown();
    }

    public function test_queues_a_batch_job_per_eligible_file_and_excludes_incoming(): void
    {
        Bus::fake();

        touch("{$this->root}/Show A/Season 1/episode1.mkv");
        touch("{$this->root}/Show A/Season 1/episode2.avi");
        touch("{$this->root}/incoming/skip-me.mkv");

        (new ScanAndQueueVideoConversion)->handle(app(\App\Services\MediaScanner::class));

        $run = ConversionRun::sole();
        $this->assertSame(ConversionRunStatus::Running, $run->status);
        $this->assertSame(2, $run->files_total);
        $this->assertSame(2, ConversionFile::count());

        Bus::assertBatched(fn ($batch) => $batch->jobs->count() === 2);
    }

    public function test_respects_the_configured_batch_size(): void
    {
        Bus::fake();

        touch("{$this->root}/Show A/Season 1/episode1.mkv");
        touch("{$this->root}/Show A/Season 1/episode2.mkv");
        touch("{$this->root}/Show A/Season 1/episode3.mkv");

        Setting::current()->update(['convert_batch_size' => 2]);

        (new ScanAndQueueVideoConversion)->handle(app(\App\Services\MediaScanner::class));

        $this->assertSame(2, ConversionRun::sole()->files_total);
    }

    public function test_completes_immediately_with_nothing_to_process(): void
    {
        (new ScanAndQueueVideoConversion)->handle(app(\App\Services\MediaScanner::class));

        $run = ConversionRun::sole();
        $this->assertSame(ConversionRunStatus::Completed, $run->status);
        $this->assertSame(0, $run->files_total);
    }

    public function test_skips_a_new_scan_while_one_is_already_running(): void
    {
        ConversionRun::create(['status' => ConversionRunStatus::Running, 'started_at' => now()]);

        touch("{$this->root}/Show A/Season 1/episode1.mkv");

        (new ScanAndQueueVideoConversion)->handle(app(\App\Services\MediaScanner::class));

        $this->assertSame(1, ConversionRun::count());
        $this->assertSame(0, ConversionFile::count());
    }

    public function test_dry_run_reports_files_without_touching_the_filesystem_or_dispatching_jobs(): void
    {
        Bus::fake();

        $mkv = "{$this->root}/Show A/Season 1/episode1.mkv";
        $avi = "{$this->root}/Show A/Season 1/episode2.avi";
        touch($mkv);
        touch($avi);

        (new ScanAndQueueVideoConversion(dryRun: true))->handle(app(MediaScanner::class));

        $this->assertFileExists($mkv);
        $this->assertFileExists($avi);

        $run = ConversionRun::sole();
        $this->assertTrue($run->is_dry_run);
        $this->assertSame(ConversionRunStatus::Completed, $run->status);
        $this->assertSame(2, $run->files_total);
        $this->assertNull($run->batch_id);

        $this->assertSame(2, ConversionFile::where('status', ConversionFileStatus::WouldConvert)->count());
        $this->assertStringContainsString('remux to MP4', $run->log);
        $this->assertStringContainsString('re-encode to MP4', $run->log);

        Bus::assertNothingBatched();
    }

    public function test_dry_run_flags_a_target_collision_without_deleting_anything(): void
    {
        $mkv = "{$this->root}/Show A/Season 1/episode1.mkv";
        $existingTarget = "{$this->root}/Show A/Season 1/episode1.mp4";
        touch($mkv);
        touch($existingTarget);

        (new ScanAndQueueVideoConversion(dryRun: true))->handle(app(MediaScanner::class));

        $this->assertFileExists($mkv);
        $this->assertFileExists($existingTarget);

        $file = ConversionFile::sole();
        $this->assertSame(ConversionFileStatus::Skipped, $file->status);
        $this->assertStringContainsString('already exists', $file->error_message);
    }

    public function test_dry_run_is_not_blocked_by_a_real_run_in_progress(): void
    {
        ConversionRun::create(['status' => ConversionRunStatus::Running, 'is_dry_run' => false, 'started_at' => now()]);

        touch("{$this->root}/Show A/Season 1/episode1.mkv");

        (new ScanAndQueueVideoConversion(dryRun: true))->handle(app(MediaScanner::class));

        $this->assertSame(2, ConversionRun::count());
        $this->assertSame(ConversionFileStatus::WouldConvert, ConversionFile::sole()->status);
    }

    public function test_a_completed_dry_run_does_not_block_a_subsequent_real_run(): void
    {
        Bus::fake();

        ConversionRun::create([
            'status' => ConversionRunStatus::Completed,
            'is_dry_run' => true,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        touch("{$this->root}/Show A/Season 1/episode1.mkv");

        (new ScanAndQueueVideoConversion)->handle(app(MediaScanner::class));

        $realRun = ConversionRun::where('is_dry_run', false)->sole();
        $this->assertSame(ConversionRunStatus::Running, $realRun->status);
    }
}
