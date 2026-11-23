<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Support\Backup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;
use ZipArchive;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private function sampleUser(): User
    {
        $user = User::factory()->create(['name' => 'Ada Lovelace']);
        $user->forceFill(['interests' => 'Information systems', 'scholar_url' => 'https://scholar.google.com/citations?user=abc', 'mcp_token' => 'secret-mcp-token'])->save();
        $user->calendarToken();
        $venue = $user->venues()->create(['name' => 'Test Journal of Backups', 'kind' => 'journal']);
        $paper = $user->papers()->create(['title' => 'A paper about backups', 'stage' => 'submitted']);
        $paper->submissions()->create(['venue_id' => $venue->id, 'venue_name' => $venue->name, 'status' => 'under_review', 'submitted_on' => '2026-10-01']);
        $user->deadlines()->create(['paper_id' => $paper->id, 'title' => 'Revise', 'due_on' => '2026-12-01']);
        Storage::disk('local')->put('papers/'.$paper->id.'/stored.pdf', '%PDF-1.4 backup test');
        $paper->files()->create(['kind' => 'manuscript', 'label' => 'First draft', 'path' => 'papers/'.$paper->id.'/stored.pdf',
            'original_name' => 'Draft one.pdf', 'mime' => 'application/pdf', 'size' => 20]);
        $user->publications()->create(['title' => 'A published backup paper', 'year' => 2025, 'paper_id' => $paper->id, 'sources' => ['scholar']]);
        $user->opportunities()->create(['title' => 'Call on backups', 'url' => 'https://example.com/cfp', 'url_hash' => hash('sha256', 'https://example.com/cfp'), 'deadline_on' => '2027-01-15']);
        Setting::put('site.name', 'Lab Desk');
        Setting::put('openai.key', 'sk-secret-key');
        Setting::put('ai.claude_token', 'secret-claude-token');

        return $user;
    }

    public function test_v2_export_has_the_new_keys_and_no_secrets(): void
    {
        Storage::fake('local');
        $user = $this->sampleUser();

        $res = $this->actingAs($user)->get('/export.json')->assertOk();
        $data = json_decode($res->getContent(), true);

        $this->assertSame(2, $data['version']);
        foreach (['profile', 'venues', 'papers', 'deadlines', 'publications', 'opportunities', 'settings'] as $key) {
            $this->assertArrayHasKey($key, $data);
        }
        $this->assertSame(['name', 'email', 'interests', 'scholar_url', 'scholar_stats', 'scholar_checked_at'], array_keys($data['profile']));
        $this->assertSame('A paper about backups', $data['publications'][0]['paper']);
        $this->assertArrayNotHasKey('paper_id', $data['publications'][0]);
        $this->assertArrayNotHasKey('scan_id', $data['opportunities'][0]);
        $this->assertSame('2027-01-15', $data['opportunities'][0]['deadline_on']);
        $this->assertSame(['site.name' => 'Lab Desk'], $data['settings']);
        $this->assertSame(['kind' => 'manuscript', 'label' => 'First draft', 'name' => 'Draft one.pdf', 'mime' => 'application/pdf', 'size' => 20], $data['papers'][0]['files'][0]);

        $raw = $res->getContent();
        foreach (['sk-secret-key', 'secret-claude-token', 'secret-mcp-token', $user->calendar_token, $user->password, 'openai.key', 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw);
        }
    }

    public function test_zip_backup_round_trip_restores_files_and_records(): void
    {
        Storage::fake('local');
        $user = $this->sampleUser();

        $res = $this->actingAs($user)->get('/export.zip')->assertOk();
        $zipPath = $res->getFile()->getPathname();
        $copy = tempnam(sys_get_temp_dir(), 'bk');
        copy($zipPath, $copy);

        $zip = new ZipArchive;
        $this->assertTrue($zip->open($copy));
        $data = json_decode($zip->getFromName('backup.json'), true);
        $this->assertSame('files/1-Draft one.pdf', $data['papers'][0]['files'][0]['path']);
        $this->assertSame('%PDF-1.4 backup test', $zip->getFromName('files/1-Draft one.pdf'));
        $zip->close();

        $other = User::factory()->create(['name' => 'Grace Hopper']);
        Volt::actingAs($other)->test('settings.data')
            ->set('backup', UploadedFile::fake()->createWithContent('backup.zip', file_get_contents($copy)))
            ->call('import')->assertHasNoErrors();

        $paper = $other->papers()->with('files', 'submissions')->firstOrFail();
        $this->assertSame('A paper about backups', $paper->title);
        $this->assertSame('Test Journal of Backups', $paper->submissions->first()->venue->name);
        $file = $paper->files->first();
        $this->assertSame('Draft one.pdf', $file->original_name);
        $this->assertStringStartsWith('papers/'.$paper->id.'/', $file->path);
        Storage::disk('local')->assertExists($file->path);
        $this->assertSame('%PDF-1.4 backup test', Storage::disk('local')->get($file->path));
        $this->actingAs($other)->get(route('files.download', $file))->assertOk();

        $this->assertSame($paper->id, $other->publications()->first()->paper_id);
        $this->assertSame(1, $other->opportunities()->count());
        $other->refresh();
        $this->assertSame('Grace Hopper', $other->name);
        $this->assertSame('Information systems', $other->interests);

        // Importing again skips calls for papers that already exist.
        $n = Backup::importFile($other, $copy);
        $this->assertSame(0, $n['opportunities']);
        $this->assertSame(1, $other->opportunities()->count());
        @unlink($copy);
    }

    public function test_v1_json_backup_still_imports(): void
    {
        $user = User::factory()->create();
        $v1 = [
            'app' => 'manuscript-tracker', 'version' => 1, 'exported_at' => '2026-11-01T00:00:00+00:00',
            'venues' => [['name' => 'Old Venue', 'kind' => 'journal']],
            'papers' => [[
                'title' => 'Old paper', 'stage' => 'drafting', 'preprint_on' => null,
                'submissions' => [['venue_name' => 'Old Venue', 'status' => 'submitted', 'venue' => 'Old Venue', 'revision_items' => [['comment' => 'Fix the intro', 'done' => false]]]],
                'queue' => ['Old Venue'], 'authors' => [['name' => 'A. Author']],
                'deadlines' => [['title' => 'Send', 'due_on' => '2026-12-24', 'done' => false]],
                'files' => [['kind' => 'manuscript', 'label' => null, 'name' => 'old.pdf']],
            ]],
            'deadlines' => [['title' => 'Loose deadline', 'due_on' => '2026-12-31', 'done' => false]],
        ];
        $path = tempnam(sys_get_temp_dir(), 'v1');
        file_put_contents($path, json_encode($v1));

        $n = Backup::importFile($user, $path);
        @unlink($path);

        $this->assertSame(1, $n['papers']);
        $paper = $user->papers()->firstOrFail();
        $this->assertSame('Old paper', $paper->title);
        $this->assertSame('Old Venue', $paper->submissions()->first()->venue->name);
        $this->assertSame(1, $paper->queue()->count());
        $this->assertSame(0, $paper->files()->count());
        $this->assertSame(2, $user->deadlines()->count());
        $this->assertSame('Imported 1 paper, 1 new venue and 1 deadline.', Backup::summary($n));
    }
}
