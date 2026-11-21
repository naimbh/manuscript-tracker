<?php

namespace App\Support;

use App\Models\Opportunity;
use App\Models\Scan;
use App\Models\User;
use App\Notifications\AiJobFinished;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Finds calls for papers that fit the user's interests in two steps:
 * 1. collect candidates from WikiCFP RSS feeds for the interest keywords;
 * 2. ask Claude, with web search, to check them on official pages, add
 *    journal special issues and archival tracks it finds, and rank them.
 */
class OpportunityScanner
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'items' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'title' => ['type' => 'string', 'description' => 'Name of the call, e.g. "Special issue: Platform labor in the Global South".'],
                        'kind' => ['type' => 'string', 'enum' => ['conference', 'special_issue', 'journal', 'workshop']],
                        'venue' => ['type' => ['string', 'null'], 'description' => 'Conference or journal name.'],
                        'publisher' => ['type' => ['string', 'null']],
                        'outlet' => ['type' => ['string', 'null'], 'description' => 'Where accepted work appears and its standing, e.g. "ACM Digital Library, CORE A" or "SJR Q1".'],
                        'deadline_on' => ['type' => ['string', 'null'], 'description' => 'Submission deadline, YYYY-MM-DD.'],
                        'event' => ['type' => ['string', 'null'], 'description' => 'Conference dates and place, or issue date.'],
                        'url' => ['type' => 'string', 'description' => 'Official call-for-papers URL.'],
                        'summary' => ['type' => 'string', 'description' => 'One or two sentences on what the call wants.'],
                        'why' => ['type' => 'string', 'description' => 'One sentence on why it fits these interests or papers.'],
                        'fit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 5],
                    ],
                    'required' => ['title', 'kind', 'venue', 'publisher', 'outlet', 'deadline_on', 'event', 'url', 'summary', 'why', 'fit'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['items'],
        'additionalProperties' => false,
    ];

    public function __construct(private VenueResearcher $ai) {}

    public static function interestsFor(User $user): string
    {
        if (filled($user->interests)) {
            return $user->interests;
        }
        $kw = $user->papers()->pluck('keywords')->filter()->flatMap(fn ($k) => preg_split('/[;,]/', $k))->map(fn ($k) => trim($k))->filter()->unique()->take(15);

        return $kw->isNotEmpty() ? $kw->implode(', ') : 'information systems, HCI, CSCW, future of work';
    }

    public function scan(User $user, string $trigger = 'manual', ?Scan $scan = null): Scan
    {
        $scan ??= $user->scans()->create(['trigger' => $trigger]);
        $scan->update(['provider' => VenueResearcher::status()]);
        $run = \App\Models\AiRun::begin($user, 'scan', $trigger === 'daily' ? 'Daily opportunity scan' : 'Opportunity scan', 3, route('opportunities'));

        try {
            $interests = self::interestsFor($user);
            $run->step('Reading WikiCFP feeds for your interests', 0);
            $candidates = $this->wikicfp($interests);
            $run->step('The AI is checking '.count($candidates).' WikiCFP candidates and searching publisher pages', 1);
            $scan->update(['candidates' => count($candidates)]);

            $known = $user->opportunities()->latest()->limit(60)->pluck('url')->implode("\n");
            $papers = $user->papers()->whereNotIn('stage', ['published', 'shelved'])->get()
                ->map(fn ($p) => '- '.$p->title.($p->method ? " ({$p->method})" : ''))->implode("\n");
            $list = collect($candidates)->map(fn ($c) => "- {$c['title']} | {$c['link']}")->implode("\n");
            $today = now()->toDateString();

            $prompt = "Today is {$today}. Find good, open opportunities for a researcher to submit papers.\n"
                ."Research interests: {$interests}\n"
                .($papers ? "Papers in progress:\n{$papers}\n" : '')
                ."\nOnly include calls that (a) have a submission deadline after today, and (b) lead to archival publication: "
                ."conference proceedings (ACM, IEEE, AIS eLibrary, Springer and similar), journal special issues, or journals with open calls. "
                ."Leave out events without proceedings, predatory or pay-to-publish outlets, and anything you cannot confirm on an official page.\n"
                .($list ? "\nCandidates from WikiCFP (check each on its official site; keep only good fits):\n{$list}\n" : '')
                ."\nAlso search the web for journal special issues and conference tracks in these areas that the candidates miss, "
                ."for example on Taylor & Francis, SAGE, Wiley, Elsevier, Springer, ACM and AIS call pages.\n"
                .($known ? "\nAlready shown before (do not repeat):\n{$known}\n" : '')
                ."\nReturn up to 12 items, best fit first. Rate fit from 1 (weak) to 5 (excellent).";

            $data = $this->ai->ask($prompt, self::SCHEMA, 420);
            $run->step('Saving the calls that fit', 2);
            $found = 0;
            foreach (array_slice($data['items'] ?? [], 0, 12) as $item) {
                $url = is_string($item['url'] ?? null) && preg_match('#^https?://#i', $item['url']) ? mb_substr($item['url'], 0, 500) : null;
                $title = is_string($item['title'] ?? null) ? Str::limit(trim($item['title']), 250, '') : null;
                if (! $url || ! $title) {
                    continue;
                }
                $deadline = $this->date($item['deadline_on'] ?? null);
                if ($deadline && $deadline->lt(today())) {
                    continue;
                }
                $opp = $user->opportunities()->firstOrCreate(['url_hash' => hash('sha256', rtrim(strtolower($url), '/'))], [
                    'scan_id' => $scan->id,
                    'title' => $title,
                    'kind' => in_array($item['kind'] ?? '', ['conference', 'special_issue', 'journal', 'workshop'], true) ? $item['kind'] : 'conference',
                    'venue' => $this->str($item['venue'] ?? null),
                    'publisher' => $this->str($item['publisher'] ?? null),
                    'outlet' => $this->str($item['outlet'] ?? null),
                    'deadline_on' => $deadline?->toDateString(),
                    'event' => $this->str($item['event'] ?? null),
                    'url' => $url,
                    'summary' => $this->str($item['summary'] ?? null, 800),
                    'why' => $this->str($item['why'] ?? null, 500),
                    'fit' => max(1, min(5, (int) ($item['fit'] ?? 3))),
                ]);
                $found += $opp->wasRecentlyCreated ? 1 : 0;
            }
            $scan->update(['status' => 'done', 'found' => $found, 'finished_at' => now()]);
            $run->note("Saved {$found} new of ".count($data['items'] ?? []).' returned');
            $run->finish();
            $scan->update(['log' => $run->fresh()->log]);
            $user->notify(new AiJobFinished('Opportunity scan finished',
                $found ? "{$found} new ".Str::plural('call', $found).' that fit your interests.' : 'No new calls this time.', 'success', route('opportunities')));
        } catch (\Throwable $e) {
            $scan->update(['status' => 'failed', 'error' => Str::limit($e->getMessage(), 600), 'finished_at' => now()]);
            $run->finish($e->getMessage());
            $scan->update(['log' => $run->fresh()->log]);
            $user->notify(new AiJobFinished('Opportunity scan failed', Str::limit($e->getMessage(), 200), 'danger', route('opportunities')));
        }

        return $scan->fresh();
    }

    /** Candidate calls from WikiCFP's public RSS feed, one search per interest keyword. */
    private function wikicfp(string $interests): array
    {
        $out = [];
        $terms = collect(preg_split('/[;,]/', $interests))->map(fn ($t) => trim($t))->filter()->take(6);
        foreach ($terms as $term) {
            try {
                $xml = Http::timeout(15)->withHeaders(['User-Agent' => 'ManuscriptTracker/1.0'])
                    ->get('http://www.wikicfp.com/cfp/rss', ['cat' => strtolower($term)])->body();
                $feed = @simplexml_load_string($xml);
                foreach ($feed?->channel?->item ?? [] as $item) {
                    $out[(string) $item->link] = ['title' => Str::limit(trim((string) $item->title), 200), 'link' => (string) $item->link];
                }
            } catch (\Throwable) {
            }
        }

        return array_slice(array_values($out), 0, 40);
    }

    private function str($v, int $max = 255): ?string
    {
        return is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
    }

    private function date($v): ?Carbon
    {
        if (! is_string($v) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $v)) {
            return null;
        }
        try {
            return Carbon::createFromFormat('Y-m-d', $v)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}
