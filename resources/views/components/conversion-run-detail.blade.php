<?php

use App\Models\ConversionRun;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Conversion Run')] class extends Component
{
    public ConversionRun $conversionRun;

    public function mount(ConversionRun $conversionRun): void
    {
        $this->conversionRun = $conversionRun;
    }

    #[Computed]
    public function run(): ConversionRun
    {
        return $this->conversionRun->fresh();
    }

    #[Computed]
    public function files()
    {
        return $this->conversionRun->files()->orderBy('id')->get();
    }

    #[Computed]
    public function errorCount(): int
    {
        return $this->conversionRun->files()->whereNotNull('error_message')->count();
    }

    public function cancel(): void
    {
        $this->conversionRun->cancel();
    }
};
?>

<div wire:poll.4s class="space-y-6">
    <div>
        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-slate-100">&larr; Dashboard</a>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <div class="flex items-center justify-between">
            <h1 class="flex items-center gap-2 text-lg font-semibold text-slate-100">
                Conversion Run #{{ $this->run->id }}
                @if ($this->run->is_dry_run)
                    <span class="rounded bg-amber-950 px-1.5 py-0.5 text-xs font-normal uppercase text-amber-400">Dry Run</span>
                @endif
            </h1>
            <div class="flex items-center gap-3">
                @if ($this->run->isCancellable())
                    <button
                        wire:click="cancel"
                        wire:confirm="Cancel this run? Files already converting will finish, but no further files will be started."
                        wire:loading.attr="disabled"
                        class="rounded border border-rose-800 px-2.5 py-1 text-xs font-medium text-rose-400 hover:border-rose-600 hover:text-rose-300 disabled:opacity-50"
                    >
                        Cancel
                    </button>
                @endif
                <span class="text-xs uppercase tracking-wide text-slate-500">
                    {{ $this->run->isCancelling() ? 'Cancelling' : str_replace('_', ' ', $this->run->status->value) }}
                </span>
            </div>
        </div>
        @if ($this->run->is_dry_run)
            <p class="mt-2 text-xs text-amber-400">No files were modified, converted, or deleted during this run.</p>
        @endif
        <div class="mt-3 h-1.5 overflow-hidden rounded-full bg-slate-800">
            <div class="h-full bg-sky-500" style="width: {{ $this->run->progressPercent() }}%"></div>
        </div>
        <div class="mt-2 text-xs text-slate-500">
            {{ $this->run->files_total }} files &middot;
            started {{ $this->run->started_at?->diffForHumans() }}
            @if ($this->run->finished_at)
                &middot; finished {{ $this->run->finished_at->diffForHumans() }}
            @endif
            @if ($this->errorCount > 0)
                &middot; <a href="{{ route('conversions.errors', $this->run) }}" wire:navigate class="text-rose-400 underline hover:text-rose-300">{{ $this->errorCount }} error{{ $this->errorCount === 1 ? '' : 's' }} &rarr;</a>
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-3 text-sm font-semibold text-slate-100">Files</h2>
        <div class="overflow-hidden rounded border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-800/50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">#</th>
                        <th class="px-3 py-2">Path</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Duration</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($this->files as $file)
                        <tr>
                            <td class="px-3 py-2 text-xs text-slate-500">{{ $loop->iteration }} / {{ $this->files->count() }}</td>
                            <td class="px-3 py-2 font-mono text-xs text-slate-300">{{ $file->source_path }}</td>
                            <td class="px-3 py-2 text-xs">
                                <span @class([
                                    'rounded px-1.5 py-0.5 text-xs uppercase',
                                    'bg-emerald-950 text-emerald-400' => $file->status->value === 'done',
                                    'bg-sky-950 text-sky-400' => $file->status->value === 'would_convert',
                                    'bg-rose-950 text-rose-400' => $file->status->value === 'failed',
                                    'bg-amber-950 text-amber-400' => $file->status->value === 'skipped',
                                    'bg-slate-700 text-slate-300' => $file->status->value === 'cancelled',
                                    'bg-slate-800 text-slate-400' => ! in_array($file->status->value, ['done', 'failed', 'would_convert', 'skipped', 'cancelled']),
                                ])>{{ str_replace('_', ' ', $file->status->value) }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-400">{{ $file->duration() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-3 text-sm font-semibold text-slate-100">Log</h2>
        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded bg-black/40 p-3 text-xs text-slate-400">{{ $this->run->log }}</pre>
    </div>
</div>
