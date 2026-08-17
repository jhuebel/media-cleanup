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

    public string $convert_schedule = '';

    public string $convert_extensions = '';

    public bool $mkv_remux = true;

    public string $video_codec = 'libx265';

    public int $crf = 26;

    public string $preset = 'medium';

    public string $tune = 'ssim';

    public string $audio_codec = 'aac';

    public string $audio_bitrate = '128k';

    public string $delete_marker_filename = 'deleteafter.txt';

    public string $delete_schedule = '';

    public string $delete_extensions = '';

    public string $log_retention_days = '';

    public ?string $saved = null;

    public function mount(): void
    {
        $settings = Setting::current();

        $this->scan_path = $settings->scan_path;
        $this->exclude_patterns = implode(', ', $settings->exclude_patterns ?? []);
        $this->convert_batch_size = $settings->convert_batch_size;
        $this->convert_schedule = $settings->convert_schedule ?? '';
        $this->convert_extensions = implode(', ', $settings->convert_extensions ?? []);
        $this->mkv_remux = $settings->mkv_remux;
        $this->video_codec = $settings->video_codec;
        $this->crf = $settings->crf;
        $this->preset = $settings->preset;
        $this->tune = $settings->tune;
        $this->audio_codec = $settings->audio_codec;
        $this->audio_bitrate = $settings->audio_bitrate;
        $this->delete_marker_filename = $settings->delete_marker_filename;
        $this->delete_schedule = $settings->delete_schedule ?? '';
        $this->delete_extensions = implode(', ', $settings->delete_extensions ?? []);
        $this->log_retention_days = (string) ($settings->log_retention_days ?? '');
    }

    protected function rules(): array
    {
        $cronRule = function (string $attribute, mixed $value, \Closure $fail) {
            if ($value !== '' && ! \Cron\CronExpression::isValidExpression($value)) {
                $fail('Not a valid cron expression (5 fields: minute hour day month weekday).');
            }
        };

        return [
            'scan_path' => ['nullable', 'string'],
            'exclude_patterns' => ['nullable', 'string'],
            'convert_batch_size' => ['required', 'integer', 'min:1', 'max:5000'],
            'convert_schedule' => ['nullable', 'string', $cronRule],
            'convert_extensions' => ['required', 'string'],
            'mkv_remux' => ['boolean'],
            'video_codec' => ['required', 'string'],
            'crf' => ['required', 'integer', 'min:0', 'max:51'],
            'preset' => ['required', 'string'],
            'tune' => ['nullable', 'string'],
            'audio_codec' => ['required', 'string'],
            'audio_bitrate' => ['required', 'string'],
            'delete_marker_filename' => ['required', 'string'],
            'delete_schedule' => ['nullable', 'string', $cronRule],
            'delete_extensions' => ['required', 'string'],
            'log_retention_days' => ['nullable', 'integer', 'min:1', 'max:3650'],
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
            'convert_schedule' => $this->convert_schedule ?: null,
            'convert_extensions' => $this->splitList($this->convert_extensions, strtolower: true),
            'mkv_remux' => $this->mkv_remux,
            'video_codec' => $this->video_codec,
            'crf' => $this->crf,
            'preset' => $this->preset,
            'tune' => $this->tune,
            'audio_codec' => $this->audio_codec,
            'audio_bitrate' => $this->audio_bitrate,
            'delete_marker_filename' => $this->delete_marker_filename,
            'delete_schedule' => $this->delete_schedule ?: null,
            'delete_extensions' => $this->splitList($this->delete_extensions, strtolower: true),
            'log_retention_days' => $this->log_retention_days !== '' ? (int) $this->log_retention_days : null,
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

        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5"
            x-data="{
                mkvRemux: @js($mkv_remux),
                videoCodec: @js($video_codec),
                crf: @js($crf),
                preset: @js($preset),
                tune: @js($tune),
                audioCodec: @js($audio_codec),
                audioBitrate: @js($audio_bitrate),
                get reencodeArgs() {
                    return `-c:v ${this.videoCodec} -crf ${this.crf} -preset ${this.preset} -tune ${this.tune} -c:a ${this.audioCodec} -b:a ${this.audioBitrate}`
                },
                get mkvCommand() {
                    return this.mkvRemux
                        ? 'ffmpeg -y -i input.mkv -codec copy output.mp4'
                        : `ffmpeg -y -i input.mkv ${this.reencodeArgs} output.mp4`
                },
                get aviCommand() {
                    return `ffmpeg -y -i input.avi ${this.reencodeArgs} output.mp4`
                },
            }"
        >
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
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Schedule (cron expression, blank to disable)</label>
                    <input type="text" wire:model="convert_schedule" placeholder="0 2 * * *"
                           class="w-full rounded border-slate-700 bg-slate-950 font-mono text-sm text-slate-200">
                    <p class="mt-1 text-xs text-slate-500">minute hour day month weekday &mdash; e.g. <code>0 2 * * *</code> = daily at 2:00am</p>
                    @error('convert_schedule') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2 flex items-center gap-2">
                    <input type="checkbox" wire:model="mkv_remux" x-model="mkvRemux" id="mkv_remux" class="rounded border-slate-700 bg-slate-950">
                    <label for="mkv_remux" class="text-sm text-slate-300">Remux .mkv (stream copy) instead of re-encoding</label>
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Video codec</label>
                    <input type="text" wire:model="video_codec" x-model="videoCodec" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('video_codec') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">CRF (0-51, lower = higher quality)</label>
                    <input type="number" wire:model="crf" x-model="crf" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('crf') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Preset</label>
                    <input type="text" wire:model="preset" x-model="preset" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('preset') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Tune</label>
                    <input type="text" wire:model="tune" x-model="tune" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Audio codec</label>
                    <input type="text" wire:model="audio_codec" x-model="audioCodec" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('audio_codec') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Audio bitrate</label>
                    <input type="text" wire:model="audio_bitrate" x-model="audioBitrate" class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    @error('audio_bitrate') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-4 space-y-2">
                <p class="text-xs text-slate-400">Example ffmpeg command lines for these settings:</p>
                <pre class="overflow-x-auto rounded bg-black/40 p-3 text-xs text-slate-400"><span class="text-slate-600"># .mkv &rarr; .mp4</span>
<span x-text="mkvCommand"></span>

<span class="text-slate-600"># .avi &rarr; .mp4</span>
<span x-text="aviCommand"></span></pre>
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
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Schedule (cron expression, blank to disable)</label>
                    <input type="text" wire:model="delete_schedule" placeholder="0 3 * * *"
                           class="w-full rounded border-slate-700 bg-slate-950 font-mono text-sm text-slate-200">
                    <p class="mt-1 text-xs text-slate-500">minute hour day month weekday &mdash; e.g. <code>0 3 * * *</code> = daily at 3:00am</p>
                    @error('delete_schedule') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
            <h2 class="mb-4 text-sm font-semibold text-slate-100">Job History</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs text-slate-400">Keep run history for (days, blank to keep forever)</label>
                    <input type="number" wire:model="log_retention_days" placeholder="30"
                           class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                    <p class="mt-1 text-xs text-slate-500">Conversion and cleanup runs (and their logs) older than this are deleted daily.</p>
                    @error('log_retention_days') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled"
                class="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50">
            Save Settings
        </button>
    </form>
</div>
