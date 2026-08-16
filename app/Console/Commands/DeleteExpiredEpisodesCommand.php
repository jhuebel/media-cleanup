<?php

namespace App\Console\Commands;

use App\Jobs\DeleteExpiredEpisodes;
use Illuminate\Console\Command;

class DeleteExpiredEpisodesCommand extends Command
{
    protected $signature = 'episodes:delete-expired';

    protected $description = 'Scan the media library for deleteafter.txt markers and delete files older than the configured retention';

    public function handle(): void
    {
        DeleteExpiredEpisodes::dispatch();

        $this->info('Expired episode cleanup queued.');
    }
}
