<?php

namespace App\Http\Controllers;

use App\Models\User;
use Carbon\CarbonInterface;

class CalendarController extends Controller
{
    public function __invoke(string $token)
    {
        $user = User::where('calendar_token', $token)->firstOrFail();
        $events = [];

        foreach ($user->deadlines()->with('paper')->where('done', false)->get() as $d) {
            $events[] = ['uid' => "deadline-{$d->id}", 'date' => $d->due_on,
                'summary' => $d->title.($d->paper ? ' · '.$d->paper->displayTitle() : '')];
        }

        $papers = $user->papers()->with('submissions.venue')->get();
        foreach ($papers as $paper) {
            foreach ($paper->submissions as $s) {
                if ($s->revision_due_on && in_array($s->decision, ['major', 'minor'])) {
                    $events[] = ['uid' => "revision-{$s->id}", 'date' => $s->revision_due_on,
                        'summary' => "Revision due · {$paper->displayTitle()} ({$s->venue_name})"];
                }
                if ($clock = $s->clock()) {
                    $events[] = ['uid' => "decision-{$s->id}", 'date' => $clock['expected_on'],
                        'summary' => "Decision expected · {$paper->displayTitle()} ({$s->venue_name})"];
                }
            }
        }

        $esc = fn (string $t) => addcslashes($t, ",;\\");
        $fmt = fn (CarbonInterface $d) => $d->format('Ymd');
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//Manuscript Tracker//EN', 'CALSCALE:GREGORIAN',
            'X-WR-CALNAME:'.config('app.name')];
        $stamp = now()->utc()->format('Ymd\THis\Z');
        foreach ($events as $e) {
            $lines = array_merge($lines, ['BEGIN:VEVENT', "UID:{$e['uid']}@manuscripttracker", "DTSTAMP:{$stamp}",
                'DTSTART;VALUE=DATE:'.$fmt($e['date']), 'DTEND;VALUE=DATE:'.$fmt($e['date']->copy()->addDay()),
                'SUMMARY:'.$esc($e['summary']),
                'BEGIN:VALARM', 'TRIGGER:-P1D', 'ACTION:DISPLAY', 'DESCRIPTION:'.$esc($e['summary']), 'END:VALARM',
                'END:VEVENT']);
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, ['Content-Type' => 'text/calendar; charset=utf-8']);
    }
}
