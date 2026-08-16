<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Services\MediaScanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class MediaScannerTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().'/media-scanner-test-'.uniqid();
        mkdir($this->root, 0777, true);
        mkdir("{$this->root}/Show A/Season 1", 0777, true);
        mkdir("{$this->root}/incoming", 0777, true);

        config(['media.root' => $this->root]);
    }

    protected function tearDown(): void
    {
        exec('rm -rf '.escapeshellarg($this->root));

        parent::tearDown();
    }

    public function test_finds_convertible_files_and_excludes_configured_patterns(): void
    {
        touch("{$this->root}/Show A/Season 1/episode1.mkv");
        touch("{$this->root}/Show A/Season 1/episode2.avi");
        touch("{$this->root}/Show A/Season 1/episode3.txt");
        touch("{$this->root}/incoming/should-be-skipped.mkv");

        $settings = Setting::current();
        $scanner = new MediaScanner;

        $files = $scanner->findConvertibleFiles($settings);

        $this->assertCount(2, $files);
        $this->assertEqualsCanonicalizing(
            ['episode1.mkv', 'episode2.avi'],
            array_map(fn ($f) => $f->getFilename(), $files),
        );
    }

    public function test_convert_extensions_setting_controls_which_files_match(): void
    {
        touch("{$this->root}/Show A/Season 1/episode1.mkv");
        touch("{$this->root}/Show A/Season 1/episode2.avi");

        $settings = Setting::current();
        $settings->update(['convert_extensions' => ['avi']]);

        $files = (new MediaScanner)->findConvertibleFiles($settings->fresh());

        $this->assertCount(1, $files);
        $this->assertSame('episode2.avi', $files[0]->getFilename());
    }

    public function test_finds_marker_files_by_configured_name(): void
    {
        touch("{$this->root}/Show A/deleteafter.txt");
        touch("{$this->root}/Show A/Season 1/notes.txt");

        $settings = Setting::current();

        $markers = (new MediaScanner)->findMarkerFiles($settings);

        $this->assertCount(1, $markers);
        $this->assertSame('deleteafter.txt', $markers[0]->getFilename());
    }

    public function test_finds_expired_files_older_than_cutoff_but_not_newer_ones(): void
    {
        $dir = "{$this->root}/Show A/Season 1";
        touch("{$dir}/old.mp4", now()->subDays(10)->timestamp);
        touch("{$dir}/new.mp4", now()->subDay()->timestamp);

        $expired = (new MediaScanner)->findExpiredFiles($dir, ['mp4'], now()->subDays(5));

        $this->assertCount(1, $expired);
        $this->assertSame('old.mp4', $expired[0]->getFilename());
    }

    public function test_resolve_scan_root_rejects_paths_that_escape_the_media_root(): void
    {
        $settings = Setting::current();
        $settings->update(['scan_path' => '../../etc']);

        $this->expectException(RuntimeException::class);

        (new MediaScanner)->resolveScanRoot($settings->fresh());
    }

    public function test_resolve_scan_root_accepts_a_valid_subdirectory(): void
    {
        $settings = Setting::current();
        $settings->update(['scan_path' => 'Show A/Season 1']);

        $resolved = (new MediaScanner)->resolveScanRoot($settings->fresh());

        $this->assertSame(realpath("{$this->root}/Show A/Season 1"), $resolved);
    }
}
