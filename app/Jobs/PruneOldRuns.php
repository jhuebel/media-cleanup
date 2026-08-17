<?php

namespace App\Jobs;

use App\Models\ConversionRun;
use App\Models\DeletionRun;
use App\Models\Setting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Support\Facades\Log;

class PruneOldRuns implements ShouldQueue
{
    use Dispatchable;

    public function handle(): void
    {
        $retentionDays = Setting::current()->log_retention_days;

        if (! $retentionDays) {
            return;
        }

        $cutoff = now()->subDays($retentionDays);

        // Related conversion_files/deletion_run_markers/deleted_files rows
        // cascade-delete at the database level.
        $conversionsDeleted = ConversionRun::where('started_at', '<', $cutoff)->delete();
        $deletionsDeleted = DeletionRun::where('started_at', '<', $cutoff)->delete();

        if ($conversionsDeleted || $deletionsDeleted) {
            Log::info("Pruned {$conversionsDeleted} conversion run(s) and {$deletionsDeleted} deletion run(s) older than {$retentionDays} days.");
        }
    }
}
