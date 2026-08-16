<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeletedFile extends Model
{
    protected $fillable = [
        'deletion_run_id',
        'deletion_run_marker_id',
        'path',
        'size_bytes',
        'last_write_time',
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'last_write_time' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DeletionRun::class, 'deletion_run_id');
    }

    public function marker(): BelongsTo
    {
        return $this->belongsTo(DeletionRunMarker::class, 'deletion_run_marker_id');
    }
}
