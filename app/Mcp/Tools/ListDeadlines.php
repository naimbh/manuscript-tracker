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
#[Description('List upcoming deadlines with days left, including revision due dates.')]
class ListDeadlines extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $u = $this->owner($request);
        $rows = $u->deadlines()->with('paper')->where('done', false)->get()->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on->toDateString(), 'days_left' => $d->daysLeft(), 'paper' => $d->paper?->displayTitle()]);
        $rev = \App\Models\Submission::whereIn('paper_id', $u->papers()->pluck('id'))->whereIn('decision', ['major', 'minor'])->whereNotNull('revision_due_on')->with('paper')->get()
            ->map(fn ($s) => ['title' => 'Revision due: '.$s->venue_name, 'due_on' => $s->revision_due_on->toDateString(), 'days_left' => (int) now()->startOfDay()->diffInDays($s->revision_due_on, false), 'paper' => $s->paper->displayTitle()]);

        return Response::json($rows->concat($rev)->sortBy('due_on')->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [];
    }
}
