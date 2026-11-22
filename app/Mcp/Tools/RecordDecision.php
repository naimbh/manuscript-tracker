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

#[Description('Record an editor’s decision on a submission or update its status: submitted, with_editor, pending (under review), awaiting_decision, returned (to the author for fixes), desk_reject, reject, major, minor, accept or withdrawn.')]
class RecordDecision extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $user = $this->owner($request);
        $s = \App\Models\Submission::whereIn('paper_id', $user->papers()->pluck('id'))->find($request->get('submission_id'));
        if (! $s) {
            return Response::error('No submission with that id.');
        }
        $decision = $request->get('decision');
        if (! isset(\App\Models\Submission::DECISIONS[$decision])) {
            return Response::error('Unknown decision.');
        }
        $date = $request->get('date', now()->toDateString());
        $s->update(['decision' => $decision, 'status_on' => $date, 'decision_on' => in_array($decision, \App\Models\Submission::WAITING, true) ? null : $date,
            'revision_due_on' => $request->get('revision_due_on') ?? $s->revision_due_on]);
        if ($s->is_current || $s->paper->currentSubmission?->id === $s->id) {
            $s->paper->update(['stage' => \App\Models\Submission::stageFor($decision)]);
        }
        if ($note = $request->get('note')) {
            $s->update(['note' => trim(($s->note ? $s->note."\n" : '').$note)]);
        }

        return Response::text("{$s->venue_name}: ".\App\Models\Submission::DECISIONS[$decision].' on '.$date.'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'submission_id' => $schema->integer()->description('From get-paper.')->required(),
            'decision' => $schema->string()->enum(array_keys(\App\Models\Submission::DECISIONS))->required(),
            'date' => $schema->string()->description('YYYY-MM-DD. Defaults to today.'),
            'revision_due_on' => $schema->string()->description('YYYY-MM-DD, for revisions.'),
            'note' => $schema->string()->description('Appended to the submission notes.'),
        ];
    }
}
