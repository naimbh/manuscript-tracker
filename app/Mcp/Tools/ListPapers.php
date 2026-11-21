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
#[Description('List the user’s papers with stage, current venue, manuscript ID, decision and days in review. Filter by stage or search text.')]
class ListPapers extends Tool
{
    use ResolvesUser;

    public function handle(Request $request): Response
    {
        $q = $this->owner($request)->papers()->with('currentSubmission')->latest('updated_at');
        if ($stage = $request->get('stage')) {
            $q->where('stage', $stage);
        }
        if ($s = $request->get('search')) {
            $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhere('short_title', 'like', "%{$s}%")->orWhere('keywords', 'like', "%{$s}%"));
        }

        return Response::json($q->get()->map(fn ($p) => [
            'id' => $p->id, 'title' => $p->title, 'short_title' => $p->short_title, 'stage' => $p->stage, 'tags' => $p->tags,
            'current_venue' => $p->currentSubmission?->venue_name, 'manuscript_id' => $p->currentSubmission?->manuscript_id,
            'decision' => $p->currentSubmission?->decision, 'days_in_review' => $p->currentSubmission?->daysInReview(),
        ])->values());
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'stage' => $schema->string()->enum(array_keys(\App\Models\Paper::STAGES))->description('Only papers at this stage.'),
            'search' => $schema->string()->description('Text to look for in titles and keywords.'),
        ];
    }
}
