<?php

namespace App\Console\Commands;

use App\Jobs\ScanAndQueueVideoConversion;
use App\Services\MediaScanner;
use Illuminate\Console\Command;

class ConvertVideosCommand extends Command
{
    protected $signature = 'videos:convert {--dry-run : Report what would be converted without touching any files}';

    protected $description = 'Scan the media library and queue any eligible .mkv/.avi files for conversion to .mp4';

    public function handle(MediaScanner $scanner): void
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            // Dry runs are cheap (no ffmpeg work), so run inline rather than
            // via the queue for immediate CLI feedback.
            (new ScanAndQueueVideoConversion(dryRun: true))->handle($scanner);
            $this->info('Dry run complete - check the dashboard for the report.');

            return;
        }

        ScanAndQueueVideoConversion::dispatch();

        $this->info('Conversion scan queued.');
    }
}
