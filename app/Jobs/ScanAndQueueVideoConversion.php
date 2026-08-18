<?php

namespace App\Jobs;

use App\Enums\ConversionFileStatus;
use App\Enums\ConversionRunStatus;
use App\Models\ConversionFile;
use App\Models\ConversionRun;
use App\Models\Setting;
use App\Services\MediaScanner;
use Illuminate\Bus\Batch;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Bus;
use RuntimeException;
use Throwable;

class ScanAndQueueVideoConversion implements ShouldQueue
{
    use Dispatchable;

    public function __construct(public bool $dryRun = false) {}

    public function handle(MediaScanner $scanner): void
    {
        // Dry runs never touch the filesystem or ffmpeg, so they're safe to
        // run alongside (or instead of) a real run in progress - only real
        // runs need to avoid overlapping with each other.
        if (! $this->dryRun && ConversionRun::where('is_dry_run', false)
            ->whereIn('status', [ConversionRunStatus::Scanning, ConversionRunStatus::Running])
            ->exists()) {
            return;
        }

        $settings = Setting::current();

        $run = ConversionRun::create([
            'status' => ConversionRunStatus::Scanning,
            'is_dry_run' => $this->dryRun,
            'started_at' => now(),
        ]);

        try {
            $allFiles = $scanner->findConvertibleFiles($settings);
        } catch (RuntimeException $e) {
            $run->update([
                'status' => ConversionRunStatus::Failed,
                'finished_at' => now(),
                'log' => $e->getMessage()."\n",
            ]);

            return;
        }

        $filesToProcess = array_slice($allFiles, 0, $settings->convert_batch_size);
        $fileCount = count($filesToProcess);

        $run->appendLog("Found {$fileCount} of ".count($allFiles)." eligible files to convert.");

        if ($this->dryRun) {
            $run->appendLog('DRY RUN: no files will be modified, converted, or deleted.');
        }

        if ($fileCount === 0) {
            $run->update([
                'status' => ConversionRunStatus::Completed,
                'finished_at' => now(),
            ]);
            $run->appendLog('Nothing to process.');

            return;
        }

        if ($this->dryRun) {
            $this->runDryRun($run, $settings, $filesToProcess);

            return;
        }

        $conversionFiles = collect($filesToProcess)->map(fn ($file) => ConversionFile::create([
            'conversion_run_id' => $run->id,
            'source_path' => $file->getPathname(),
            'extension' => strtolower($file->getExtension()),
            'status' => ConversionFileStatus::Pending,
            'source_mtime' => $file->getMTime(),
        ]));

        $jobs = $conversionFiles->map(fn (ConversionFile $file) => new ConvertVideoFile($file->id))->all();
        $runId = $run->id;

        // Mark the run "running" before dispatch: on synchronous-style queue
        // drivers, jobs execute inside dispatch() itself, so anything after
        // this point (including a failure mid-batch) must not leave the run
        // stuck in "scanning".
        $run->update(['status' => ConversionRunStatus::Running, 'files_total' => $fileCount]);

        $batch = Bus::batch($jobs)
            ->name("convert-run-{$runId}")
            ->onQueue('conversions')
            ->allowFailures()
            ->finally(function (Batch $batch) use ($runId) {
                ConversionRun::find($runId)?->update([
                    'status' => match (true) {
                        $batch->cancelled() => ConversionRunStatus::Cancelled,
                        $batch->failedJobs > 0 => ConversionRunStatus::CompletedWithErrors,
                        default => ConversionRunStatus::Completed,
                    },
                    'finished_at' => now(),
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($runId) {
                ConversionRun::find($runId)?->appendLog('Batch error: '.$e->getMessage());
            })
            ->dispatch();

        $run->update(['batch_id' => $batch->id]);
    }

    /**
     * @param  \Symfony\Component\Finder\SplFileInfo[]  $files
     */
    private function runDryRun(ConversionRun $run, Setting $settings, array $files): void
    {
        foreach ($files as $file) {
            $source = $file->getPathname();
            $extension = strtolower($file->getExtension());
            $target = dirname($source).'/'.pathinfo($source, PATHINFO_FILENAME).'.mp4';
            $description = $settings->conversionDescription($extension);

            $run->appendLog("{$source}:");

            if (file_exists($target)) {
                ConversionFile::create([
                    'conversion_run_id' => $run->id,
                    'source_path' => $source,
                    'extension' => $extension,
                    'status' => ConversionFileStatus::Skipped,
                    'error_message' => "Target already exists, would fail during a real run: {$target}",
                    'source_mtime' => $file->getMTime(),
                    'finished_at' => now(),
                ]);
                $run->appendLog(" - WOULD FAIL: target already exists ({$target})");

                continue;
            }

            ConversionFile::create([
                'conversion_run_id' => $run->id,
                'source_path' => $source,
                'extension' => $extension,
                'status' => ConversionFileStatus::WouldConvert,
                'source_mtime' => $file->getMTime(),
                'finished_at' => now(),
            ]);
            $run->appendLog(" - Would {$description}");
            $run->appendLog(' - Would remove original '.strtoupper($extension).' after a successful conversion');
        }

        $run->update([
            'status' => ConversionRunStatus::Completed,
            'files_total' => count($files),
            'finished_at' => now(),
        ]);
    }
}
