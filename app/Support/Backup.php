<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use ZipArchive;

class Backup
{
    private const STRIP = ['id', 'user_id', 'paper_id', 'venue_id', 'submission_id', 'created_at', 'updated_at'];

    private const PROFILE = ['name', 'email', 'interests', 'scholar_url', 'scholar_stats', 'scholar_checked_at'];

    /**
     * The full backup (version 2). With $files, each paper file gets a "path" inside the zip,
     * and $files is filled with zip path => path on the local disk.
     */
    public static function export(User $user, ?array &$files = null): array
    {
        $withFiles = func_num_args() > 1;
        $files = [];
        $venues = $user->venues()->get();
        $vmap = $venues->pluck('name', 'id');
        $papers = $user->papers()->with(['submissions.revisionItems', 'queue', 'authors', 'deadlines', 'files'])->get();
        $titles = $papers->pluck('title', 'id');
        $disk = Storage::disk('local');
        $date = fn ($d) => $d?->toDateString();

        $fileEntries = [];
        foreach ($papers->flatMap->files as $f) {
            $entry = ['kind' => $f->kind, 'label' => $f->label, 'name' => $f->original_name, 'mime' => $f->mime, 'size' => (int) $f->size];
            if ($withFiles && $f->path && $disk->exists($f->path)) {
                $entry['path'] = 'files/'.(count($files) + 1).'-'.self::safeName($f->original_name);
                $files[$entry['path']] = $disk->path($f->path);
            }
            $fileEntries[$f->id] = $entry;
        }

        return [
            'app' => 'manuscript-tracker',
            'version' => 2,
            'exported_at' => now()->toIso8601String(),
            'profile' => collect(self::PROFILE)->mapWithKeys(fn ($f) => [$f => $user->{$f} instanceof \DateTimeInterface ? $user->{$f}->toIso8601String() : $user->{$f}])->all(),
            'venues' => $venues->map(fn ($v) => array_merge(Arr::except($v->toArray(), self::STRIP), ['deadline_on' => $date($v->deadline_on)]))->values(),
            'papers' => $papers->map(fn ($p) => array_merge(Arr::except($p->only(array_keys($p->getAttributes())), self::STRIP), [
                'preprint_on' => $date($p->preprint_on),
                'started_on' => $date($p->started_on),
                'submissions' => $p->submissions->map(fn ($s) => array_merge(Arr::except($s->getAttributes(), self::STRIP), [
                    'venue' => $vmap[$s->venue_id] ?? null,
                    'revision_items' => $s->revisionItems->map(fn ($r) => Arr::except($r->getAttributes(), [...self::STRIP, 'position']))->values(),
                ]))->values(),
                'queue' => $p->queue->map(fn ($q) => $vmap[$q->venue_id] ?? null)->filter()->values(),
                'authors' => $p->authors->map(fn ($a) => Arr::except($a->toArray(), [...self::STRIP, 'position']))->values(),
                'deadlines' => $p->deadlines->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on->toDateString(), 'done' => $d->done])->values(),
                'files' => $p->files->map(fn ($f) => $fileEntries[$f->id])->values(),
            ]))->values(),
            'deadlines' => $user->deadlines()->whereNull('paper_id')->get()
                ->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on->toDateString(), 'done' => $d->done])->values(),
            'publications' => $user->publications()->orderBy('id')->get()
                ->map(fn ($p) => array_merge(Arr::except($p->attributesToArray(), ['id', 'user_id', 'paper_id']), ['paper' => $titles[$p->paper_id] ?? null]))->values(),
            'opportunities' => $user->opportunities()->orderBy('id')->get()
                ->map(fn ($o) => array_merge(Arr::except($o->attributesToArray(), ['id', 'user_id', 'scan_id']), ['deadline_on' => $date($o->deadline_on)]))->values(),
            'settings' => (object) Setting::query()->whereNotIn('key', Setting::SECRET)->get()
                ->filter(fn ($s) => isset(Setting::CONFIG[$s->key]) && ! $s->encrypted)
                ->mapWithKeys(fn ($s) => [$s->key => $s->plain()])->all(),
        ];
    }

    /** Writes a zip with backup.json and the uploaded files to a temporary path and returns it. */
    public static function zip(User $user): string
    {
        $data = self::export($user, $files);
        $path = tempnam(sys_get_temp_dir(), 'backup');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('backup.json', self::json($data));
        foreach ($files as $name => $source) {
            $zip->addFile($source, $name);
        }
        $zip->close();

        return $path;
    }

    public static function json(array $data): string
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private static function safeName(?string $name): string
    {
        $name = trim(str_replace(['/', '\\', "\0"], '-', (string) $name), ' .');

        return $name === '' ? 'file' : $name;
    }

    /** Imports a backup file: v1 or v2 JSON, or a v2 zip. Returns counts. */
    public static function importFile(User $user, string $path): array
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) === true) {
            try {
                $json = $zip->getFromName('backup.json');
                abort_unless($json !== false, 422, 'This zip has no backup.json, so it is not a Manuscript Tracker backup.');

                return self::import($user, self::decode($json), $zip);
            } finally {
                $zip->close();
            }
        }

        return self::import($user, self::decode((string) file_get_contents($path)));
    }

    private static function decode(string $json): array
    {
        $data = json_decode($json, true);
        abort_unless(is_array($data) && ($data['app'] ?? null) === 'manuscript-tracker', 422, 'This is not a Manuscript Tracker backup.');

        return $data;
    }

    /** Adds the backup's records to the account. Nothing is deleted. Returns counts. */
    public static function import(User $user, array $data, ?ZipArchive $zip = null): array
    {
        abort_unless(($data['app'] ?? null) === 'manuscript-tracker', 422, 'This is not a Manuscript Tracker backup.');
        $n = ['papers' => 0, 'venues' => 0, 'files' => 0, 'publications' => 0, 'opportunities' => 0, 'deadlines' => 0, 'profile' => 0, 'settings' => 0];
        $stored = [];

        try {
            DB::transaction(function () use ($user, $data, $zip, &$n, &$stored) {
                $venueIds = [];
                foreach ($data['venues'] ?? [] as $v) {
                    $venue = $user->venues()->firstOrCreate(['name' => $v['name']], Arr::except($v, ['name', ...self::STRIP]));
                    $venueIds[$v['name']] = $venue->id;
                    $n['venues'] += $venue->wasRecentlyCreated ? 1 : 0;
                }
                foreach ($data['papers'] ?? [] as $p) {
                    $paper = $user->papers()->create(Arr::except($p, ['submissions', 'queue', 'authors', 'deadlines', 'files', ...self::STRIP]));
                    $n['papers']++;
                    foreach ($p['submissions'] ?? [] as $s) {
                        $sub = $paper->submissions()->create(array_merge(Arr::except($s, ['venue', 'revision_items', ...self::STRIP]),
                            ['venue_id' => $venueIds[$s['venue'] ?? ''] ?? null]));
                        foreach (array_values($s['revision_items'] ?? []) as $i => $r) {
                            $sub->revisionItems()->create(Arr::except($r, self::STRIP) + ['position' => $i]);
                        }
                    }
                    foreach (array_values($p['queue'] ?? []) as $i => $name) {
                        if (isset($venueIds[$name])) {
                            $paper->queue()->create(['venue_id' => $venueIds[$name], 'position' => $i]);
                        }
                    }
                    foreach (array_values($p['authors'] ?? []) as $i => $a) {
                        $paper->authors()->create(Arr::except($a, self::STRIP) + ['position' => $i]);
                    }
                    foreach ($p['deadlines'] ?? [] as $d) {
                        $user->deadlines()->create(Arr::only($d, ['title', 'due_on', 'done']) + ['paper_id' => $paper->id]);
                    }
                    foreach ($p['files'] ?? [] as $f) {
                        if (! $zip || empty($f['path']) || ($contents = $zip->getFromName($f['path'])) === false) {
                            continue;
                        }
                        $name = $f['name'] ?? basename($f['path']);
                        $ext = pathinfo($name, PATHINFO_EXTENSION);
                        $path = 'papers/'.$paper->id.'/'.Str::random(40).($ext !== '' ? '.'.strtolower($ext) : '');
                        Storage::disk('local')->put($path, $contents);
                        $stored[] = $path;
                        $paper->files()->create([
                            'kind' => $f['kind'] ?? 'manuscript', 'label' => $f['label'] ?? null, 'path' => $path, 'original_name' => $name,
                            'mime' => $f['mime'] ?? null, 'size' => strlen($contents),
                        ]);
                        $n['files']++;
                    }
                }
                foreach ($data['deadlines'] ?? [] as $d) {
                    $user->deadlines()->create(Arr::only($d, ['title', 'due_on', 'done']));
                    $n['deadlines']++;
                }

                $paperIds = $user->papers()->pluck('id', 'title');
                $existing = $user->publications()->pluck('title')->map(fn ($t) => mb_strtolower(trim($t)))->flip();
                foreach ($data['publications'] ?? [] as $pub) {
                    if (empty($pub['title']) || $existing->has($key = mb_strtolower(trim($pub['title'])))) {
                        continue;
                    }
                    $user->publications()->create(array_merge(Arr::except($pub, ['id', 'user_id', 'paper_id', 'paper']),
                        ['paper_id' => $paperIds[$pub['paper'] ?? ''] ?? null]));
                    $existing[$key] = true;
                    $n['publications']++;
                }

                foreach ($data['opportunities'] ?? [] as $o) {
                    if (empty($o['url'])) {
                        continue;
                    }
                    $hash = $o['url_hash'] ?? hash('sha256', rtrim(strtolower($o['url']), '/'));
                    $opp = $user->opportunities()->firstOrCreate(['url_hash' => $hash], Arr::except($o, ['id', 'user_id', 'scan_id', 'url_hash']));
                    $n['opportunities'] += $opp->wasRecentlyCreated ? 1 : 0;
                }

                // Only fill profile fields that are still empty; never the email or password.
                foreach (Arr::except($data['profile'] ?? [], ['email']) as $field => $value) {
                    if (in_array($field, self::PROFILE, true) && filled($value) && blank($user->{$field})) {
                        $user->{$field} = $value;
                        $n['profile']++;
                    }
                }
                $user->save();

                // Site settings belong to the admin. Only missing, non-secret keys are added.
                if ($user->is_admin) {
                    foreach ($data['settings'] ?? [] as $key => $value) {
                        if (isset(Setting::CONFIG[$key]) && ! in_array($key, Setting::SECRET, true) && is_scalar($value) && ! Setting::whereKey($key)->exists()) {
                            Setting::put($key, (string) $value);
                            $n['settings']++;
                        }
                    }
                }
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($stored);
            throw $e;
        }

        return $n;
    }

    /** A short sentence such as “Imported 2 papers, 3 files and 1 new venue.” */
    public static function summary(array $n): string
    {
        $labels = ['papers' => ['paper', 'papers'], 'files' => ['file', 'files'], 'venues' => ['new venue', 'new venues'],
            'publications' => ['publication', 'publications'], 'opportunities' => ['new call', 'new calls'], 'deadlines' => ['deadline', 'deadlines'],
            'profile' => ['profile field', 'profile fields'], 'settings' => ['setting', 'settings']];
        $parts = collect($labels)->filter(fn ($l, $k) => ($n[$k] ?? 0) > 0)
            ->map(fn ($l, $k) => $n[$k].' '.$l[$n[$k] === 1 ? 0 : 1])->values();

        return $parts->isEmpty() ? 'Nothing new to import.' : 'Imported '.$parts->join(', ', ' and ').'.';
    }
}
