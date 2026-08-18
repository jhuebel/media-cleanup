<?php

namespace Tests\Feature;

use App\Enums\ConversionFileStatus;
use App\Enums\ConversionRunStatus;
use App\Models\ConversionFile;
use App\Models\ConversionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ConversionRunErrorsTest extends TestCase
{
    use RefreshDatabase;

    public function test_run_detail_links_to_the_errors_page_when_a_file_has_an_error(): void
    {
        $run = ConversionRun::create(['status' => ConversionRunStatus::CompletedWithErrors, 'started_at' => now(), 'finished_at' => now()]);
        ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => '/media/Show/episode1.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Failed,
            'error_message' => 'ffmpeg failed: boom',
            'source_mtime' => now(),
        ]);

        Livewire::test('conversion-run-detail', ['conversionRun' => $run])
            ->assertSee('1 error')
            ->assertSee(route('conversions.errors', $run))
            ->assertDontSee('ffmpeg failed: boom');
    }

    public function test_run_detail_does_not_link_to_the_errors_page_when_there_are_none(): void
    {
        $run = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now(), 'finished_at' => now()]);

        Livewire::test('conversion-run-detail', ['conversionRun' => $run])
            ->assertDontSee(route('conversions.errors', $run));
    }

    public function test_errors_page_lists_only_files_with_an_error_message(): void
    {
        $run = ConversionRun::create(['status' => ConversionRunStatus::CompletedWithErrors, 'started_at' => now(), 'finished_at' => now()]);
        ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => '/media/Show/ok.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Done,
            'source_mtime' => now(),
        ]);
        ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => '/media/Show/bad.mkv',
            'extension' => 'mkv',
            'status' => ConversionFileStatus::Failed,
            'error_message' => 'ffmpeg failed: boom',
            'source_mtime' => now(),
        ]);

        Livewire::test('conversion-run-errors', ['conversionRun' => $run])
            ->assertSee('bad.mkv')
            ->assertSee('ffmpeg failed: boom')
            ->assertDontSee('ok.mkv');
    }
}
