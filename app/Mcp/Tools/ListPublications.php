<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesUser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsReadOnly]
#[Description('List published work with venue, year and Google Scholar citation counts, plus the citation summary.')]
class ListPublications extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $u = $this->owner($request);

        return Response::json([
            'summary' => $u->scholar_stats,
            'publications' => $u->publications()->orderByDesc('year')->get()->map(fn ($p) => ['title' => $p->title, 'venue' => $p->citation() ?: $p->publisher,
                'year' => $p->year, 'kind' => $p->kind, 'quartile' => $p->quartile, 'citations' => $p->citations, 'doi' => $p->doi])->values(),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
