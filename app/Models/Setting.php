<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = [
        'scan_path',
        'exclude_patterns',
        'convert_batch_size',
        'convert_extensions',
        'mkv_remux',
        'video_codec',
        'crf',
        'preset',
        'tune',
        'audio_codec',
        'audio_bitrate',
        'delete_marker_filename',
        'delete_extensions',
    ];

    protected function casts(): array
    {
        return [
            'exclude_patterns' => 'array',
            'convert_extensions' => 'array',
            'delete_extensions' => 'array',
            'mkv_remux' => 'boolean',
            'convert_batch_size' => 'integer',
            'crf' => 'integer',
        ];
    }

    /**
     * Fetch the single settings row, creating it with defaults if it doesn't exist yet.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'exclude_patterns' => ['incoming'],
            'convert_extensions' => ['mkv', 'avi'],
            'delete_extensions' => ['mp4', 'mkv', 'avi', 'srt', 'sub'],
        ]);
    }
}
