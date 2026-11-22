<?php

namespace App\Mcp\Tools;

use App\Mcp\Tools\Concerns\ResolvesUser;
use App\Models\Submission;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;

#[Description('Change a submission: manuscript ID, portal link, submitted date, status (submitted, with_editor, pending = under review, awaiting_decision, returned, or a decision), the date that status began, a note, or whether it is the paper’s current journal. Only the fields you send are changed.')]
class UpdateSubmission extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $s = Submission::whereIn('paper_id', $this->owner($request)->papers()->select('id'))->find($request->get('submission_id'));
        if (! $s) {
            return Response::error('No submission with that id.');
        }
        $d = array_filter([
            'manuscript_id' => $request->get('manuscript_id'), 'portal_url' => $request->get('portal_url'),
            'submitted_on' => $request->get('submitted_on'), 'revision_due_on' => $request->get('revision_due_on'),
        ], fn ($v) => $v !== null);
        if ($status = $request->get('status')) {
            if (! isset(Submission::DECISIONS[$status])) {
                return Response::error('Unknown status.');
            }
            $d['decision'] = $status;
            $d['status_on'] = $request->get('status_on', now()->toDateString());
            $d['decision_on'] = in_array($status, Submission::WAITING, true) ? null : ($request->get('decision_on') ?? $d['status_on']);
        } elseif ($request->get('status_on')) {
            $d['status_on'] = $request->get('status_on');
        }
        if ($note = $request->get('note')) {
            $d['note'] = trim(($s->note ? $s->note."\n" : '').$note);
        }
        if ($request->get('current') === true) {
            Submission::where('paper_id', $s->paper_id)->update(['is_current' => false]);
            $d['is_current'] = true;
        }
        if (! $d) {
            return Response::error('Nothing to change.');
        }
        $s->update($d);
        $paper = $s->paper()->first();
        if ($paper->currentSubmission()->first()?->id === $s->id && $paper->stage !== 'published') {
            $paper->update(['stage' => Submission::stageFor($s->decision)]);
        }

        return Response::text("Updated {$s->venue_name} for “{$paper->title}”: ".implode(', ', array_keys($d)).'.');
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'submission_id' => $schema->integer()->required(),
            'manuscript_id' => $schema->string(),
            'portal_url' => $schema->string(),
            'submitted_on' => $schema->string()->description('YYYY-MM-DD'),
            'status' => $schema->string()->enum(array_keys(Submission::DECISIONS)),
            'status_on' => $schema->string()->description('YYYY-MM-DD, the day the status began. Defaults to today when status is set.'),
            'decision_on' => $schema->string()->description('YYYY-MM-DD, for decisions.'),
            'revision_due_on' => $schema->string()->description('YYYY-MM-DD'),
            'note' => $schema->string()->description('Added to the end of the existing note.'),
            'current' => $schema->boolean()->description('true makes this the paper’s current journal.'),
        ];
    }
}
