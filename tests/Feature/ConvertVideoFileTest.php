<?php

namespace Tests\Feature;

use App\Enums\ConversionFileStatus;
use App\Enums\ConversionRunStatus;
use App\Jobs\ConvertVideoFile;
use App\Models\ConversionFile;
use App\Models\ConversionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConvertVideoFileTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->ffmpegAvailable()) {
            $this->markTestSkipped('ffmpeg is not installed on PATH.');
        }

        $this->root = sys_get_temp_dir().'/convert-video-test-'.uniqid();
        mkdir("{$this->root}/Show A", 0777, true);

        config(['media.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        if (isset($this->root)) {
            exec('rm -rf '.escapeshellarg($this->root));
        }

        parent::tearDown();
    }

    private function ffmpegAvailable(): bool
    {
        $process = new Process(['ffmpeg', '-version']);
        $process->run();

        return $process->isSuccessful();
    }

    private function makeSampleVideo(string $path, string $videoCodec, string $audioCodec): void
    {
        $process = new Process([
            'ffmpeg', '-y',
            '-f', 'lavfi', '-i', 'testsrc=duration=1:size=64x64:rate=5',
            '-f', 'lavfi', '-i', 'sine=frequency=440:duration=1',
            '-c:v', $videoCodec, '-c:a', $audioCodec,
            $path, '-loglevel', 'error',
        ]);
        $process->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    public function test_remuxes_mkv_to_mp4_preserving_mtime_and_removing_the_original(): void
    {
        $source = "{$this->root}/Show A/episode1.mkv";
        $this->makeSampleVideo($source, 'libx264', 'aac');
        $mtime = (new \DateTime('2024-01-01 12:00:00'))->getTimestamp();
        touch($source, $mtime);

        $run = ConversionRun::create(['status' => ConversionRunStatus::Running, 'started_at' => now()]);
        $file = ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => $source,
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Pending,
            'source_mtime' => $mtime,
        ]);

        (new ConvertVideoFile($file->id))->handle();

        $target = "{$this->root}/Show A/episode1.mp4";
        $this->assertFileExists($target);
        $this->assertFileDoesNotExist($source);
        $this->assertSame($mtime, filemtime($target));
        $this->assertSame(ConversionFileStatus::Done, $file->fresh()->status);
    }

    public function test_marks_the_file_failed_and_cleans_up_temp_output_on_ffmpeg_error(): void
    {
        $source = "{$this->root}/Show A/broken.mkv";
        file_put_contents($source, 'not a real video file');
        touch($source);

        $run = ConversionRun::create(['status' => ConversionRunStatus::Running, 'started_at' => now()]);
        $file = ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => $source,
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Pending,
            'source_mtime' => now(),
        ]);

        try {
            (new ConvertVideoFile($file->id))->handle();
            $this->fail('Expected an exception to be thrown for an invalid source file.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertFileExists($source);
        $this->assertFileDoesNotExist("{$this->root}/Show A/.broken.converting.mp4");
        $this->assertSame(ConversionFileStatus::Failed, $file->fresh()->status);
    }

    public function test_refuses_to_overwrite_an_existing_target_file(): void
    {
        $source = "{$this->root}/Show A/episode1.mkv";
        $this->makeSampleVideo($source, 'libx264', 'aac');
        touch("{$this->root}/Show A/episode1.mp4");

        $run = ConversionRun::create(['status' => ConversionRunStatus::Running, 'started_at' => now()]);
        $file = ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => $source,
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Pending,
            'source_mtime' => now(),
        ]);

        try {
            (new ConvertVideoFile($file->id))->handle();
            $this->fail('Expected an exception when the target already exists.');
        } catch (\Throwable) {
            // expected
        }

        $this->assertFileExists($source);
        $this->assertSame(ConversionFileStatus::Failed, $file->fresh()->status);
        $this->assertStringContainsString('already exists', $file->fresh()->error_message);
    }
}
