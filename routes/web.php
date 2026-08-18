<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::livewire('/login', 'login')->name('login')->middleware('guest');

Route::post('/logout', function () {
    Auth::logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();

    return redirect()->route('login');
})->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::livewire('/', 'dashboard')->name('dashboard');
    Route::livewire('/jobs', 'jobs')->name('jobs');
    Route::livewire('/settings', 'settings')->name('settings');
    Route::livewire('/conversions/{conversionRun}', 'conversion-run-detail')->name('conversions.show');
    Route::livewire('/conversions/{conversionRun}/errors', 'conversion-run-errors')->name('conversions.errors');
    Route::livewire('/deletions/{deletionRun}', 'deletion-run-detail')->name('deletions.show');
});
