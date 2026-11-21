<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_page_renders_for_a_signed_in_user(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::first();
        $paper = $user->papers()->first();
        $this->actingAs($user);
        foreach (['/dashboard', '/papers', "/papers/{$paper->id}", "/papers/{$paper->id}?tab=submissions", "/papers/{$paper->id}?tab=files", "/papers/{$paper->id}?tab=overview",
            '/venues', '/venues?tab=journal', '/deadlines', '/published', '/opportunities', '/settings/data', '/settings/ai', '/settings/research', '/settings/agents', '/opportunities?show=history', '/export.json'] as $url) {
            $res = $this->get($url);
            $this->assertSame(200, $res->status(), $url.' → '.mb_substr($res->exception?->getMessage() ?? '', 0, 600));
        }
        $this->get('/calendar/'.$user->calendarToken().'.ics')->assertOk()->assertSee('BEGIN:VCALENDAR');
    }
}
