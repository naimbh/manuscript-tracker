<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();

Schedule::command('opportunities:scan')->dailyAt(config('tracker.scan_time'));
Schedule::command('venues:refresh')->weeklyOn(config('tracker.venues_day'), config('tracker.venues_time'));
Schedule::command('publications:refresh')->weeklyOn(config('tracker.citations_day'), config('tracker.citations_time'));
