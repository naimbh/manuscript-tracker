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
#[Description('Get one paper with its abstract, authors, every submission (journal or conference, IDs, dates, decision, notes, reviewer checklist) and the next-submission queue.')]
class GetPaper extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $p = $this->owner($request)->papers()->with(['submissions.revisionItems', 'queue.venue', 'authors'])->find($request->get('paper_id'));
        if (! $p) {
            return Response::error('No paper with that id.');
        }

        return Response::json([
            'id' => $p->id, 'title' => $p->title, 'short_title' => $p->short_title, 'stage' => $p->stage, 'tags' => $p->tags,
            'abstract' => $p->abstract, 'keywords' => $p->keywords, 'method' => $p->method, 'data' => $p->dataset,
            'preprint' => $p->preprint_url, 'notes' => $p->notes,
            'authors' => $p->authors->map(fn ($a) => ['name' => $a->name, 'affiliation' => $a->affiliation, 'orcid' => $a->orcid, 'corresponding' => $a->corresponding, 'roles' => $a->roles]),
            'submissions' => $p->submissions->map(fn ($s) => [
                'id' => $s->id, 'venue' => $s->venue_name, 'manuscript_id' => $s->manuscript_id, 'submitted_on' => $s->submitted_on?->toDateString(),
                'status_since' => $s->status_on?->toDateString(), 'decision' => $s->decision, 'decision_on' => $s->decision_on?->toDateString(),
                'revision_due_on' => $s->revision_due_on?->toDateString(), 'current' => $s->is_current, 'portal' => $s->portal_url, 'note' => $s->note,
                'reviewer_comments' => $s->revisionItems->map(fn ($i) => ['comment' => $i->comment, 'done' => $i->done, 'response' => $i->response]),
            ]),
            'next_venues' => $p->queue->map(fn ($q) => $q->venue->name),
        ]);
    }

    public function schema(JsonSchema $schema): array
    {
        return ['paper_id' => $schema->integer()->description('The paper id from list-papers.')->required()];
    }
}
