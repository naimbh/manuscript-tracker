<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class Backup
{
    private const STRIP = ['id', 'user_id', 'paper_id', 'venue_id', 'submission_id', 'created_at', 'updated_at'];

    public static function export(User $user): array
    {
        $venues = $user->venues()->get();
        $vmap = $venues->pluck('name', 'id');

        return [
            'app' => 'manuscript-tracker',
            'version' => 1,
            'exported_at' => now()->toIso8601String(),
            'venues' => $venues->map(fn ($v) => Arr::except($v->toArray(), self::STRIP))->values(),
            'papers' => $user->papers()->with(['submissions.revisionItems', 'queue', 'authors', 'deadlines', 'files'])->get()
                ->map(fn ($p) => array_merge(Arr::except($p->only(array_keys($p->getAttributes())), self::STRIP), [
                    'preprint_on' => $p->preprint_on?->toDateString(),
                    'submissions' => $p->submissions->map(fn ($s) => array_merge(Arr::except($s->getAttributes(), self::STRIP), [
                        'venue' => $vmap[$s->venue_id] ?? null,
                        'revision_items' => $s->revisionItems->map(fn ($r) => Arr::except($r->getAttributes(), [...self::STRIP, 'position']))->values(),
                    ]))->values(),
                    'queue' => $p->queue->map(fn ($q) => $vmap[$q->venue_id] ?? null)->filter()->values(),
                    'authors' => $p->authors->map(fn ($a) => Arr::except($a->toArray(), [...self::STRIP, 'position']))->values(),
                    'deadlines' => $p->deadlines->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on->toDateString(), 'done' => $d->done])->values(),
                    'files' => $p->files->map(fn ($f) => ['kind' => $f->kind, 'label' => $f->label, 'name' => $f->original_name])->values(),
                ]))->values(),
            'deadlines' => $user->deadlines()->whereNull('paper_id')->get()
                ->map(fn ($d) => ['title' => $d->title, 'due_on' => $d->due_on->toDateString(), 'done' => $d->done])->values(),
        ];
    }

    /** Adds the backup's records to the account. Returns counts. */
    public static function import(User $user, array $data): array
    {
        abort_unless(($data['app'] ?? null) === 'manuscript-tracker', 422, 'This is not a Manuscript Tracker backup.');
        $n = ['venues' => 0, 'papers' => 0];

        DB::transaction(function () use ($user, $data, &$n) {
            $venueIds = [];
            foreach ($data['venues'] ?? [] as $v) {
                $venue = $user->venues()->firstOrCreate(['name' => $v['name']], Arr::except($v, ['name']));
                $venueIds[$v['name']] = $venue->id;
                $n['venues'] += $venue->wasRecentlyCreated ? 1 : 0;
            }
            foreach ($data['papers'] ?? [] as $p) {
                $paper = $user->papers()->create(Arr::except($p, ['submissions', 'queue', 'authors', 'deadlines', 'files']));
                $n['papers']++;
                foreach ($p['submissions'] ?? [] as $s) {
                    $sub = $paper->submissions()->create(array_merge(Arr::except($s, ['venue', 'revision_items']),
                        ['venue_id' => $venueIds[$s['venue'] ?? ''] ?? null]));
                    foreach (array_values($s['revision_items'] ?? []) as $i => $r) {
                        $sub->revisionItems()->create($r + ['position' => $i]);
                    }
                }
                foreach (array_values($p['queue'] ?? []) as $i => $name) {
                    if (isset($venueIds[$name])) {
                        $paper->queue()->create(['venue_id' => $venueIds[$name], 'position' => $i]);
                    }
                }
                foreach (array_values($p['authors'] ?? []) as $i => $a) {
                    $paper->authors()->create($a + ['position' => $i]);
                }
                foreach ($p['deadlines'] ?? [] as $d) {
                    $user->deadlines()->create($d + ['paper_id' => $paper->id]);
                }
            }
            foreach ($data['deadlines'] ?? [] as $d) {
                $user->deadlines()->create($d);
            }
        });

        return $n;
    }
}
