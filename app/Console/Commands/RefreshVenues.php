<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Notifications\AiJobFinished;
use App\Support\VenueResearcher;
use Illuminate\Console\Command;

class RefreshVenues extends Command
{
    protected $signature = 'venues:refresh {--all : Refresh every conference, not only watched ones} {--stale=7 : Skip venues checked within this many days}';

    protected $description = 'Update watched venues’ next edition and deadlines with Claude and web search';

    public function handle(VenueResearcher $researcher): int
    {
        if (! VenueResearcher::configured()) {
            $this->warn('ANTHROPIC_API_KEY is not set; skipping.');

            return self::SUCCESS;
        }
        $venues = Venue::query()
            ->when(! $this->option('all'), fn ($q) => $q->where('watch', true))
            ->where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<', now()->subDays((int) $this->option('stale'))))
            ->get();

        $tally = [];
        $runs = [];
        foreach ($venues->groupBy('user_id') as $uid => $group) {
            $runs[$uid] = \App\Models\AiRun::begin(\App\Models\User::find($uid), 'venues', 'Weekly venue update', $group->count(), route('venues'));
        }
        foreach ($venues as $venue) {
            $tally[$venue->user_id] ??= ['ok' => 0, 'failed' => 0];
            $runs[$venue->user_id]->step('Checking '.$venue->name);
            try {
                $researcher->apply($venue, $researcher->research($venue));
                $tally[$venue->user_id]['ok']++;
                $runs[$venue->user_id]->advance();
                $this->info("Updated {$venue->name}");
            } catch (\Throwable $e) {
                $tally[$venue->user_id]['failed']++;
                $runs[$venue->user_id]->note('Error on '.$venue->name.': '.$e->getMessage());
                $runs[$venue->user_id]->advance();
                $this->error("{$venue->name}: {$e->getMessage()}");
            }
        }
        foreach ($runs as $r) {
            $r->finish();
        }
        foreach ($tally as $userId => $t) {
            \App\Models\User::find($userId)?->notify(new AiJobFinished('Venue update finished',
                "{$t['ok']} venues updated".($t['failed'] ? ", {$t['failed']} failed." : '.'), $t['failed'] ? 'warning' : 'success', route('venues')));
        }

        return self::SUCCESS;
    }
}
