<?php

use App\Models\Setting;
use App\Services\MediaScanner;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Settings')] class extends Component
{
    public string $scan_path = '';

    public string $delete_marker_filename = 'deleteafter.txt';

    public string $delete_schedule = '';

    public string $delete_extensions = '';

    public string $log_retention_days = '';

    public ?string $saved = null;

    public string $current_password = '';

    public string $new_password = '';

    public string $new_password_confirmation = '';

    public ?string $passwordUpdated = null;

    public function mount(): void
    {
        $settings = Setting::current();

        $this->scan_path = $settings->scan_path;
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
            'delete_marker_filename' => $this->delete_marker_filename,
            'delete_schedule' => $this->delete_schedule ?: null,
            'delete_extensions' => $this->splitList($this->delete_extensions, strtolower: true),
            'log_retention_days' => $this->log_retention_days !== '' ? (int) $this->log_retention_days : null,
        ]);

        $this->saved = 'Settings saved.';
    }

    public function changePassword(): void
    {
        $this->passwordUpdated = null;

        $this->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        if (! Hash::check($this->current_password, Auth::user()->password)) {
            $this->addError('current_password', 'Current password is incorrect.');

            return;
        }

        Auth::user()->update(['password' => $this->new_password]);

        $this->reset(['current_password', 'new_password', 'new_password_confirmation']);
        $this->passwordUpdated = 'Password updated.';
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
                    <p class="mt-1 text-xs text-slate-500">Cleanup runs (and their logs) older than this are deleted daily.</p>
                    @error('log_retention_days') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <button type="submit" wire:loading.attr="disabled"
                class="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50">
            Save Settings
        </button>
    </form>

    <section class="rounded-lg border border-slate-800 bg-slate-900 p-5">
        <h2 class="mb-4 text-sm font-semibold text-slate-100">Admin Account</h2>

        @if ($passwordUpdated)
            <div class="mb-4 rounded border border-emerald-800 bg-emerald-950/50 px-4 py-2 text-sm text-emerald-300">
                {{ $passwordUpdated }}
            </div>
        @endif

        <form wire:submit="changePassword" class="grid gap-4 sm:grid-cols-2">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs text-slate-400">Username</label>
                <p class="text-sm text-slate-300">admin <span class="text-slate-500">(cannot be changed)</span></p>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-xs text-slate-400">Current password</label>
                <input type="password" wire:model="current_password" autocomplete="current-password"
                       class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                @error('current_password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400">New password</label>
                <input type="password" wire:model="new_password" autocomplete="new-password"
                       class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
                @error('new_password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-400">Confirm new password</label>
                <input type="password" wire:model="new_password_confirmation" autocomplete="new-password"
                       class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
            </div>
            <div class="sm:col-span-2">
                <button type="submit" wire:loading.attr="disabled"
                        class="rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50">
                    Update Password
                </button>
            </div>
        </form>
    </section>
</div>
