<?php

namespace App\Models;

use App\Enums\DeletionMarkerStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeletionRunMarker extends Model
{
    protected $fillable = [
        'deletion_run_id',
        'marker_path',
        'delete_after_days',
        'status',
        'files_deleted_count',
    ];

    protected function casts(): array
    {
        return [
            'status' => DeletionMarkerStatus::class,
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DeletionRun::class, 'deletion_run_id');
    }

    public function deletedFiles(): HasMany
    {
        return $this->hasMany(DeletedFile::class);
    }
}
