<?php

namespace Tests\Feature;

use App\Enums\DeletionRunStatus;
use App\Models\DeletionRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ClearLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_clear_log_empties_the_log_and_marks_the_run_hidden(): void
    {
        $run = DeletionRun::create([
            'status' => DeletionRunStatus::Completed,
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
        $visible = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);
        $hidden = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);
        $hidden->clearLog();

        $ids = DeletionRun::visible()->pluck('id')->all();

        $this->assertSame([$visible->id], $ids);
    }

    public function test_jobs_page_no_longer_lists_a_run_after_its_log_is_cleared(): void
    {
        $run = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now()]);
        $run->clearLog();

        Livewire::test('jobs')->assertDontSee("Run #{$run->id}");
    }

    public function test_delete_log_button_only_shows_for_finished_runs_with_a_log(): void
    {
        $running = DeletionRun::create(['status' => DeletionRunStatus::Running, 'started_at' => now(), 'log' => 'in progress']);
        $finished = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now(), 'finished_at' => now(), 'log' => 'done']);

        Livewire::test('deletion-run-detail', ['deletionRun' => $running])
            ->assertDontSee('Delete Log');

        Livewire::test('deletion-run-detail', ['deletionRun' => $finished])
            ->assertSee('Delete Log');
    }

    public function test_delete_log_action_clears_the_log_and_removes_it_from_the_jobs_list(): void
    {
        $run = DeletionRun::create(['status' => DeletionRunStatus::Completed, 'started_at' => now(), 'finished_at' => now(), 'log' => 'done']);

        Livewire::test('deletion-run-detail', ['deletionRun' => $run])
            ->call('deleteLog')
            ->assertDontSee('Delete Log');

        $this->assertNull($run->fresh()->log);
        Livewire::test('jobs')->assertDontSee("Run #{$run->id}");
    }
}
