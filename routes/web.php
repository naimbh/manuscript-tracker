<?php

use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DataController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => redirect()->route(auth()->check() ? 'dashboard' : 'login'))->name('home');

Route::get('calendar/{token}.ics', CalendarController::class)->name('calendar.feed');

Route::middleware(['auth'])->group(function () {
    Volt::route('dashboard', 'desk')->name('dashboard');
    Volt::route('papers/{paper?}', 'papers.index')->name('papers.index');
    Volt::route('published', 'publications')->name('publications');
    Volt::route('venues', 'venues')->name('venues');
    Volt::route('deadlines', 'deadlines')->name('deadlines');
    Volt::route('opportunities', 'opportunities')->name('opportunities');

    Route::get('files/{file}', [FileController::class, 'show'])->name('files.show');
    Route::get('files/{file}/download', [FileController::class, 'download'])->name('files.download');
    Route::get('export.json', [DataController::class, 'export'])->name('data.export');

    Route::redirect('settings', 'settings/profile');
    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
    Volt::route('settings/data', 'settings.data')->name('settings.data');
    Volt::route('settings/ai', 'settings.ai')->name('settings.ai');
    Volt::route('settings/research', 'settings.research')->name('settings.research');
    Volt::route('settings/general', 'settings.general')->name('settings.general');
    Volt::route('settings/agents', 'settings.agents')->name('settings.agents');
});

require __DIR__.'/auth.php';
