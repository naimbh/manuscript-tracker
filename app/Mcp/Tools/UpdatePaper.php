<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\PaperFields;
use App\Mcp\Tools\Concerns\ResolvesUser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;

#[IsIdempotent]
#[Description('Change a paper’s details: title, stage, abstract, keywords, method, data, tags, notes, links or preprint. Only the fields you send are changed.')]
class UpdatePaper extends Tool
{
    use PaperFields, ResolvesUser;

    public function handle(Request $request): Response
    {
        $p = $this->owner($request)->papers()->find($request->get('paper_id'));
        if (! $p) {
            return Response::error('No paper with that id.');
        }
        $d = $this->paperData($request);
        if (! $d) {
            return Response::error('Nothing to change.');
        }
        $p->update($d);

        return Response::text("Updated “{$p->title}”: ".implode(', ', array_keys($d)).'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return ['paper_id' => $schema->integer()->required()] + $this->paperSchema($schema);
    }
}
