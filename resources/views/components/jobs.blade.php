<?php

use App\Models\DeletionRun;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Jobs')] class extends Component
{
    use WithPagination;

    public function deletionRuns()
    {
        // Ordered by id rather than created_at: id is immune to clock/timezone
        // changes (e.g. a TZ config fix) shifting stored timestamps out of
        // true insertion order.
        return DeletionRun::visible()->latest('id')->paginate(10, pageName: 'deletions');
    }

    public function with(): array
    {
        return [
            'deletionRuns' => $this->deletionRuns(),
        ];
    }
};
?>

<div class="space-y-6">
    <h1 class="text-lg font-semibold text-slate-100">Jobs</h1>

    <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-4 text-base font-semibold text-slate-100">Expired Cleanup Runs</h2>
        <ul class="space-y-2">
            @forelse ($deletionRuns as $run)
                <li>
                    <a href="{{ route('deletions.show', $run) }}" wire:navigate
                       class="block rounded border border-slate-800 p-3 hover:border-slate-600">
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-slate-200">Run #{{ $run->id }}</span>
                            <span class="text-xs uppercase tracking-wide text-slate-500">
                                {{ $run->status->value }}
                            </span>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ $run->markers_found }} markers &middot; {{ $run->files_deleted }} files deleted &middot;
                            {{ $run->started_at?->diffForHumans() }}
                            @if ($run->duration())
                                &middot; ran for {{ $run->duration() }}
                            @endif
                        </div>
                    </a>
                </li>
            @empty
                <li class="text-sm text-slate-500">No cleanup runs yet.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $deletionRuns->onEachSide(1)->links() }}</div>
    </section>
</div>
