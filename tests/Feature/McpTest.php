<?php

namespace Tests\Feature;

use App\Mcp\Servers\TrackerServer;
use App\Mcp\Tools\GetPaper;
use App\Mcp\Tools\ListDeadlines;
use App\Mcp\Tools\ListPapers;
use App\Mcp\Tools\ListPublications;
use App\Mcp\Tools\RecordDecision;
use App\Mcp\Tools\RecordSubmission;
use App\Mcp\Tools\SearchVenues;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class McpTest extends TestCase
{
    use RefreshDatabase;

    public function test_agent_tools_read_and_write_the_users_data(): void
    {
        $this->seed(DemoSeeder::class);
        $user = User::where('email', 'demo@example.com')->first();
        $paper = $user->papers()->where('short_title', 'Rural broadband')->first();

        TrackerServer::actingAs($user)->tool(ListPapers::class, [])->assertOk()->assertSee('Ratings Without Context');
        TrackerServer::actingAs($user)->tool(GetPaper::class, ['paper_id' => $paper->id])->assertOk()->assertSee('Information Technology for Development');
        TrackerServer::actingAs($user)->tool(SearchVenues::class, ['search' => 'CHI'])->assertOk()->assertSee('CHI');
        TrackerServer::actingAs($user)->tool(ListDeadlines::class, [])->assertOk()->assertSee('Revision due');
        TrackerServer::actingAs($user)->tool(ListPublications::class, [])->assertOk()->assertSee('142');

        TrackerServer::actingAs($user)->tool(RecordSubmission::class, ['paper_id' => $paper->id, 'venue' => 'Information Technology for Development', 'manuscript_id' => 'ITD-1'])->assertOk();
        $sub = $paper->fresh()->currentSubmission;
        $this->assertSame('ITD-1', $sub->manuscript_id);
        TrackerServer::actingAs($user)->tool(RecordDecision::class, ['submission_id' => $sub->id, 'decision' => 'major', 'revision_due_on' => '2027-03-01'])->assertOk();
        $this->assertSame('revision', $paper->fresh()->stage);
    }

    public function test_remote_endpoint_needs_a_token(): void
    {
        $this->postJson('/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertStatus(401);
    }
}
