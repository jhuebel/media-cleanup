<?php

namespace App\Console\Commands;

use App\Jobs\ScanAndQueueVideoConversion;
use Illuminate\Console\Command;

class ConvertVideosCommand extends Command
{
    protected $signature = 'videos:convert';

    protected $description = 'Scan the media library and queue any eligible .mkv/.avi files for conversion to .mp4';

    public function handle(): void
    {
        ScanAndQueueVideoConversion::dispatch();

        $this->info('Conversion scan queued.');
    }
}
