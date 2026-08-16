<?php

namespace App\Models;

use App\Enums\ConversionRunStatus;
use Illuminate\Bus\Batch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class ConversionRun extends Model
{
    protected $fillable = [
        'status',
        'is_dry_run',
        'batch_id',
        'files_total',
        'started_at',
        'finished_at',
        'log',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversionRunStatus::class,
            'is_dry_run' => 'boolean',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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
