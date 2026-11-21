<?php

namespace App\Support;

use App\Models\Publication;

/** Refreshes a publication's citation count and details with Claude and web search. */
class CitationUpdater
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'citations' => ['type' => ['integer', 'null'], 'description' => 'Google Scholar "Cited by" count. Null if you could not read it.'],
            'citations_source' => ['type' => ['string', 'null'], 'enum' => ['google_scholar', 'other', null], 'description' => 'Where the count came from.'],
            'doi' => ['type' => ['string', 'null'], 'description' => 'DOI without the https://doi.org/ prefix.'],
            'url' => ['type' => ['string', 'null'], 'description' => 'Publisher page for the article.'],
            'volume' => ['type' => ['string', 'null']],
            'issue' => ['type' => ['string', 'null']],
            'pages' => ['type' => ['string', 'null']],
            'quartile' => ['type' => ['string', 'null'], 'description' => 'Best SJR quartile of the journal, Q1–Q4.'],
            'sources' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['citations', 'citations_source', 'doi', 'url', 'volume', 'issue', 'pages', 'quartile', 'sources'],
        'additionalProperties' => false,
    ];

    public function __construct(private VenueResearcher $ai) {}

    public function refresh(Publication $pub): Publication
    {
        $prompt = 'Update the record of this publication. Today is '.now()->toDateString().".\n"
            ."Title: {$pub->title}\nAuthors: {$pub->authors}\nVenue: {$pub->venue}\nYear: {$pub->year}\n"
            .($pub->scholar_url ? "Google Scholar record: {$pub->scholar_url}\n" : '')
            .($pub->cites_url ? "Google Scholar \"cited by\" list: {$pub->cites_url}\n" : '')
            .($pub->doi ? "DOI: {$pub->doi}\n" : '')
            ."\nFetch the Google Scholar pages above and read the current \"Cited by\" count. If Google Scholar cannot be read, give null for citations rather than a count from another index. "
            .'Also find the DOI and publisher page, and fill in volume, issue, pages and the journal’s SJR quartile if they are missing. Use null for anything you cannot confirm.';

        $d = $this->ai->ask($prompt, self::SCHEMA, 240);
        $str = fn ($v, $max = 255) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
        $updates = array_filter([
            'citations' => is_int($d['citations'] ?? null) && $d['citations'] >= 0 && ($d['citations_source'] ?? null) === 'google_scholar' ? $d['citations'] : null,
            'doi' => $str(preg_replace('#^https?://(dx\.)?doi\.org/#i', '', (string) ($d['doi'] ?? ''))),
            'url' => is_string($d['url'] ?? null) && preg_match('#^https?://#', $d['url']) ? mb_substr($d['url'], 0, 500) : null,
            'volume' => $pub->volume ? null : $str($d['volume'] ?? null, 40),
            'issue' => $pub->issue ? null : $str($d['issue'] ?? null, 40),
            'pages' => $pub->pages ? null : $str($d['pages'] ?? null, 40),
            'quartile' => $pub->quartile ? null : (is_string($d['quartile'] ?? null) && preg_match('/^Q[1-4]$/', $d['quartile']) ? $d['quartile'] : null),
        ], fn ($v) => $v !== null);

        $pub->fill($updates);
        $pub->sources = collect($d['sources'] ?? [])->filter(fn ($u) => is_string($u) && preg_match('#^https?://#', $u))->take(6)->values()->all();
        $pub->checked_at = now();
        $pub->save();

        return $pub;
    }
}
