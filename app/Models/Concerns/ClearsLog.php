<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait ClearsLog
{
    /**
     * Exclude runs whose log has been cleared from a query.
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * Clear this run's log and hide it from the Jobs list, without deleting
     * the run itself or its files.
     */
    public function clearLog(): void
    {
        $this->update([
            'log' => null,
            'hidden_at' => now(),
        ]);
    }
}
