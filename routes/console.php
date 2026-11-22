<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Each job runs at its set time, or as soon as possible afterwards if the computer
 * was asleep or off then (catch-up). The scheduler checks every few minutes.
 */
$catchUp = function (string $command, string $time, ?int $weekday = null) {
    $key = 'schedule:last-run:'.$command;
    $due = function () use ($key, $time, $weekday) {
        [$h, $m] = array_map('intval', explode(':', $time.':0'));
        $slot = now()->setTime($h, $m);
        if ($weekday !== null) {
            $slot = $slot->subDays((now()->dayOfWeek - $weekday + 7) % 7);
        }
        if ($slot->isFuture()) {
            $slot = $weekday === null ? $slot->subDay() : $slot->subWeek();
        }
        $last = Cache::get($key);
        if (! $last) {
            // First run: start counting from now instead of running everything at once.
            Cache::forever($key, now()->toIso8601String());

            return false;
        }

        return \Illuminate\Support\Carbon::parse($last)->lt($slot);
    };
    Schedule::command($command)->everyFiveMinutes()->when($due)->withoutOverlapping(120)
        ->before(fn () => Cache::forever($key, now()->toIso8601String()));
};

$catchUp('opportunities:scan', config('tracker.scan_time'));
$catchUp('venues:refresh', config('tracker.venues_time'), (int) config('tracker.venues_day'));
$catchUp('publications:refresh', config('tracker.citations_time'), (int) config('tracker.citations_day'));
