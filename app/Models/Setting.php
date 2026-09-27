<?php

namespace App\Models;

use Cron\CronExpression;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Setting extends Model
{
    protected $fillable = [
        'scan_path',
        'delete_marker_filename',
        'delete_schedule',
        'delete_extensions',
        'log_retention_days',
    ];

    protected function casts(): array
    {
        return [
            'delete_extensions' => 'array',
            'log_retention_days' => 'integer',
        ];
    }

    /**
     * Fetch the single settings row, creating it with defaults if it doesn't exist yet.
     */
    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'delete_extensions' => ['mp4', 'mkv', 'avi', 'srt', 'sub'],
        ]);
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
