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

#[Description('Add a deadline, optionally linked to a paper.')]
class AddDeadline extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $u = $this->owner($request);
        $paper = $request->get('paper_id') ? $u->papers()->find($request->get('paper_id')) : null;
        $d = $u->deadlines()->create(['title' => (string) $request->get('title'), 'due_on' => $request->get('due_on'), 'paper_id' => $paper?->id]);

        return Response::text("Added “{$d->title}” on {$d->due_on->toDateString()}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->required(),
            'due_on' => $schema->string()->description('YYYY-MM-DD')->required(),
            'paper_id' => $schema->integer(),
        ];
    }
}
