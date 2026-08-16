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

    public function handle(MediaScanner $scanner): void
    {
        if (ConversionRun::whereIn('status', [ConversionRunStatus::Scanning, ConversionRunStatus::Running])->exists()) {
            return;
        }

        $settings = Setting::current();

        $run = ConversionRun::create([
            'status' => ConversionRunStatus::Scanning,
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

        if ($fileCount === 0) {
            $run->update([
                'status' => ConversionRunStatus::Completed,
                'finished_at' => now(),
            ]);
            $run->appendLog('Nothing to process.');

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
                    'status' => $batch->failedJobs > 0
                        ? ConversionRunStatus::CompletedWithErrors
                        : ConversionRunStatus::Completed,
                    'finished_at' => now(),
                ]);
            })
            ->catch(function (Batch $batch, Throwable $e) use ($runId) {
                ConversionRun::find($runId)?->appendLog('Batch error: '.$e->getMessage());
            })
            ->dispatch();

        $run->update(['batch_id' => $batch->id]);
    }
}
