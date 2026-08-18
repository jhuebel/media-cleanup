<?php

namespace Tests\Feature;

use App\Enums\ConversionRunStatus;
use App\Models\ConversionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class ConversionRunCancelTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancel_marks_the_underlying_batch_cancelled(): void
    {
        $batch = Bus::batch([])->dispatch();
        $run = ConversionRun::create([
            'status' => ConversionRunStatus::Running,
            'batch_id' => $batch->id,
            'started_at' => now(),
        ]);

        $this->assertTrue($run->isCancellable());
        $this->assertFalse($run->isCancelling());

        $run->cancel();

        $this->assertTrue($batch->fresh()->cancelled());
        $this->assertTrue($run->isCancelling());
        $this->assertFalse($run->isCancellable());
    }

    public function test_a_run_without_a_batch_is_not_cancellable(): void
    {
        $run = ConversionRun::create([
            'status' => ConversionRunStatus::Running,
            'started_at' => now(),
        ]);

        $this->assertFalse($run->isCancellable());
    }

    public function test_a_finished_run_is_not_cancellable(): void
    {
        $batch = Bus::batch([])->dispatch();
        $run = ConversionRun::create([
            'status' => ConversionRunStatus::Completed,
            'batch_id' => $batch->id,
            'started_at' => now(),
            'finished_at' => now(),
        ]);

        $this->assertFalse($run->isCancellable());
    }
}
