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
#[Description('Search the venue library: conferences (next edition, deadline, rank, proceedings) and journals (quartile, first decision, acceptance rate, fees).')]
class SearchVenues extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $q = $this->owner($request)->venues();
        if ($k = $request->get('kind')) {
            $q->where('kind', $k);
        }
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('field', 'like', "%{$s}%"));
        }

        return Response::json($q->limit(50)->get()->map(fn ($v) => collect($v->only(['id', 'name', 'kind', 'field', 'publisher', 'rank', 'quartile', 'acceptance_rate', 'first_decision',
            'proceedings', 'next_edition', 'deadline_label', 'deadline_expected', 'review_type', 'word_limit', 'fee', 'preprint_policy', 'website_url']))
            ->put('deadline_on', $v->deadline_on?->toDateString())->filter(fn ($x) => $x !== null && $x !== ''))->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'kind' => $schema->string()->enum(['journal', 'conference']),
            'search' => $schema->string()->description('Name or field, e.g. "CSCW" or "information systems".'),
        ];
    }
}
