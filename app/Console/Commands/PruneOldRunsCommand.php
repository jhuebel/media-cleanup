<?php

namespace App\Console\Commands;

use App\Jobs\PruneOldRuns;
use Illuminate\Console\Command;

class PruneOldRunsCommand extends Command
{
    protected $signature = 'logs:prune';

    protected $description = 'Delete conversion/cleanup run history older than the configured retention period';

    public function handle(): void
    {
        PruneOldRuns::dispatch();

        $this->info('Log pruning queued.');
    }
}
