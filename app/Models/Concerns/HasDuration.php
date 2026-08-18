<?php

namespace App\Models\Concerns;

use Carbon\CarbonInterface;

trait HasDuration
{
    public function duration(): ?string
    {
        if (! $this->started_at || ! $this->finished_at) {
            return null;
        }

        return $this->started_at->diffForHumans($this->finished_at, [
            'syntax' => CarbonInterface::DIFF_ABSOLUTE,
            'parts' => 2,
            'short' => true,
        ]);
    }
}
