<?php

use App\Models\ConversionRun;
use App\Models\DeletionRun;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

new #[Title('Jobs')] class extends Component
{
    use WithPagination;

    public function conversionRuns()
    {
        return ConversionRun::latest()->paginate(10, pageName: 'conversions');
    }

    public function deletionRuns()
    {
        return DeletionRun::latest()->paginate(10, pageName: 'deletions');
    }

    public function with(): array
    {
        return [
            'conversionRuns' => $this->conversionRuns(),
            'deletionRuns' => $this->deletionRuns(),
        ];
    }
};
?>

<div class="space-y-6">
    <h1 class="text-lg font-semibold text-slate-100">Jobs</h1>

    <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-4 text-base font-semibold text-slate-100">Video Conversion Runs</h2>
        <ul class="space-y-2">
            @forelse ($conversionRuns as $run)
                <li>
                    <a href="{{ route('conversions.show', $run) }}" wire:navigate
                       class="block rounded border border-slate-800 p-3 hover:border-slate-600">
                        <div class="flex items-center justify-between text-sm">
                            <span class="flex items-center gap-2 font-medium text-slate-200">
                                Run #{{ $run->id }}
                                @if ($run->is_dry_run)
                                    <span class="rounded bg-amber-950 px-1.5 py-0.5 text-xs font-normal uppercase text-amber-400">Dry Run</span>
                                @endif
                            </span>
                            <span class="text-xs uppercase tracking-wide text-slate-500">
                                {{ str_replace('_', ' ', $run->status->value) }}
                            </span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800">
                            <div class="h-full bg-sky-500" style="width: {{ $run->progressPercent() }}%"></div>
                        </div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ $run->files_total }} files &middot; {{ $run->started_at?->diffForHumans() }}
                        </div>
                    </a>
                </li>
            @empty
                <li class="text-sm text-slate-500">No conversion runs yet.</li>
            @endforelse
        </ul>
        <div class="mt-4">{{ $conversionRuns->onEachSide(1)->links() }}</div>
    </section>

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
