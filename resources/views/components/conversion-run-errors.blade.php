<?php

use App\Models\ConversionRun;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Conversion Run Errors')] class extends Component
{
    public ConversionRun $conversionRun;

    public function mount(ConversionRun $conversionRun): void
    {
        $this->conversionRun = $conversionRun;
    }

    #[Computed]
    public function files()
    {
        return $this->conversionRun->files()
            ->whereNotNull('error_message')
            ->orderBy('id')
            ->get();
    }
};
?>

<div class="space-y-6">
    <div>
        <a href="{{ route('conversions.show', $conversionRun) }}" wire:navigate class="text-sm text-slate-400 hover:text-slate-100">
            &larr; Conversion Run #{{ $conversionRun->id }}
        </a>
    </div>

    <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h1 class="text-lg font-semibold text-slate-100">Errors &mdash; Conversion Run #{{ $conversionRun->id }}</h1>
        <p class="mt-1 text-xs text-slate-500">{{ $this->files->count() }} file(s) with an error</p>
    </div>

    <div class="space-y-4">
        @forelse ($this->files as $file)
            <div class="rounded-lg border border-slate-800 bg-slate-900 p-5">
                <p class="font-mono text-xs text-slate-300">{{ $file->source_path }}</p>
                <pre class="mt-2 max-h-96 overflow-auto whitespace-pre-wrap break-words rounded bg-black/40 p-3 text-xs text-rose-400">{{ $file->error_message }}</pre>
            </div>
        @empty
            <div class="rounded-lg border border-slate-800 bg-slate-900 p-5 text-sm text-slate-500">
                No errors recorded for this run.
            </div>
        @endforelse
    </div>
</div>
