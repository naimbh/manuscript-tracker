<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\PaperFields;
use App\Mcp\Tools\Concerns\ResolvesUser;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Add a new paper to the tracker. Check list-papers first so the paper is not added twice. Stage defaults to drafting.')]
class AddPaper extends Tool
{
    use PaperFields, ResolvesUser;

    public function handle(Request $request): Response
    {
        $d = $this->paperData($request);
        if (trim((string) ($d['title'] ?? '')) === '') {
            return Response::error('Give the paper title.');
        }
        $p = $this->owner($request)->papers()->create($d + ['stage' => 'drafting']);

        return Response::text("Added paper #{$p->id}: “{$p->title}” ({$p->stage}).");
    }

    public function schema(JsonSchema $schema): array
    {
        $s = $this->paperSchema($schema);
        $s['title'] = $schema->string()->required();

        return $s;
    }
}
