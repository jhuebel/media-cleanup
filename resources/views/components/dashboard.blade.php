<?php

use App\Jobs\DeleteExpiredEpisodes;
use App\Jobs\ScanAndQueueVideoConversion;
use App\Models\ConversionRun;
use App\Models\DeletionRun;
use App\Models\Setting;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Dashboard')] class extends Component
{
    public ?string $flash = null;

    #[Computed]
    public function settings(): Setting
    {
        return Setting::current();
    }

    #[Computed]
    public function conversionRuns()
    {
        return ConversionRun::latest()->take(8)->get();
    }

    #[Computed]
    public function deletionRuns()
    {
        return DeletionRun::latest()->take(8)->get();
    }

    public function convertNow(): void
    {
        ScanAndQueueVideoConversion::dispatch();

        $this->flash = 'Conversion scan queued.';
        unset($this->conversionRuns);
    }

    public function cleanupNow(): void
    {
        DeleteExpiredEpisodes::dispatch();

        $this->flash = 'Expired episode cleanup queued.';
        unset($this->deletionRuns);
    }
};
?>

<div wire:poll.4s class="space-y-6">
    @if ($flash)
        <div class="rounded border border-emerald-800 bg-emerald-950/50 px-4 py-2 text-sm text-emerald-300">
            {{ $flash }}
        </div>
    @endif

    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-100">Video Conversion</h2>
                <button
                    wire:click="convertNow"
                    wire:loading.attr="disabled"
                    class="rounded bg-sky-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50"
                >
                    Scan &amp; Convert Now
                </button>
            </div>
            <p class="mb-4 text-xs text-slate-500">
                /{{ trim($this->settings->scan_path, '/') }} &middot;
                {{ implode(', ', $this->settings->convert_extensions) }} &rarr; mp4 &middot;
                batch of {{ $this->settings->convert_batch_size }}
            </p>

            <ul class="space-y-2">
                @forelse ($this->conversionRuns as $run)
                    <li>
                        <a href="{{ route('conversions.show', $run) }}" wire:navigate
                           class="block rounded border border-slate-800 p-3 hover:border-slate-600">
                            <div class="flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-200">Run #{{ $run->id }}</span>
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
        </section>

        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
            <div class="mb-1 flex items-center justify-between">
                <h2 class="text-base font-semibold text-slate-100">Expired Cleanup</h2>
                <button
                    wire:click="cleanupNow"
                    wire:loading.attr="disabled"
                    class="rounded bg-rose-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-600 disabled:opacity-50"
                >
                    Run Now
                </button>
            </div>
            <p class="mb-4 text-xs text-slate-500">
                Looking for <code class="text-slate-400">{{ $this->settings->delete_marker_filename }}</code> markers &middot;
                {{ implode(', ', $this->settings->delete_extensions) }}
            </p>

            <ul class="space-y-2">
                @forelse ($this->deletionRuns as $run)
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
        </section>
    </div>
</div>
