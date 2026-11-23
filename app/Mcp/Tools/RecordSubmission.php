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

#[Description('Record that a paper was sent to a journal or conference. Adds it to the user’s journals and conferences if it is new, and makes this the current submission.')]
class RecordSubmission extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $user = $this->owner($request);
        $p = $user->papers()->find($request->get('paper_id'));
        if (! $p) {
            return Response::error('No paper with that id.');
        }
        $name = trim((string) $request->get('venue'));
        if ($name === '') {
            return Response::error('Give the journal or conference name.');
        }
        $venue = $user->venues()->where('name', $name)->first() ?? $user->venues()->create(['name' => $name, 'kind' => $request->get('kind', 'journal')]);
        $p->submissions()->update(['is_current' => false]);
        $s = $p->submissions()->create([
            'venue_id' => $venue->id, 'venue_name' => $venue->name, 'manuscript_id' => $request->get('manuscript_id'),
            'submitted_on' => $request->get('submitted_on', now()->toDateString()), 'portal_url' => $request->get('portal_url') ?? $venue->portal_url,
            'decision' => 'submitted', 'is_current' => true, 'note' => $request->get('note'),
        ]);
        $p->queue()->where('venue_id', $venue->id)->delete();
        $p->update(['stage' => 'submitted']);

        return Response::text("Recorded submission #{$s->id} of “{$p->title}” to {$venue->name}.");
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'paper_id' => $schema->integer()->required(),
            'venue' => $schema->string()->description('Journal or conference name.')->required(),
            'kind' => $schema->string()->enum(['journal', 'conference'])->description('Only used when the journal or conference is new.'),
            'manuscript_id' => $schema->string(),
            'submitted_on' => $schema->string()->description('YYYY-MM-DD. Defaults to today.'),
            'portal_url' => $schema->string(),
            'note' => $schema->string(),
        ];
    }
}
