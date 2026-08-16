<?php

use Illuminate\Support\Facades\Route;

Route::livewire('/', 'dashboard')->name('dashboard');
Route::livewire('/settings', 'settings')->name('settings');
Route::livewire('/conversions/{conversionRun}', 'conversion-run-detail')->name('conversions.show');
Route::livewire('/deletions/{deletionRun}', 'deletion-run-detail')->name('deletions.show');
