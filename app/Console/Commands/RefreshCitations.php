<?php

namespace App\Console\Commands;

use App\Models\Publication;
use App\Models\User;
use App\Notifications\AiJobFinished;
use App\Support\CitationUpdater;
use App\Support\ScholarProfileUpdater;
use App\Support\VenueResearcher;
use Illuminate\Console\Command;

class RefreshCitations extends Command
{
    protected $signature = 'publications:refresh {--stale=6 : Skip publications checked within this many days}';

    protected $description = 'Update citation counts and details of published work';

    public function handle(CitationUpdater $updater, ScholarProfileUpdater $profile): int
    {
        if (! VenueResearcher::configured()) {
            $this->warn('AI is not set up; skipping.');

            return self::SUCCESS;
        }
        // One read of each Scholar profile updates the stats table, the chart and every article.
        foreach (User::whereNotNull('scholar_url')->get() as $user) {
            $run = \App\Models\AiRun::begin($user, 'citations', 'Citation update', 1, route('publications'));
            $run->step('Reading your Google Scholar profile');
            try {
                [$n, $new] = $profile->refresh($user);
                $run->finish();
                $this->info("{$user->email}: {$n} articles updated from Scholar");
                $total = $user->fresh()->scholar_stats['all']['citations'] ?? null;
                $user->notify(new AiJobFinished('Citations updated', ($total !== null ? "{$total} citations in total. " : '')."{$n} articles refreshed from Google Scholar."
                    .($new ? ' New on Scholar: '.implode('; ', array_slice($new, 0, 2)).'.' : ''), 'success', route('publications')));
                continue;
            } catch (\Throwable $e) {
                $run->finish($e->getMessage());
                $this->warn("{$user->email}: {$e->getMessage()} Falling back to one-by-one.");
                $user->notify(new AiJobFinished('Citation update could not read Scholar', $e->getMessage(), 'warning', route('publications')));
            }
        }
        foreach (Publication::where(fn ($q) => $q->whereNull('checked_at')->orWhere('checked_at', '<', now()->subDays((int) $this->option('stale'))))->get() as $pub) {
            try {
                $updater->refresh($pub);
                $this->info("{$pub->title}: {$pub->citations}");
            } catch (\Throwable $e) {
                $this->error("{$pub->title}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
