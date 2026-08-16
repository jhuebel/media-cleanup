<?php

use App\Models\Setting;
use App\Services\MediaScanner;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] class extends Component
{
    public string $scan_path = '';

    public string $exclude_patterns = '';

    public int $convert_batch_size = 250;

    public string $convert_extensions = '';

    public bool $mkv_remux = true;

    public string $video_codec = 'libx265';

    public int $crf = 26;

    public string $preset = 'medium';

    public string $tune = 'ssim';

    public string $audio_codec = 'aac';

    public string $audio_bitrate = '128k';

    public string $delete_marker_filename = 'deleteafter.txt';

    public string $delete_extensions = '';

    public ?string $saved = null;

    public function mount(): void
    {
        $settings = Setting::current();

        $this->scan_path = $settings->scan_path;
        $this->exclude_patterns = implode(', ', $settings->exclude_patterns ?? []);
        $this->convert_batch_size = $settings->convert_batch_size;
        $this->convert_extensions = implode(', ', $settings->convert_extensions ?? []);
        $this->mkv_remux = $settings->mkv_remux;
        $this->video_codec = $settings->video_codec;
        $this->crf = $settings->crf;
        $this->preset = $settings->preset;
        $this->tune = $settings->tune;
        $this->audio_codec = $settings->audio_codec;
        $this->audio_bitrate = $settings->audio_bitrate;
        $this->delete_marker_filename = $settings->delete_marker_filename;
        $this->delete_extensions = implode(', ', $settings->delete_extensions ?? []);
    }

    protected function rules(): array
    {
        return [
            'scan_path' => ['nullable', 'string'],
            'exclude_patterns' => ['nullable', 'string'],
            'convert_batch_size' => ['required', 'integer', 'min:1', 'max:5000'],
            'convert_extensions' => ['required', 'string'],
            'mkv_remux' => ['boolean'],
            'video_codec' => ['required', 'string'],
            'crf' => ['required', 'integer', 'min:0', 'max:51'],
            'preset' => ['required', 'string'],
            'tune' => ['nullable', 'string'],
            'audio_codec' => ['required', 'string'],
            'audio_bitrate' => ['required', 'string'],
            'delete_marker_filename' => ['required', 'string'],
            'delete_extensions' => ['required', 'string'],
        ];
    }

    public function save(MediaScanner $scanner): void
    {
        $this->validate();
        $this->saved = null;

        $scanPath = trim($this->scan_path, "/ \t\n\r\0\x0B");

        try {
            $scanner->resolveScanRoot(new Setting(['scan_path' => $scanPath]));
        } catch (RuntimeException $e) {
            $this->addError('scan_path', $e->getMessage());

            return;
        }

        Setting::current()->update([
            'scan_path' => $scanPath,
            'exclude_patterns' => $this->splitList($this->exclude_patterns),
            'convert_batch_size' => $this->convert_batch_size,
            'convert_extensions' => $this->splitList($this->convert_extensions, strtolower: true),
            'mkv_remux' => $this->mkv_remux,
            'video_codec' => $this->video_codec,
            'crf' => $this->crf,
            'preset' => $this->preset,
            'tune' => $this->tune,
            'audio_codec' => $this->audio_codec,
            'audio_bitrate' => $this->audio_bitrate,
            'delete_marker_filename' => $this->delete_marker_filename,
            'delete_extensions' => $this->splitList($this->delete_extensions, strtolower: true),
        ]);

        $this->saved = 'Settings saved.';
    }

    private function splitList(string $value, bool $strtolower = false): array
    {
        return collect(explode(',', $value))
            ->map(fn ($v) => trim($v))
            ->filter()
            ->map(fn ($v) => $strtolower ? strtolower($v) : $v)
            ->values()
            ->all();
    }
};
?>

<div class="space-y-6">
    <h1 class="text-lg font-semibold text-slate-100">Settings</h1>

    @if ($saved)
        <div class="rounded border border-emerald-800 bg-emerald-950/50 px-4 py-2 text-sm text-emerald-300">
            {{ $saved }}
        </div>
    @endif

    <form wire:submit="save" class="space-y-8">
        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-100">Scan Scope</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Scan path (relative to /media)</label>
                    <input type="text" wire:model="scan_path" placeholder="e.g. TV Shows"
                           class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('scan_path') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Exclude path patterns (comma-separated)</label>
                    <input type="text" wire:model="exclude_patterns" placeholder="incoming"
                           class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('exclude_patterns') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-100">Conversion</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Source extensions (comma-separated)</label>
                    <input type="text" wire:model="convert_extensions" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('convert_extensions') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Batch size per run</label>
                    <input type="number" wire:model="convert_batch_size" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('convert_batch_size') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input type="checkbox" wire:model="mkv_remux" id="mkv_remux" class="rounded border-slate-700 bg-slate-950">
                    <label for="mkv_remux" class="text-sm text-slate-300">Remux .mkv (stream copy) instead of re-encoding</label>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Video codec</label>
                    <input type="text" wire:model="video_codec" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('video_codec') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">CRF (0-51, lower = higher quality)</label>
                    <input type="number" wire:model="crf" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('crf') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Preset</label>
                    <input type="text" wire:model="preset" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('preset') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Tune</label>
                    <input type="text" wire:model="tune" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Audio codec</label>
                    <input type="text" wire:model="audio_codec" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('audio_codec') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Audio bitrate</label>
                    <input type="text" wire:model="audio_bitrate" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('audio_bitrate') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-100">Expired Cleanup</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Marker filename</label>
                    <input type="text" wire:model="delete_marker_filename" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('delete_marker_filename') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Deletable extensions (comma-separated)</label>
                    <input type="text" wire:model="delete_extensions" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('delete_extensions') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled"
                class="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50">
            Save Settings
        </button>
    </form>
</div>
