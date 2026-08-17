<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="bg-slate-950 text-slate-200 min-h-screen antialiased">
    <div class="mx-auto max-w-5xl px-4 py-8">
        <header class="mb-8 flex items-center justify-between">
            <a href="{{ auth()->check() ? route('dashboard') : route('login') }}" wire:navigate class="text-lg font-semibold text-slate-100">
                {{ config('app.name') }}
            </a>
            @auth
                <nav class="flex items-center gap-4 text-sm">
                    <a href="{{ route('dashboard') }}" wire:navigate class="text-slate-400 hover:text-slate-100">Dashboard</a>
                    <a href="{{ route('jobs') }}" wire:navigate class="text-slate-400 hover:text-slate-100">Jobs</a>
                    <a href="{{ route('settings') }}" wire:navigate class="text-slate-400 hover:text-slate-100">Settings</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="text-slate-400 hover:text-slate-100">Logout</button>
                    </form>
                </nav>
            @endauth
        </header>

        <main>
            {{ $slot }}
        </main>
    </div>

    @livewireScripts
</body>
</html>
