<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Setting extends Model
{
    protected $fillable = [
        'scan_path',
        'exclude_patterns',
        'convert_batch_size',
        'convert_schedule',
        'convert_extensions',
        'mkv_remux',
        'video_codec',
        'crf',
        'preset',
        'tune',
        'audio_codec',
        'audio_bitrate',
        'delete_marker_filename',
        'delete_schedule',
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

    /**
     * Whether a source file with the given extension would be remuxed
     * (stream copy) rather than re-encoded.
     */
    public function usesRemux(string $extension): bool
    {
        return $extension === 'mkv' && $this->mkv_remux;
    }

    /**
     * Human-readable description of what conversion would do for the
     * given extension, used both in dry-run reporting and run logs.
     */
    public function conversionDescription(string $extension): string
    {
        if ($this->usesRemux($extension)) {
            return 'remux to MP4 (stream copy, no re-encode)';
        }

        return sprintf(
            're-encode to MP4 (%s, crf %d, preset %s, tune %s, audio %s %s)',
            $this->video_codec,
            $this->crf,
            $this->preset,
            $this->tune,
            $this->audio_codec,
            $this->audio_bitrate,
        );
    }

    /**
     * Next scheduled run time for a stored cron expression, or null if the
     * expression is blank/invalid.
     */
    public static function nextRunFor(?string $cronExpression): ?Carbon
    {
        if (! $cronExpression || ! CronExpression::isValidExpression($cronExpression)) {
            return null;
        }

        return Carbon::instance(CronExpression::factory($cronExpression)->getNextRunDate());
    }
}
