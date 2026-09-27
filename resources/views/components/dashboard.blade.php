<?php

use App\Jobs\DeleteExpiredEpisodes;
use App\Models\DeletedFile;
use App\Models\DeletionRun;
use App\Models\Setting;
use Illuminate\Support\Carbon;
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
    public function deletionStats(): array
    {
        return [
            'runs' => DeletionRun::count(),
            'deleted' => DeletedFile::count(),
            'spaceFreed' => DeletedFile::sum('size_bytes'),
            'chart' => $this->dailyCounts(DeletedFile::query(), 'deleted_at'),
        ];
    }

    /**
     * Zero-filled daily counts for the last 14 days, keyed by date.
     */
    private function dailyCounts($query, string $dateColumn): array
    {
        $since = now()->subDays(13)->startOfDay();

        $counts = (clone $query)
            ->where($dateColumn, '>=', $since)
            ->selectRaw("date({$dateColumn}) as day, count(*) as total")
            ->groupBy('day')
            ->pluck('total', 'day');

        return collect(range(13, 0))
            ->map(function ($daysAgo) use ($counts) {
                $date = now()->subDays($daysAgo)->format('Y-m-d');

                return ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
            })
            ->all();
    }

    public function formatBytes(?int $bytes): string
    {
        if (! $bytes) {
            return '0 MB';
        }

        $gb = $bytes / 1073741824;

        return $gb >= 1
            ? number_format($gb, 1).' GB'
            : number_format($bytes / 1048576, 1).' MB';
    }

    public function cleanupNow(): void
    {
        DeleteExpiredEpisodes::dispatch();

        $this->flash = 'Expired episode cleanup queued.';
        unset($this->deletionStats);
    }
};
?>

<div wire:poll.4s class="space-y-6">
    @if ($flash)
        <div class="rounded border border-emerald-800 bg-emerald-950/50 px-4 py-2 text-sm text-emerald-300">
            {{ $flash }}
        </div>
    @endif

    <div class="grid gap-6 md:max-w-md">
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
            <p class="mb-1 text-xs text-slate-500">
                Looking for <code class="text-slate-400">{{ $this->settings->delete_marker_filename }}</code> markers &middot;
                {{ implode(', ', $this->settings->delete_extensions) }}
            </p>
            <p class="mb-4 text-xs text-slate-500">
                @if ($nextDeleteRun = Setting::nextRunFor($this->settings->delete_schedule))
                    Next scheduled run: {{ $nextDeleteRun->format('M j, g:i A') }} ({{ $nextDeleteRun->diffForHumans() }})
                @else
                    Scheduled run disabled &mdash; <a href="{{ route('settings') }}" wire:navigate class="underline hover:text-slate-300">configure in Settings</a>
                @endif
            </p>

            <div class="grid grid-cols-3 gap-2 text-center">
                <div class="rounded bg-slate-950 p-2">
                    <div class="text-lg font-semibold text-slate-100">{{ $this->deletionStats['runs'] }}</div>
                    <div class="text-xs text-slate-500">runs</div>
                </div>
                <div class="rounded bg-slate-950 p-2">
                    <div class="text-lg font-semibold text-emerald-400">{{ $this->deletionStats['deleted'] }}</div>
                    <div class="text-xs text-slate-500">deleted</div>
                </div>
                <div class="rounded bg-slate-950 p-2">
                    <div class="text-lg font-semibold text-sky-400">{{ $this->formatBytes($this->deletionStats['spaceFreed']) }}</div>
                    <div class="text-xs text-slate-500">freed</div>
                </div>
            </div>

            <div class="mt-4">
                <p class="mb-1 text-xs text-slate-500">Files deleted per day (last 14 days)</p>
                @php $maxDelete = max(1, collect($this->deletionStats['chart'])->max('count')); @endphp
                <div class="flex h-16 items-end gap-1">
                    @foreach ($this->deletionStats['chart'] as $day)
                        <div class="group relative flex-1">
                            <div class="rounded-t bg-rose-700" style="height: {{ max(2, $day['count'] / $maxDelete * 64) }}px"></div>
                            <div class="pointer-events-none absolute -top-6 left-1/2 hidden -translate-x-1/2 whitespace-nowrap rounded bg-slate-800 px-1.5 py-0.5 text-xs text-slate-200 group-hover:block">
                                {{ Carbon::parse($day['date'])->format('M j') }}: {{ $day['count'] }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <a href="{{ route('jobs') }}" wire:navigate class="mt-4 block text-center text-xs text-slate-400 underline hover:text-slate-200">
                View all cleanup runs &rarr;
            </a>
        </section>
    </div>
</div>
