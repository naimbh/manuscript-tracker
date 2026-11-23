<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Fictional demo data, so you can try the app (and so the README screenshots
 * show no real manuscripts). Run: php artisan db:seed --class=DemoSeeder
 * Login: demo@example.com / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::where('email', 'demo@example.com')->first();
        if ($user) {
            $user->papers()->delete();
            $user->publications()->delete();
            $user->opportunities()->delete();
            $user->deadlines()->delete();
        } else {
            $user = User::create(['name' => 'Alex Rivera', 'email' => 'demo@example.com', 'password' => Hash::make('password'), 'email_verified_at' => now()]);
        }
        $user->forceFill([
            'interests' => 'platform labor, algorithmic management, future of work, CSCW, information systems',
            'scholar_url' => 'https://scholar.google.com/citations?user=EXAMPLE',
            'scholar_stats' => ['since' => 2021, 'all' => ['citations' => 142, 'h' => 5, 'i10' => 3], 'recent' => ['citations' => 131, 'h' => 5, 'i10' => 3],
                'by_year' => ['2022' => 6, '2023' => 19, '2024' => 34, '2025' => 47, '2026' => 36]],
            'scholar_checked_at' => now()->subDays(2),
        ])->save();

        $v = fn (string $like) => $user->venues()->where('name', 'like', $like.'%')->first()
            ?? $user->venues()->create(['name' => $like, 'kind' => 'journal']);
        $isj = tap($v('Information Systems Journal'), fn ($x) => $x->update(['decision_days' => 120]));
        $icis = $v('ICIS');
        $cscwJ = $v('Computer Supported Cooperative Work');
        $nms = $v('New Media & Society');
        $itd = $v('Information Technology for Development');
        $jit = $v('Journal of Information Technology');
        $wes = $v('Work, Employment and Society');

        $me = ['name' => 'Alex Rivera', 'affiliation' => 'School of Information, Example University', 'email' => 'demo@example.com', 'corresponding' => true, 'position' => 0];
        $co = ['name' => 'Sam Chen', 'affiliation' => 'Department of Sociology, Example College', 'position' => 1];

        // 1. Under review, after one rejection
        $p = $user->papers()->create([
            'title' => 'Ratings Without Context: Algorithmic Scheduling and Worker Autonomy on Food-Delivery Platforms',
            'short_title' => 'Delivery study', 'stage' => 'under_review', 'tags' => ['Thesis'],
            'method' => '42 interviews and 6 months of app-log diaries', 'dataset' => 'Interviews with delivery riders in three cities, 2025',
            'keywords' => 'algorithmic management, platform labor, autonomy, scheduling, gig economy',
            'abstract' => 'Food-delivery platforms assign shifts and orders through scoring systems that riders cannot see. Drawing on 42 interviews and six months of app-log diaries, this paper shows how riders read, test and work around these scores. We find that autonomy is not removed but reallocated: riders keep control over when they log in while losing control over which work they receive. We call this pattern bounded flexibility and discuss what it means for the design of fair scheduling systems.',
        ]);
        $p->submissions()->create(['venue_id' => $icis->id, 'venue_name' => $icis->name, 'manuscript_id' => 'ICIS-2025-0412', 'submitted_on' => now()->subDays(260), 'decision' => 'reject', 'decision_on' => now()->subDays(190), 'portal_url' => 'https://example.com/portal']);
        $p->submissions()->create(['venue_id' => $isj->id, 'venue_name' => $isj->name, 'manuscript_id' => 'ISJ-26-0198', 'submitted_on' => now()->subDays(104), 'status_on' => now()->subDays(96), 'decision' => 'pending', 'is_current' => true, 'portal_url' => 'https://example.com/portal']);
        $p->queue()->create(['venue_id' => $jit->id, 'position' => 0]);
        $p->authors()->create($me + ['roles' => ['Conceptualization', 'Investigation', 'Formal analysis', 'Writing – original draft']]);
        $p->authors()->create($co + ['roles' => ['Validation', 'Writing – review & editing']]);

        // 2. Revising, with a reviewer checklist
        $p = $user->papers()->create([
            'title' => 'Who Maintains the Maintainers? Care Work and Burnout in Open-Source Communities',
            'short_title' => 'Open source', 'stage' => 'revision', 'tags' => ['Preprint'],
            'method' => 'Mixed methods: survey (n = 611) and 24 interviews', 'dataset' => 'Survey of maintainers of 300 packages',
            'keywords' => 'open source, CSCW, care work, burnout',
            'preprint_server' => 'arXiv', 'preprint_url' => 'https://arxiv.org/abs/0000.00000', 'preprint_on' => now()->subDays(150),
            'abstract' => 'Open-source software depends on a small number of maintainers whose work is rarely seen. Combining a survey of 611 maintainers with 24 interviews, we describe the care work that keeps projects alive and show how it turns into burnout.',
        ]);
        $s = $p->submissions()->create(['venue_id' => $cscwJ->id, 'venue_name' => $cscwJ->name, 'manuscript_id' => 'COSU-D-26-00077', 'submitted_on' => now()->subDays(170), 'decision' => 'major', 'decision_on' => now()->subDays(20), 'status_on' => now()->subDays(20), 'revision_due_on' => now()->addDays(40), 'is_current' => true]);
        foreach ([['Clarify how interviewees were selected from the survey sample.', true], ['Report the survey response rate and possible non-response bias.', true], ['Engage with recent work on invisible labor in CSCW.', false], ['Shorten the discussion and sharpen the design implications.', false]] as $i => [$c, $done]) {
            $s->revisionItems()->create(['comment' => $c, 'done' => $done, 'position' => $i]);
        }
        $p->authors()->create($me + ['roles' => ['Conceptualization', 'Methodology', 'Writing – original draft']]);

        // 3. Accepted
        $p = $user->papers()->create([
            'title' => 'Drafted by Machines: How Newsrooms Negotiate Generative AI',
            'short_title' => 'Newsrooms', 'stage' => 'accepted', 'method' => 'Ethnography in two newsrooms', 'keywords' => 'generative AI, journalism, work practice',
        ]);
        $p->submissions()->create(['venue_id' => $nms->id, 'venue_name' => $nms->name, 'manuscript_id' => 'NMS-25-1180', 'submitted_on' => now()->subDays(300), 'decision' => 'accept', 'decision_on' => now()->subDays(12), 'is_current' => true]);
        $p->authors()->create($me);

        // 4. Ready, with a queue
        $p = $user->papers()->create([
            'title' => 'Last-Mile Connectivity and Remote Work in Rural Districts',
            'short_title' => 'Rural broadband', 'stage' => 'ready', 'method' => 'Survey and spatial analysis', 'keywords' => 'ICT4D, broadband, remote work',
        ]);
        $p->queue()->create(['venue_id' => $itd->id, 'position' => 0]);
        $p->queue()->create(['venue_id' => $wes->id, 'position' => 1]);
        $p->authors()->create($me);

        // 5. Drafting
        $user->papers()->create(['title' => 'Trust and Data Quality in Volunteer Science Projects', 'short_title' => 'Citizen science', 'stage' => 'drafting', 'method' => 'Log analysis']);

        // Deadlines
        $user->deadlines()->create(['title' => 'Revision due: Open source', 'due_on' => now()->addDays(40)]);
        $user->deadlines()->create(['title' => 'CHI late-breaking work', 'due_on' => now()->addDays(61)]);
        $user->deadlines()->create(['title' => 'Doctoral consortium application', 'due_on' => now()->addDays(9)]);

        // Published
        foreach ([
            ['title' => 'Scores, Shifts and Silence: A Review of Algorithmic Management Research', 'venue' => 'Journal of Information Technology', 'volume' => '40', 'issue' => '2', 'pages' => '101–124', 'year' => 2025, 'quartile' => 'Q1', 'citations' => 71],
            ['title' => 'Platform Work in the Global South: A Research Agenda', 'venue' => 'Information Technology for Development', 'volume' => '31', 'issue' => '1', 'pages' => '1–19', 'year' => 2024, 'quartile' => 'Q1', 'citations' => 44],
            ['title' => 'Designing for Contestability in Rating Systems', 'kind' => 'conference', 'venue' => 'Proceedings of the ACM on Human-Computer Interaction', 'volume' => '9', 'year' => 2025, 'citations' => 19],
            ['title' => 'Making Work Visible: Care in Open-Source Maintenance', 'kind' => 'preprint', 'venue' => 'arXiv', 'year' => 2026, 'citations' => 8],
            ['title' => 'Everyday Algorithms: Worker Theories of Platform Scoring', 'kind' => 'thesis', 'publisher' => 'Example University', 'year' => 2023],
        ] as $row) {
            $user->publications()->create($row + ['authors' => 'A Rivera', 'scholar_url' => 'https://scholar.google.com/', 'checked_at' => now()->subDays(2)]);
        }

        // Opportunities
        $scan = $user->scans()->create(['status' => 'done', 'trigger' => 'daily', 'found' => 3, 'candidates' => 28, 'created_at' => now()->subHours(3)->subSeconds(45), 'finished_at' => now()->subHours(3)]);
        foreach ([
            ['title' => 'Special issue: Algorithmic Management beyond the Platform', 'kind' => 'special_issue', 'venue' => 'Example Journal of Work and Technology', 'outlet' => 'SJR Q1', 'deadline_on' => now()->addDays(48), 'fit' => 5,
                'summary' => 'Invites empirical work on how algorithmic control spreads from platforms into conventional workplaces.', 'why' => 'Matches the delivery study and its focus on scheduling and autonomy.'],
            ['title' => 'Workshop on Worker-Centred Design', 'kind' => 'workshop', 'venue' => 'Example HCI Conference 2027', 'outlet' => 'Proceedings in the ACM Digital Library', 'deadline_on' => now()->addDays(30), 'fit' => 4,
                'summary' => 'Short papers on designing technology with and for workers.', 'why' => 'A good home for the design implications of the open-source paper.'],
            ['title' => 'Track: Digital Labour and Development', 'kind' => 'conference', 'venue' => 'Example Conference on Information Systems', 'outlet' => 'AIS eLibrary', 'deadline_on' => now()->addDays(95), 'fit' => 3,
                'summary' => 'Full and short papers on digital labour in developing countries.', 'why' => 'Fits the rural broadband paper.'],
        ] as $o) {
            $user->opportunities()->create($o + ['scan_id' => $scan->id, 'url' => 'https://example.com/cfp/'.md5($o['title']), 'url_hash' => md5($o['title']), 'event' => null, 'publisher' => null]);
        }
    }
}
