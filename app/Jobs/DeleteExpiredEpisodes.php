<?php

namespace App\Jobs;

use App\Enums\DeletionMarkerStatus;
use App\Enums\DeletionRunStatus;
use App\Models\DeletedFile;
use App\Models\DeletionRun;
use App\Models\DeletionRunMarker;
use App\Models\Setting;
use App\Services\MediaScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use RuntimeException;

class DeleteExpiredEpisodes implements ShouldQueue
{
    use Dispatchable;

    public function handle(MediaScanner $scanner): void
    {
        if (DeletionRun::where('status', DeletionRunStatus::Running)->exists()) {
            return;
        }

        $settings = Setting::current();

        $run = DeletionRun::create([
            'status' => DeletionRunStatus::Running,
            'started_at' => now(),
        ]);

        try {
            $markers = $scanner->findMarkerFiles($settings);
        } catch (RuntimeException $e) {
            $run->update([
                'status' => DeletionRunStatus::Failed,
                'finished_at' => now(),
                'log' => $e->getMessage()."\n",
            ]);

            return;
        }

        $run->update(['markers_found' => count($markers)]);

        $totalDeleted = 0;

        foreach ($markers as $markerFile) {
            $directory = $markerFile->getPath();
            $handle = fopen($markerFile->getPathname(), 'r');
            $rawDays = trim((string) fgets($handle));
            fclose($handle);
            $days = filter_var($rawDays, FILTER_VALIDATE_INT);

            if ($days === false || $days <= 0) {
                DeletionRunMarker::create([
                    'deletion_run_id' => $run->id,
                    'marker_path' => $markerFile->getPathname(),
                    'delete_after_days' => $days === false ? null : $days,
                    'status' => DeletionMarkerStatus::BadValue,
                ]);

                $run->appendLog("{$markerFile->getPathname()}: BAD VALUE");

                continue;
            }

            $run->appendLog("{$directory}: {$days} Days");

            $marker = DeletionRunMarker::create([
                'deletion_run_id' => $run->id,
                'marker_path' => $markerFile->getPathname(),
                'delete_after_days' => $days,
                'status' => DeletionMarkerStatus::Ok,
            ]);

            $expiredFiles = $scanner->findExpiredFiles($directory, $settings->delete_extensions, now()->subDays($days));

            foreach ($expiredFiles as $expiredFile) {
                $run->appendLog("  - {$expiredFile->getPathname()}");

                DeletedFile::create([
                    'deletion_run_id' => $run->id,
                    'deletion_run_marker_id' => $marker->id,
                    'path' => $expiredFile->getPathname(),
                    'size_bytes' => $expiredFile->getSize(),
                    'last_write_time' => $expiredFile->getMTime(),
                    'deleted_at' => now(),
                ]);

                unlink($expiredFile->getPathname());
            }

            $marker->update(['files_deleted_count' => count($expiredFiles)]);
            $totalDeleted += count($expiredFiles);
        }

        $run->update([
            'status' => DeletionRunStatus::Completed,
            'files_deleted' => $totalDeleted,
            'finished_at' => now(),
        ]);
    }
}
