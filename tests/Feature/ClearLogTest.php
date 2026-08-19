<?php

namespace Tests\Feature;

use App\Enums\ConversionRunStatus;
use App\Enums\DeletionRunStatus;
use App\Models\ConversionRun;
use App\Models\DeletionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClearLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_log_empties_the_log_and_marks_the_run_hidden(): void
    {
        $run = ConversionRun::create([
            'status' => ConversionRunStatus::Completed,
            'started_at' => now(),
            'finished_at' => now(),
            'log' => 'some log output',
        ]);

        $run->clearLog();

        $fresh = $run->fresh();
        $this->assertNull($fresh->log);
        $this->assertNotNull($fresh->hidden_at);
    }

    public function test_visible_scope_excludes_runs_with_a_cleared_log(): void
    {
        $visible = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()]);
        $hidden = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()]);
        $hidden->clearLog();

        $ids = ConversionRun::visible()->pluck('id')->all();

        $this->assertSame([$visible->id], $ids);
    }

    public function test_deletion_run_clear_log_and_visible_scope_work_the_same_way(): void
    {
        $run = DeletionRun::create([
            'status' => DeletionRunStatus::Completed,
            'started_at' => now(),
            'finished_at' => now(),
            'log' => 'some log output',
        ]);

        $run->clearLog();

        $this->assertNull($run->fresh()->log);
        $this->assertSame(0, DeletionRun::visible()->count());
    }

    public function test_jobs_page_no_longer_lists_a_run_after_its_log_is_cleared(): void
    {
        $run = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now()]);
        $run->clearLog();

        Livewire::test('jobs')->assertDontSee("Run #{$run->id}");
    }

    public function test_delete_log_button_only_shows_for_finished_runs_with_a_log(): void
    {
        $running = ConversionRun::create(['status' => ConversionRunStatus::Running, 'started_at' => now(), 'log' => 'in progress']);
        $finished = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now(), 'finished_at' => now(), 'log' => 'done']);

        Livewire::test('conversion-run-detail', ['conversionRun' => $running])
            ->assertDontSee('Delete Log');

        Livewire::test('conversion-run-detail', ['conversionRun' => $finished])
            ->assertSee('Delete Log');
    }

    public function test_delete_log_action_clears_the_log_and_removes_it_from_the_jobs_list(): void
    {
        $run = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'started_at' => now(), 'finished_at' => now(), 'log' => 'done']);

        Livewire::test('conversion-run-detail', ['conversionRun' => $run])
            ->call('deleteLog')
            ->assertDontSee('Delete Log');

        $this->assertNull($run->fresh()->log);
        Livewire::test('jobs')->assertDontSee("Run #{$run->id}");
    }

    public function test_prune_dry_runs_deletes_only_dry_run_conversion_runs(): void
    {
        $dryRun = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'is_dry_run' => true, 'started_at' => now()]);
        $realRun = ConversionRun::create(['status' => ConversionRunStatus::Completed, 'is_dry_run' => false, 'started_at' => now()]);

        Livewire::test('jobs')
            ->assertSee('Prune Dry Runs (1)')
            ->call('pruneDryRuns');

        $this->assertModelMissing($dryRun);
        $this->assertModelExists($realRun);
    }

    public function test_prune_dry_runs_button_is_hidden_when_there_are_no_dry_runs(): void
    {
        ConversionRun::create(['status' => ConversionRunStatus::Completed, 'is_dry_run' => false, 'started_at' => now()]);

        Livewire::test('jobs')->assertDontSee('Prune Dry Runs');
    }
}
