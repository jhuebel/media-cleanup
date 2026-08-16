<?php

namespace Tests\Feature;

use App\Enums\DeletionMarkerStatus;
use App\Enums\DeletionRunStatus;
use App\Jobs\DeleteExpiredEpisodes;
use App\Models\DeletionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeleteExpiredEpisodesTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/delete-expired-test-'.uniqid();
        mkdir("{$this->root}/Show A/Season 1", 0777, true);

        config(['media.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));

        parent::tearDown();
    }

    public function test_deletes_files_older_than_the_configured_retention_and_keeps_newer_ones(): void
    {
        $dir = "{$this->root}/Show A/Season 1";
        file_put_contents("{$this->root}/Show A/deleteafter.txt", "5\n");
        touch("{$dir}/old.mp4", now()->subDays(10)->timestamp);
        touch("{$dir}/new.mp4", now()->subDay()->timestamp);

        (new DeleteExpiredEpisodes)->handle(app(\App\Services\MediaScanner::class));

        $this->assertFileDoesNotExist("{$dir}/old.mp4");
        $this->assertFileExists("{$dir}/new.mp4");

        $run = DeletionRun::sole();
        $this->assertSame(DeletionRunStatus::Completed, $run->status);
        $this->assertSame(1, $run->files_deleted);
        $this->assertSame(1, $run->markers_found);
    }

    public function test_marks_non_numeric_marker_contents_as_bad_value_without_deleting_anything(): void
    {
        $dir = "{$this->root}/Show A/Season 1";
        file_put_contents("{$this->root}/Show A/deleteafter.txt", "not-a-number\n");
        touch("{$dir}/old.mp4", now()->subDays(10)->timestamp);

        (new DeleteExpiredEpisodes)->handle(app(\App\Services\MediaScanner::class));

        $this->assertFileExists("{$dir}/old.mp4");

        $run = DeletionRun::sole();
        $this->assertSame(0, $run->files_deleted);
        $marker = $run->markers()->sole();
        $this->assertSame(DeletionMarkerStatus::BadValue, $marker->status);
    }

    public function test_skips_a_new_run_while_one_is_already_in_progress(): void
    {
        DeletionRun::create(['status' => DeletionRunStatus::Running, 'started_at' => now()]);

        (new DeleteExpiredEpisodes)->handle(app(\App\Services\MediaScanner::class));

        $this->assertSame(1, DeletionRun::count());
    }
}
