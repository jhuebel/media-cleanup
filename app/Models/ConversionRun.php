<?php

namespace App\Models;

use App\Enums\ConversionRunStatus;
use App\Models\Concerns\ClearsLog;
use App\Models\Concerns\HasDuration;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class ConversionRun extends Model
{
    use ClearsLog, HasDuration;

    protected $fillable = [
        'status',
        'is_dry_run',
        'batch_id',
        'files_total',
        'started_at',
        'finished_at',
        'log',
        'hidden_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversionRunStatus::class,
            'is_dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'hidden_at' => 'datetime',
        ];
    }

    public function files(): HasMany
    {
        return $this->hasMany(ConversionFile::class);
    }

    public function batch(): ?Batch
    {
        return $this->batch_id ? Bus::findBatch($this->batch_id) : null;
    }

    public function progressPercent(): int
    {
        if ($this->status === ConversionRunStatus::Scanning) {
            return 0;
        }

        return $this->batch()?->progress() ?? 100;
    }

    /**
     * Whether this run can be cancelled: it must be actively running a
     * batch, and not already in the process of cancelling.
     */
    public function isCancellable(): bool
    {
        return $this->status === ConversionRunStatus::Running
            && ! ($this->batch()?->cancelled() ?? true);
    }

    public function isCancelling(): bool
    {
        return $this->status === ConversionRunStatus::Running
            && ($this->batch()?->cancelled() ?? false);
    }

    /**
     * Cancel the run's batch. Files already being converted are allowed to
     * finish; only files that haven't started yet are skipped.
     */
    public function cancel(): void
    {
        $this->batch()?->cancel();
    }

    /**
     * Append a line to the run log, guarded against interleaved writes
     * from concurrently-running per-file jobs.
     */
    public function appendLog(string $line): void
    {
        DB::transaction(function () use ($line) {
            $run = static::query()->lockForUpdate()->find($this->id);
            $run->update([
                'log' => $run->log.$line."\n",
            ]);
        });
    }
}
