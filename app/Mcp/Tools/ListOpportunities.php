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
#[Description('List calls for papers found by the daily calls for papers scan, best fit first.')]
class ListOpportunities extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $rows = $this->owner($request)->opportunities()->where('status', $request->get('status', 'new'))
            ->where(fn ($w) => $w->whereNull('deadline_on')->orWhere('deadline_on', '>=', today()))->orderByDesc('fit')->limit(30)->get();

        return Response::json($rows->map(fn ($o) => ['title' => $o->title, 'kind' => $o->kind, 'venue' => $o->venue, 'outlet' => $o->outlet,
            'deadline_on' => $o->deadline_on?->toDateString(), 'fit' => $o->fit, 'why' => $o->why, 'url' => $o->url])->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return ['status' => $schema->string()->enum(['new', 'saved', 'dismissed'])->description('Defaults to new.')];
    }
}
