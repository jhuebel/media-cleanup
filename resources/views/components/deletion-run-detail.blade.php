<?php

use App\Models\DeletionRun;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Cleanup Run')] class extends Component
{
    public DeletionRun $deletionRun;

    public function mount(DeletionRun $deletionRun): void
    {
        $this->deletionRun = $deletionRun;
    }

    #[Computed]
    public function run(): DeletionRun
    {
        return $this->deletionRun->fresh();
    }

    #[Computed]
    public function markers()
    {
        return $this->deletionRun->markers()->orderBy('id')->get();
    }

    #[Computed]
    public function deletedFiles()
    {
        return $this->deletionRun->deletedFiles()->orderBy('id')->get();
    }

    public function deleteLog(): void
    {
        $this->deletionRun->clearLog();
    }
};
?>

<div wire:poll.4s class="space-y-6">
    <div>
        <a href="{{ route('dashboard') }}" wire:navigate class="text-sm text-slate-400 hover:text-slate-100">&larr; Dashboard</a>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <div class="flex items-center justify-between">
            <h1 class="text-lg font-semibold text-slate-100">Cleanup Run #{{ $this->run->id }}</h1>
            <span class="text-xs uppercase tracking-wide text-slate-500">{{ $this->run->status->value }}</span>
        </div>
        <div class="mt-2 text-xs text-slate-500">
            {{ $this->run->markers_found }} markers &middot;
            {{ $this->run->files_deleted }} files deleted &middot;
            started {{ $this->run->started_at?->diffForHumans() }}
            @if ($this->run->finished_at)
                &middot; finished {{ $this->run->finished_at->diffForHumans() }}
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-3 text-sm font-semibold text-slate-100">Markers</h2>
        <div class="overflow-hidden rounded border border-slate-800">
            <table class="w-full text-left text-sm">
                <thead class="bg-slate-800/50 text-xs uppercase text-slate-500">
                    <tr>
                        <th class="px-3 py-2">Marker</th>
                        <th class="px-3 py-2">Days</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Deleted</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    @foreach ($this->markers as $marker)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs text-slate-300">{{ $marker->marker_path }}</td>
                            <td class="px-3 py-2 text-xs text-slate-400">{{ $marker->delete_after_days ?? '-' }}</td>
                            <td class="px-3 py-2 text-xs">
                                <span @class([
                                    'rounded px-1.5 py-0.5 text-xs uppercase',
                                    'bg-emerald-950 text-emerald-400' => $marker->status->value === 'ok',
                                    'bg-rose-950 text-rose-400' => $marker->status->value === 'bad_value',
                                ])>{{ str_replace('_', ' ', $marker->status->value) }}</span>
                            </td>
                            <td class="px-3 py-2 text-xs text-slate-400">{{ $marker->files_deleted_count }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-3 text-sm font-semibold text-slate-100">Deleted Files</h2>
        <ul class="space-y-1 text-xs font-mono text-slate-400">
            @forelse ($this->deletedFiles as $file)
                <li>{{ $file->path }} <span class="text-slate-600">({{ number_format($file->size_bytes / 1048576, 1) }} MB)</span></li>
            @empty
                <li class="font-sans text-slate-500">No files deleted in this run.</li>
            @endforelse
        </ul>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <div class="mb-3 flex items-center justify-between gap-2">
            <h2 class="text-sm font-semibold text-slate-100">Log</h2>
            @if ($this->run->finished_at && $this->run->log)
                <button
                    wire:click="deleteLog"
                    wire:confirm="Delete this run's log? It will also be removed from the Jobs list, though the run and its files stay in history."
                    wire:loading.attr="disabled"
                    class="rounded border border-rose-800 px-2.5 py-1 text-xs font-medium text-rose-400 hover:border-rose-600 hover:text-rose-300 disabled:opacity-50"
                >
                    Delete Log
                </button>
            @endif
        </div>
        <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded bg-black/40 p-3 text-xs text-slate-400">{{ $this->run->log }}</pre>
    </div>
</div>
