<?php

namespace App\Models;

use App\Enums\DeletionRunStatus;
use App\Models\Concerns\HasDuration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class DeletionRun extends Model
{
    use HasDuration;

    protected $fillable = [
        'status',
        'markers_found',
        'files_deleted',
        'started_at',
        'finished_at',
        'log',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeletionRunStatus::class,
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function markers(): HasMany
    {
        return $this->hasMany(DeletionRunMarker::class);
    }

    public function deletedFiles(): HasMany
    {
        return $this->hasMany(DeletedFile::class);
    }

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
