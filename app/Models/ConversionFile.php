<?php

namespace App\Models;

use App\Enums\ConversionFileStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ConversionFile extends Model
{
    protected $fillable = [
        'conversion_run_id',
        'source_path',
        'extension',
        'status',
        'error_message',
        'source_mtime',
        'source_size_bytes',
        'converted_size_bytes',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ConversionFileStatus::class,
            'source_mtime' => 'datetime',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(ConversionRun::class, 'conversion_run_id');
    }
}
