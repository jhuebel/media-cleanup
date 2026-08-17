<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Sign In')] class extends Component
{
    public string $username = '';

    public string $password = '';

    public function login(): void
    {
        $this->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        if (! Auth::attempt(['username' => $this->username, 'password' => $this->password])) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages([
                'username' => 'Those credentials do not match our records.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
        session()->regenerate();

        $this->redirect(route('dashboard'), navigate: true);
    }

    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'username' => "Too many attempts. Try again in {$seconds} seconds.",
        ]);
    }

    private function throttleKey(): string
    {
        return Str::lower($this->username).'|'.request()->ip();
    }
};
?>

<div class="mx-auto mt-24 max-w-sm">
    <h1 class="mb-6 text-center text-lg font-semibold text-slate-100">{{ config('app.name') }}</h1>

    <form wire:submit="login" class="space-y-4 rounded-lg border border-slate-800 bg-slate-900 p-5">
        <div>
            <label class="mb-1 block text-xs text-slate-400">Username</label>
            <input type="text" wire:model="username" autofocus autocapitalize="off"
                   class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
            @error('username') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="mb-1 block text-xs text-slate-400">Password</label>
            <input type="password" wire:model="password"
                   class="w-full rounded border-slate-700 bg-slate-950 text-sm text-slate-200">
            @error('password') <p class="mt-1 text-xs text-rose-400">{{ $message }}</p> @enderror
        </div>
        <button type="submit" wire:loading.attr="disabled"
                class="w-full rounded bg-sky-600 px-4 py-2 text-sm font-medium text-white hover:bg-sky-500 disabled:opacity-50">
            Sign In
        </button>
    </form>
</div>
