<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;

#[Name('Manuscript Tracker')]
#[Version('1.0.0')]
#[Instructions('Tools for a researcher’s manuscript tracker: papers and their submissions, editor decisions, deadlines, the user’s journals and conferences, calls for papers and published work. Use list-papers or get-paper before changing anything, and confirm writes with the user.')]
class TrackerServer extends Server
{
    protected array $tools = [
        Tools\ListPapers::class,
        Tools\GetPaper::class,
        Tools\AddPaper::class,
        Tools\UpdatePaper::class,
        Tools\RecordSubmission::class,
        Tools\UpdateSubmission::class,
        Tools\AttachFile::class,
        Tools\RecordDecision::class,
        Tools\ListDeadlines::class,
        Tools\AddDeadline::class,
        Tools\SearchVenues::class,
        Tools\ListOpportunities::class,
        Tools\ListPublications::class,
    ];
}
