<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;

/** Reads the user's Google Scholar profile with Claude and updates stats, per-year counts and each article's "cited by". */
class ScholarProfileUpdater
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'read_ok' => ['type' => 'boolean', 'description' => 'True only if the Google Scholar profile page itself was read.'],
            'since_year' => ['type' => ['integer', 'null'], 'description' => 'The "Since" column year, e.g. 2021.'],
            'citations_all' => ['type' => ['integer', 'null']], 'citations_since' => ['type' => ['integer', 'null']],
            'h_all' => ['type' => ['integer', 'null']], 'h_since' => ['type' => ['integer', 'null']],
            'i10_all' => ['type' => ['integer', 'null']], 'i10_since' => ['type' => ['integer', 'null']],
            'by_year' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['year' => ['type' => 'integer'], 'count' => ['type' => 'integer']],
                'required' => ['year', 'count'], 'additionalProperties' => false], 'description' => 'Citations per year from the bar chart.'],
            'articles' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'title' => ['type' => 'string'], 'cited_by' => ['type' => ['integer', 'null']], 'year' => ['type' => ['integer', 'null']],
                'venue' => ['type' => ['string', 'null']], 'scholar_url' => ['type' => ['string', 'null']]],
                'required' => ['title', 'cited_by', 'year', 'venue', 'scholar_url'], 'additionalProperties' => false]],
        ],
        'required' => ['read_ok', 'since_year', 'citations_all', 'citations_since', 'h_all', 'h_since', 'i10_all', 'i10_since', 'by_year', 'articles'],
        'additionalProperties' => false,
    ];

    public function __construct(private VenueResearcher $ai) {}

    /** Returns [updated articles, new articles found]. */
    public function refresh(User $user): array
    {
        if (! $user->scholar_url) {
            throw new RuntimeException('Add your Google Scholar profile link first.');
        }
        $prompt = "Fetch this Google Scholar profile and copy what it shows: {$user->scholar_url}&view_op=list_works&sortby=pubdate&cstart=0&pagesize=100\n"
            .'Read the "Cited by" table (All and Since columns for Citations, h-index and i10-index), the citations-per-year bar chart, '
            .'and every article in the list with its exact title, "cited by" number, year and venue line. '
            .'Copy the numbers exactly as the page shows them. If the page cannot be read, set read_ok to false and leave the numbers null.';

        $d = $this->ai->ask($prompt, self::SCHEMA, 240);
        if (empty($d['read_ok'])) {
            throw new RuntimeException('Google Scholar could not be read this time. Try again later.');
        }
        $int = fn ($v) => is_int($v) && $v >= 0 ? $v : null;
        $user->forceFill([
            'scholar_stats' => [
                'since' => $int($d['since_year']),
                'all' => ['citations' => $int($d['citations_all']), 'h' => $int($d['h_all']), 'i10' => $int($d['i10_all'])],
                'recent' => ['citations' => $int($d['citations_since']), 'h' => $int($d['h_since']), 'i10' => $int($d['i10_since'])],
                'by_year' => collect($d['by_year'])->filter(fn ($r) => $int($r['year']) && $int($r['count']) !== null)
                    ->mapWithKeys(fn ($r) => [(string) $r['year'] => $r['count']])->sortKeys()->all(),
            ],
            'scholar_checked_at' => now(),
        ])->save();

        $norm = fn ($t) => Str::of($t)->lower()->replaceMatches('/[^a-z0-9]+/', ' ')->trim()->value();
        $pubs = $user->publications()->get()->keyBy(fn ($p) => $norm($p->title));
        $updated = 0;
        $new = [];
        foreach ($d['articles'] as $a) {
            $pub = $pubs[$norm($a['title'])] ?? null;
            if ($pub) {
                $pub->update(['citations' => $int($a['cited_by']), 'checked_at' => now()]);
                $updated++;
            } else {
                $new[] = $a['title'];
            }
        }

        return [$updated, $new];
    }
}
