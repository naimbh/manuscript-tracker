<?php

use App\Models\Author;
use App\Models\Paper;
use App\Models\PaperFile;
use App\Models\Submission;
use App\Models\Venue;
use Flux\Flux;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new class extends Component {
    use WithFileUploads;

    public Paper $paper;

    #[Url(except: 'submissions')]
    public string $tab = 'submissions';

    public array $p = [];
    public array $s = [];
    public ?int $subId = null;
    public array $a = [];
    public ?int $authorId = null;
    public $upload;
    public string $fileKind = 'manuscript';
    public string $fileLabel = '';
    public ?int $fileSubmission = null;
    public ?int $previewId = null;
    public string $comments = '';
    public ?int $checklistFor = null;
    public string $queueVenue = '';

    /** Which pop-up form is built. Forms are only rendered when opened, which keeps the page light. */
    public ?string $modal = null;

    private function openModal(string $name): void
    {
        $this->modal = $name;
        Flux::modal($name)->show();
    }

    public function openUpload(): void
    {
        $this->openModal('file-form');
    }

    private const PAPER_FIELDS = ['title', 'short_title', 'stage', 'started_on', 'abstract', 'method', 'dataset', 'keywords', 'overleaf_url', 'drive_url', 'preprint_server', 'preprint_url', 'preprint_on', 'notes'];

    public function mount(Paper $paper): void
    {
        abort_unless($paper->user_id === auth()->id(), 404);
        $this->paper = $paper;
        if (! in_array($this->tab, ['submissions', 'files'], true)) {
            $this->tab = 'submissions';
        }
    }

    private function changed(string $msg): void
    {
        $this->paper->refresh();
        $this->dispatch('paper-updated');
        Flux::toast(variant: 'success', text: $msg);
    }

    /* ---------- Paper ---------- */
    public function editPaper(): void
    {
        $this->p = collect(self::PAPER_FIELDS)->mapWithKeys(fn ($f) => [$f => in_array($f, ['preprint_on', 'started_on'], true) ? $this->paper->$f?->toDateString() : $this->paper->$f])->all();
        $this->p['tags'] = implode(', ', $this->paper->tags ?? []);
        $this->openModal('paper-form');
    }

    public function savePaper(): void
    {
        $data = $this->validate([
            'p.title' => 'required|string|max:255', 'p.short_title' => 'nullable|string|max:80',
            'p.stage' => 'required|in:'.implode(',', array_keys(Paper::STAGES)),
            'p.abstract' => 'nullable|string|max:10000', 'p.method' => 'nullable|string|max:255', 'p.dataset' => 'nullable|string|max:255',
            'p.keywords' => 'nullable|string|max:255', 'p.overleaf_url' => 'nullable|url|max:255', 'p.drive_url' => 'nullable|url|max:255',
            'p.preprint_server' => 'nullable|string|max:255', 'p.preprint_url' => 'nullable|url|max:255', 'p.preprint_on' => 'nullable|date', 'p.started_on' => 'nullable|date',
            'p.notes' => 'nullable|string|max:10000', 'p.tags' => 'nullable|string|max:500',
        ])['p'];
        $data['tags'] = collect(explode(',', $data['tags'] ?? ''))->map(fn ($t) => trim($t))->filter()->unique(fn ($t) => mb_strtolower($t))->take(12)->values()->all() ?: null;
        $this->paper->update(array_map(fn ($v) => $v === '' ? null : $v, $data));
        Flux::modal('paper-form')->close();
        $this->changed('Paper saved.');
    }

    public function setStage(string $stage): void
    {
        abort_unless(isset(Paper::STAGES[$stage]), 422);
        $this->paper->update(['stage' => $stage]);
        $this->changed('Stage set to '.Paper::STAGES[$stage].'.');
    }

    public function deletePaper(): void
    {
        $this->paper->files->each->delete();
        $this->paper->delete();
        $this->dispatch('paper-deleted');
    }

    /* ---------- Submissions ---------- */
    public function newSubmission(?int $venueId = null): void
    {
        $this->subId = null;
        $v = $venueId ? auth()->user()->venues()->find($venueId) : null;
        $this->s = ['venue_id' => $v?->id ?? '', 'venue_name' => $v?->name ?? '', 'manuscript_id' => '', 'portal_url' => $v?->portal_url ?? '',
            'submitted_on' => today()->toDateString(), 'status_on' => '', 'decision' => 'submitted', 'decision_on' => '', 'revision_due_on' => '', 'note' => '', 'is_current' => true];
        $this->resetValidation();
        $this->openModal('sub-form');
    }

    public function editSubmission(int $id): void
    {
        $sub = $this->paper->submissions()->findOrFail($id);
        $this->subId = $sub->id;
        $this->s = ['venue_id' => $sub->venue_id ?? '', 'venue_name' => $sub->venue_name, 'manuscript_id' => $sub->manuscript_id, 'portal_url' => $sub->portal_url,
            'submitted_on' => $sub->submitted_on?->toDateString(), 'status_on' => $sub->status_on?->toDateString(), 'decision' => $sub->decision, 'decision_on' => $sub->decision_on?->toDateString(),
            'revision_due_on' => $sub->revision_due_on?->toDateString(), 'note' => $sub->note, 'is_current' => $sub->id === $this->paper->currentSubmission()->first()?->id];
        $this->resetValidation();
        $this->openModal('sub-form');
    }

    public function updatedSVenueId($id): void
    {
        if ($v = auth()->user()->venues()->find($id)) {
            $this->s['venue_name'] = $v->name;
            $this->s['portal_url'] = $this->s['portal_url'] ?: $v->portal_url;
        }
    }

    public function saveSubmission(): void
    {
        $d = $this->validate([
            's.venue_id' => 'nullable', 's.venue_name' => 'required|string|max:255', 's.manuscript_id' => 'nullable|string|max:255',
            's.portal_url' => 'nullable|url|max:255', 's.submitted_on' => 'nullable|date', 's.status_on' => 'nullable|date', 's.decision' => 'required|in:'.implode(',', array_keys(Submission::DECISIONS)),
            's.decision_on' => 'nullable|date', 's.revision_due_on' => 'nullable|date', 's.note' => 'nullable|string|max:5000', 's.is_current' => 'boolean',
        ])['s'];
        $d = array_map(fn ($v) => $v === '' ? null : $v, $d);
        $venue = $d['venue_id'] ? auth()->user()->venues()->find($d['venue_id']) : auth()->user()->venues()->firstOrCreate(['name' => $d['venue_name']]);
        $d['venue_id'] = $venue?->id;
        if (! in_array($d['decision'], Submission::WAITING, true) && ! $d['decision_on']) {
            $d['decision_on'] = today()->toDateString();
        }

        $d['is_current'] = (bool) ($d['is_current'] ?? false);
        if ($d['is_current']) {
            $this->paper->submissions()->update(['is_current' => false]);
        }
        $this->paper->unsetRelation('currentSubmission');
        $sub = $this->subId ? tap($this->paper->submissions()->findOrFail($this->subId))->update($d) : $this->paper->submissions()->create($d);
        $this->paper->queue()->where('venue_id', $sub->venue_id)->delete();
        $this->syncStage($sub->fresh());
        Flux::modal('sub-form')->close();
        $this->changed('Submission saved.');
    }

    public function makeCurrent(int $id): void
    {
        $sub = $this->paper->submissions()->findOrFail($id);
        $this->paper->submissions()->update(['is_current' => false]);
        $sub->update(['is_current' => true]);
        $this->paper->unsetRelation('currentSubmission');
        $this->syncStage($sub->fresh());
        $this->changed("{$sub->venue_name} is now the current journal.");
    }

    public function setDecision(int $id, string $decision): void
    {
        $sub = $this->paper->submissions()->findOrFail($id);
        $sub->update(['decision' => $decision, 'status_on' => today(), 'decision_on' => in_array($decision, Submission::WAITING, true) ? null : ($sub->decision_on ?? today())]);
        $this->syncStage($sub);
        $this->changed('Decision recorded: '.Submission::DECISIONS[$decision].'.');
    }

    private function syncStage(Submission $sub): void
    {
        if ($sub->id !== $this->paper->currentSubmission()->first()?->id) {
            return;
        }
        $stage = Submission::stageFor($sub->decision);
        if ($this->paper->stage !== 'published' || $stage !== 'accepted') {
            $this->paper->update(['stage' => $stage]);
        }
    }

    public function deleteSubmission(): void
    {
        $this->paper->submissions()->findOrFail($this->subId)->delete();
        Flux::modal('sub-form')->close();
        $this->changed('Submission removed.');
    }

    public function submitNext(): void
    {
        if ($sub = $this->paper->submitToNext()) {
            $this->changed("Submitted to {$sub->venue_name}. Add the manuscript ID when you have it.");
        }
    }

    /* ---------- Queue ---------- */
    public function addToQueue(): void
    {
        $venue = auth()->user()->venues()->find($this->queueVenue);
        if ($venue) {
            $this->paper->queue()->firstOrCreate(['venue_id' => $venue->id], ['position' => ($this->paper->queue()->max('position') ?? 0) + 1]);
            $this->queueVenue = '';
            $this->changed("{$venue->name} added to the queue.");
        }
    }

    public function moveQueue(int $id, int $dir): void
    {
        $items = $this->paper->queue()->get()->values();
        $i = $items->search(fn ($q) => $q->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $items->count()) {
            return;
        }
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        $items->each(fn ($q, $k) => $q->update(['position' => $k]));
        $this->paper->refresh();
    }

    public function removeQueue(int $id): void
    {
        $this->paper->queue()->whereKey($id)->delete();
        $this->paper->refresh();
    }

    /* ---------- Revision checklist ---------- */
    public function openChecklist(int $subId): void
    {
        $this->checklistFor = $subId;
        $this->comments = '';
        $this->openModal('comments');
    }

    public function importComments(): void
    {
        $sub = $this->paper->submissions()->findOrFail($this->checklistFor);
        $text = str_replace("\r", '', $this->comments);
        $parts = preg_split('/\n\s*\n|\n(?=\s*(?:\d+[\.\)]|[-•*]|R\d|Reviewer\s*\d|Comment\s*\d))/i', $text);
        $start = (int) $sub->revisionItems()->max('position');
        $n = 0;
        foreach ($parts as $part) {
            $part = trim(preg_replace('/^\s*(?:\d+[\.\)]|[-•*])\s*/', '', $part));
            if (mb_strlen($part) > 3) {
                $sub->revisionItems()->create(['comment' => $part, 'position' => $start + ++$n]);
            }
        }
        Flux::modal('comments')->close();
        $this->changed("{$n} reviewer ".str('comment')->plural($n).' added to the checklist.');
    }

    public function toggleItem(int $id): void
    {
        $item = \App\Models\RevisionItem::whereKey($id)->whereIn('submission_id', $this->paper->submissions()->pluck('id'))->firstOrFail();
        $item->update(['done' => ! $item->done]);
    }

    public function saveResponse(int $id, string $response): void
    {
        \App\Models\RevisionItem::whereKey($id)->whereIn('submission_id', $this->paper->submissions()->pluck('id'))->firstOrFail()
            ->update(['response' => mb_substr($response, 0, 5000)]);
    }

    public function deleteItem(int $id): void
    {
        \App\Models\RevisionItem::whereKey($id)->whereIn('submission_id', $this->paper->submissions()->pluck('id'))->delete();
    }

    /* ---------- Files ---------- */
    public function saveFile(): void
    {
        $this->validate(['upload' => 'required|file|max:51200', 'fileKind' => 'required|in:'.implode(',', array_keys(PaperFile::KINDS)), 'fileLabel' => 'nullable|string|max:120']);
        $path = $this->upload->store('papers/'.$this->paper->id, 'local');
        $this->paper->files()->create([
            'submission_id' => $this->fileSubmission ?: null, 'kind' => $this->fileKind, 'label' => $this->fileLabel ?: null, 'path' => $path,
            'original_name' => $this->upload->getClientOriginalName(), 'mime' => $this->upload->getMimeType(), 'size' => $this->upload->getSize(),
        ]);
        $this->reset('upload', 'fileLabel', 'fileSubmission');
        Flux::modal('file-form')->close();
        $this->changed('File uploaded.');
    }

    public function preview(int $id): void
    {
        $this->previewId = $this->paper->files()->findOrFail($id)->id;
        $this->openModal('preview');
    }

    public function deleteFile(int $id): void
    {
        $this->paper->files()->findOrFail($id)->delete();
        $this->changed('File deleted.');
    }

    /* ---------- Authors ---------- */
    public function editAuthor(?int $id = null): void
    {
        $au = $id ? $this->paper->authors()->findOrFail($id) : null;
        $this->authorId = $au?->id;
        $this->a = ['name' => $au?->name ?? '', 'affiliation' => $au?->affiliation ?? '', 'email' => $au?->email ?? '', 'orcid' => $au?->orcid ?? '',
            'corresponding' => (bool) $au?->corresponding, 'roles' => $au?->roles ?? []];
        $this->resetValidation();
        $this->openModal('author-form');
    }

    public function saveAuthor(): void
    {
        $d = $this->validate([
            'a.name' => 'required|string|max:255', 'a.affiliation' => 'nullable|string|max:255', 'a.email' => 'nullable|email|max:255',
            'a.orcid' => ['nullable', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'], 'a.corresponding' => 'boolean', 'a.roles' => 'array',
            'a.roles.*' => 'in:'.implode(',', Author::CREDIT),
        ])['a'];
        if ($d['corresponding']) {
            $this->paper->authors()->update(['corresponding' => false]);
        }
        $this->authorId
            ? $this->paper->authors()->findOrFail($this->authorId)->update($d)
            : $this->paper->authors()->create($d + ['position' => ($this->paper->authors()->max('position') ?? 0) + 1]);
        Flux::modal('author-form')->close();
        $this->changed('Author saved.');
    }

    public function deleteAuthor(): void
    {
        $this->paper->authors()->findOrFail($this->authorId)->delete();
        Flux::modal('author-form')->close();
        $this->changed('Author removed.');
    }

    public function moveAuthor(int $id, int $dir): void
    {
        $items = $this->paper->authors()->get()->values();
        $i = $items->search(fn ($x) => $x->id === $id);
        $j = $i + $dir;
        if ($i === false || $j < 0 || $j >= $items->count()) {
            return;
        }
        [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        $items->each(fn ($x, $k) => $x->update(['position' => $k]));
        $this->paper->refresh();
    }

    public function with(): array
    {
        $this->paper->load(['submissions.venue', 'submissions.revisionItems', 'queue.venue', 'authors', 'files', 'currentSubmission.venue', 'deadlines']);
        $authors = $this->paper->authors;
        $credit = collect(Author::CREDIT)->map(function ($role) use ($authors) {
            $who = $authors->filter(fn ($x) => in_array($role, $x->roles ?? []))->pluck('name');
            return $who->isEmpty() ? null : $role.': '.$who->implode(', ').'.';
        })->filter()->implode(' ');

        return [
            'cur' => $this->paper->currentSubmission,
            'risk' => $this->paper->anonymityRisk(),
            'venues' => auth()->user()->venues()->orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind']),
            'credit' => $credit,
            'proof' => $this->paper->files->firstWhere('kind', 'proof'),
            'previewFile' => $this->previewId ? $this->paper->files->firstWhere('id', $this->previewId) : null,
        ];
    }
}; ?>

<div class="page-in flex min-h-0 flex-1 flex-col">
    {{-- Header --}}
    <header class="border-b border-zinc-200/80 px-4 pt-4 sm:px-6 sm:pt-5 lg:px-8 dark:border-zinc-800">
        <a href="{{ route('papers.index') }}" wire:navigate class="mb-3 inline-flex items-center gap-1 text-xs text-zinc-500 hover:text-indigo-600 lg:hidden"><flux:icon.chevron-left variant="micro" /> Papers</a>
        <div class="flex flex-col-reverse items-start gap-3 sm:flex-row sm:justify-between sm:gap-4">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <flux:dropdown>
                        <button class="inline-flex h-6 items-center rounded-full border border-zinc-200 px-2.5 transition hover:border-indigo-300 dark:border-zinc-700"><x-ui.stage :stage="$paper->stage" /></button>
                        <flux:menu>
                            @foreach (Paper::STAGES as $k => $l)<flux:menu.item wire:click="setStage('{{ $k }}')">{{ $l }}</flux:menu.item>@endforeach
                        </flux:menu>
                    </flux:dropdown>
                    @if ($cur && ! ($paper->stage === Submission::stageFor($cur->decision) && in_array($paper->stage, ['submitted', 'under_review'])))<x-ui.decision :s="$cur" />@endif
                    @if ($paper->short_title)<span class="text-xs text-zinc-400">{{ $paper->short_title }}</span>@endif
                    @foreach ($paper->tags ?? [] as $t)<a href="{{ route('papers.index', ['q' => '#'.$t]) }}" wire:navigate class="inline-flex"><x-ui.tag :tag="$t" class="h-6 px-2.5" /></a>@endforeach
                </div>
                <h1 class="mt-2 max-w-4xl text-lg leading-snug font-semibold tracking-tight text-balance sm:text-xl lg:text-[1.6rem]">{{ $paper->title }}</h1>
                @if ($paper->method)<p class="mt-1 text-sm text-zinc-500">{{ $paper->method }}</p>@endif
            </div>
            <div class="flex shrink-0 gap-1 self-end sm:self-auto">
                <flux:button size="sm" icon="pencil-square" wire:click="editPaper">Edit</flux:button>
                <flux:dropdown align="end">
                    <flux:button size="sm" variant="ghost" icon="ellipsis-horizontal" />
                    <flux:menu>
                        <flux:menu.item icon="trash" variant="danger" wire:click="deletePaper" wire:confirm="Delete this paper, its submissions and its files? This cannot be undone.">Delete paper</flux:menu.item>
                    </flux:menu>
                </flux:dropdown>
            </div>
        </div>

        @php $tabs = ['submissions' => 'Overview', 'files' => 'Files']; $counts = ['files' => $paper->files->count()]; @endphp
        <nav class="relative mt-5 flex gap-6 text-sm" x-data="{ ink: {} }" x-init="const m = () => { const el = $el.querySelector('[aria-current]'); if (el) ink = { left: el.offsetLeft + 'px', width: el.offsetWidth + 'px' } }; m(); new ResizeObserver(m).observe($el); Livewire.hook('morph.updated', () => $nextTick(m))">
            @foreach ($tabs as $k => $l)
                <button wire:click="$set('tab', '{{ $k }}')" @if ($tab === $k) aria-current="page" @endif
                        class="pb-3 font-medium transition {{ $tab === $k ? 'text-zinc-900 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                    {{ $l }} @if (! empty($counts[$k]))<span class="num ml-0.5 text-xs text-zinc-400">{{ $counts[$k] }}</span>@endif
                </button>
            @endforeach
            <span class="tab-ink" :style="`left:${ink.left};width:${ink.width}`"></span>
        </nav>
    </header>

    <div class="flex-1 overflow-y-auto px-4 py-5 sm:px-6 sm:py-6 lg:px-8" wire:key="tab-{{ $tab }}">
        @if ($risk)
            <flux:callout variant="danger" icon="eye" class="mb-5" heading="Anonymity risk">
                <flux:callout.text>This paper has a public preprint ({{ $paper->preprint_server ?: 'link' }}) while it is under double-anonymous review at {{ $risk->venue_name }}. Check the venue’s preprint policy, and do not cite the preprint in the anonymized file.</flux:callout.text>
            </flux:callout>
        @endif

        {{-- ================= PAPER: submissions + overview ================= --}}
        @if ($tab === 'submissions')
            <div class="grid grid-cols-[minmax(0,1fr)] gap-6 xl:grid-cols-3">
                <div class="min-w-0 space-y-6 xl:col-span-2">
                    @php
                        $chron = $paper->submissions->sortBy(fn ($x) => [$x->submitted_on?->timestamp ?? 0, $x->id])->values();
                        $first = $chron->first()?->submitted_on;
                        $rejections = $chron->filter->isRejection()->count();
                        $ringTone = ['blue' => 'bg-sky-500 ring-sky-100 dark:ring-sky-500/20', 'amber' => 'bg-amber-500 ring-amber-100 dark:ring-amber-500/20', 'red' => 'bg-rose-500 ring-rose-100 dark:ring-rose-500/20', 'green' => 'bg-emerald-500 ring-emerald-100 dark:ring-emerald-500/20', 'zinc' => 'bg-zinc-400 ring-zinc-100 dark:ring-zinc-700', 'indigo' => 'bg-indigo-500 ring-indigo-100 dark:ring-indigo-500/20', 'fuchsia' => 'bg-fuchsia-500 ring-fuchsia-100 dark:ring-fuchsia-500/20', 'teal' => 'bg-teal-500 ring-teal-100 dark:ring-teal-500/20', 'orange' => 'bg-orange-500 ring-orange-100 dark:ring-orange-500/20'];
                        $iconOf = ['blue' => 'clock', 'amber' => 'pencil', 'red' => 'x-mark', 'green' => 'check', 'zinc' => 'minus', 'indigo' => 'paper-airplane', 'fuchsia' => 'user', 'teal' => 'scale', 'orange' => 'arrow-uturn-left'];
                        $hasPreprint = filled($paper->preprint_server) || filled($paper->preprint_url);
                        $preprintName = $hasPreprint ? (str($paper->preprint_server ?: 'Preprint')->before(' (')->value()) : null;
                        // Journey steps: every submission plus the preprint, in date order.
                        $steps = $chron->map(fn ($x) => ['type' => 'sub', 'sub' => $x, 'date' => $x->submitted_on]);
                        if ($hasPreprint) {
                            $steps->push(['type' => 'preprint', 'date' => $paper->preprint_on]);
                        }
                        $steps = $steps->sortBy(fn ($x) => [$x['date']?->timestamp ?? PHP_INT_MAX, $x['type'] === 'preprint' ? 1 : 0])->values();
                        $first = $first ?? $paper->preprint_on;
                    @endphp
                    @if ($steps->isNotEmpty())
                        <section class="panel relative overflow-hidden p-4 sm:p-5">
                            <div class="pointer-events-none absolute -top-20 -right-10 size-56 rounded-full bg-gradient-to-br from-indigo-400/15 via-fuchsia-400/10 to-transparent blur-2xl"></div>
                            <div class="relative flex flex-wrap items-baseline justify-between gap-2">
                                <h2 class="font-semibold">Journey</h2>
                                <span class="text-xs text-zinc-500">{{ $first ? 'First sent '.$first->format('j M Y') : '' }}</span>
                            </div>

                            {{-- Stepper --}}
                            <div class="relative mt-3 -mx-2 overflow-x-auto px-2 pt-2 pb-1">
                                <ol class="flex min-w-max items-start">
                                    @foreach ($steps as $i => $step)
                                        <li class="flex items-start">
                                        @if ($step['type'] === 'preprint')
                                            <div class="flex w-28 flex-col items-center text-center" style="animation: page-in .4s both {{ $i * 0.08 }}s">
                                                <span class="relative flex size-9 items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-500 text-white ring-4 ring-teal-100 dark:ring-teal-500/20"><flux:icon.document-arrow-up variant="mini" class="size-4" /></span>
                                                <span class="mt-2 line-clamp-2 text-xs leading-tight font-medium text-zinc-700 dark:text-zinc-200">{{ $preprintName }}</span>
                                                <span class="mt-0.5 text-[10px] text-zinc-400">{{ $paper->preprint_on?->format('M Y') ?? 'date not set' }}</span>
                                                @if ($paper->preprint_url)<a href="{{ $paper->preprint_url }}" target="_blank" class="mt-1 rounded-full bg-teal-50 px-1.5 py-px text-[10px] font-medium text-teal-700 hover:underline dark:bg-teal-500/10 dark:text-teal-300">Preprint ↗</a>
                                                @else<span class="mt-1 rounded-full bg-teal-50 px-1.5 py-px text-[10px] font-medium text-teal-700 dark:bg-teal-500/10 dark:text-teal-300">Preprint</span>@endif
                                            </div>
                                        @else
                                        @php $j = $step['sub']; $isCurJ = $cur && $cur->id === $j->id; @endphp
                                            <div class="flex w-28 flex-col items-center text-center" style="animation: page-in .4s both {{ $i * 0.08 }}s">
                                                <span class="relative flex size-9 items-center justify-center rounded-full text-white ring-4 {{ $ringTone[$j->tone()] }} {{ $isCurJ ? 'shadow-lg shadow-indigo-500/30' : '' }}">
                                                    @if ($isCurJ && $j->isWaiting())<span class="absolute inset-0 animate-ping rounded-full bg-sky-400/40"></span>@endif
                                                    <flux:icon :name="$iconOf[$j->tone()]" variant="mini" class="relative size-4" />
                                                </span>
                                                <span class="mt-2 line-clamp-2 text-xs leading-tight font-medium {{ $isCurJ ? 'text-zinc-900 dark:text-white' : 'text-zinc-600 dark:text-zinc-300' }}">{{ str($j->venue_name)->before(' (') }}</span>
                                                <span class="mt-0.5 text-[10px] text-zinc-400">{{ $j->submitted_on?->format('M Y') }}</span>
                                                <span class="mt-1 rounded-full px-1.5 py-px text-[10px] font-medium {{ ['blue' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300', 'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300', 'red' => 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-300', 'green' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'zinc' => 'bg-zinc-100 text-zinc-500 dark:bg-zinc-800', 'indigo' => 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300', 'fuchsia' => 'bg-fuchsia-50 text-fuchsia-700 dark:bg-fuchsia-500/10 dark:text-fuchsia-300', 'teal' => 'bg-teal-50 text-teal-700 dark:bg-teal-500/10 dark:text-teal-300', 'orange' => 'bg-orange-50 text-orange-700 dark:bg-orange-500/10 dark:text-orange-300'][$j->tone()] }}">{{ Submission::DECISIONS[$j->decision] }}@if ($j->daysInReview() !== null) · {{ $j->daysInReview() }}d @endif</span>
                                            </div>
                                        @endif
                                            @unless ($loop->last)
                                                <div class="mt-4 h-0.5 w-6 shrink-0 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700"><div class="grow-x h-full bg-gradient-to-r from-zinc-300 to-indigo-300 dark:from-zinc-600 dark:to-indigo-500" style="animation-delay: {{ 0.1 + $i * 0.08 }}s"></div></div>
                                            @endunless
                                        </li>
                                    @endforeach
                                    @if ($paper->queue->isNotEmpty() && (! $cur || $cur->isRejection()))
                                        <li class="flex items-start">
                                            <div class="mt-4 h-0.5 w-10 shrink-0 border-t-2 border-dashed border-violet-300"></div>
                                            <div class="flex w-32 flex-col items-center text-center">
                                                <span class="flex size-9 items-center justify-center rounded-full border-2 border-dashed border-violet-400 text-violet-500"><flux:icon.arrow-right variant="mini" class="size-4" /></span>
                                                <span class="mt-2 line-clamp-2 text-xs leading-tight font-medium text-violet-600 dark:text-violet-400">{{ str($paper->queue->first()->venue->name)->before(' (') }}</span>
                                                <span class="mt-0.5 text-[10px] text-zinc-400">next</span>
                                            </div>
                                        </li>
                                    @endif
                                </ol>
                            </div>

                            @if ($chron->isNotEmpty())
                            {{-- Stat tiles --}}
                            <div class="relative mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4 sm:gap-3">
                                <div class="rounded-xl bg-gradient-to-br from-indigo-50 to-white p-3 ring-1 ring-indigo-100 dark:from-indigo-500/10 dark:to-transparent dark:ring-indigo-500/20">
                                    <p class="text-[11px] text-zinc-500">Venues tried</p><x-ui.count :value="$chron->count()" class="text-2xl font-semibold" />
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-rose-50 to-white p-3 ring-1 ring-rose-100 dark:from-rose-500/10 dark:to-transparent dark:ring-rose-500/20">
                                    <p class="text-[11px] text-zinc-500">Rejections</p><x-ui.count :value="$rejections" class="text-2xl font-semibold" />
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-sky-50 to-white p-3 ring-1 ring-sky-100 dark:from-sky-500/10 dark:to-transparent dark:ring-sky-500/20">
                                    <p class="text-[11px] text-zinc-500">Days since first sent</p><x-ui.count :value="$first ? (int) $first->diffInDays(now()) : 0" class="text-2xl font-semibold" />
                                </div>
                                <div class="rounded-xl bg-gradient-to-br from-amber-50 to-white p-3 ring-1 ring-amber-100 dark:from-amber-500/10 dark:to-transparent dark:ring-amber-500/20">
                                    <p class="text-[11px] text-zinc-500">{{ $cur && $cur->isWaiting() ? ($cur->decision === 'pending' ? 'Days in current review' : 'Days since submission') : 'Status' }}</p>
                                    @if ($cur && $cur->isWaiting())<x-ui.count :value="$cur->daysInReview() ?? 0" class="text-2xl font-semibold" />
                                    @else<p class="mt-1 text-sm font-semibold">{{ $cur ? Submission::DECISIONS[$cur->decision] : Paper::STAGES[$paper->stage] }}</p>@endif
                                </div>
                            </div>
                            @endif
                        </section>
                    @endif

                    <div>
                    <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                        <div><h2 class="font-semibold">Submission history</h2><p class="text-xs text-zinc-500">One paper, every venue it has been sent to, current journal first.</p></div>
                        <flux:button size="sm" variant="primary" icon="plus" wire:click="newSubmission">{{ $paper->submissions->isEmpty() ? 'Record submission' : 'Add another venue' }}</flux:button>
                    </div>
                    @if ($cur && $cur->isRejection())
                        <div class="mb-5 flex flex-wrap items-center gap-3 rounded-xl border border-dashed border-indigo-300 bg-indigo-50/50 p-4 dark:border-indigo-500/30 dark:bg-indigo-500/5">
                            <flux:icon.arrow-path-rounded-square class="size-6 text-indigo-500" />
                            <div class="min-w-0 flex-1"><p class="text-sm font-semibold">Sent it somewhere else?</p><p class="text-xs text-zinc-500">Add the new journal. {{ $cur->venue_name }} stays in the history.</p></div>
                            <flux:button size="sm" variant="primary" wire:click="newSubmission">Add new journal</flux:button>
                        </div>
                    @endif
                    @php $preprintShown = false; @endphp
                    @foreach ($paper->submissions->sortByDesc(fn ($x) => $x->id === $cur?->id) as $sub)
                        @php $c = $sub->clock(); $items = $sub->revisionItems; $doneN = $items->where('done', true)->count(); $isCur = $cur && $cur->id === $sub->id; @endphp
                        {{-- The preprint sits in date order among the earlier submissions. --}}
                        @if ($hasPreprint && ! $preprintShown && ! $isCur && $paper->preprint_on && $sub->submitted_on && $paper->preprint_on->gte($sub->submitted_on))
                            <x-ui.preprint-entry :paper="$paper" />
                            @php $preprintShown = true; @endphp
                        @endif
                        <div class="relative pb-5 pl-7 sm:pb-6 sm:pl-8" wire:key="s{{ $sub->id }}">
                            @unless ($loop->last)<span class="absolute top-6 bottom-0 left-[11px] w-px bg-zinc-200 dark:bg-zinc-800"></span>@endunless
                            <span class="absolute top-1.5 left-0 flex size-6 items-center justify-center rounded-full ring-4 ring-[#f7f8fc] dark:ring-zinc-950
                                {{ ['blue' => 'bg-sky-500', 'amber' => 'bg-amber-500', 'red' => 'bg-rose-500', 'green' => 'bg-emerald-500', 'zinc' => 'bg-zinc-400', 'indigo' => 'bg-indigo-500', 'fuchsia' => 'bg-fuchsia-500', 'teal' => 'bg-teal-500', 'orange' => 'bg-orange-500'][$sub->tone()] }} text-white">
                                <flux:icon :name="['blue' => 'clock', 'amber' => 'pencil', 'red' => 'x-mark', 'green' => 'check', 'zinc' => 'minus', 'indigo' => 'paper-airplane', 'fuchsia' => 'user', 'teal' => 'scale', 'orange' => 'arrow-uturn-left'][$sub->tone()]" variant="micro" class="size-3.5" />
                            </span>
                            <div class="panel p-4 {{ $isCur ? 'ring-1 ring-indigo-200 dark:ring-indigo-500/30' : '' }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2"><h3 class="font-semibold">{{ $sub->venue_name }}</h3><x-ui.decision :s="$sub" />@if ($isCur)<span class="rounded-md bg-indigo-600 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">Current</span>@endif</div>
                                        <p class="mt-0.5 text-sm text-zinc-500">
                                            {{ $sub->manuscript_id ?: 'No manuscript ID' }} · Submitted {{ $sub->submitted_on?->format('j M Y') ?? 'date not set' }}
                                            @if ($sub->status_on) · {{ Submission::DECISIONS[$sub->decision] }} since {{ $sub->status_on->format('j M Y') }}@endif
                                            @if ($sub->decision_on && ! $sub->isWaiting()) → {{ $sub->decision_on->format('j M Y') }}@endif
                                            @if ($sub->daysInReview() !== null) · <span class="num">{{ $sub->daysInReview() }}</span> days @endif
                                        </p>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-1">
                                        @unless ($isCur)<flux:button size="sm" variant="ghost" icon="star" wire:click="makeCurrent({{ $sub->id }})" title="Make this the paper’s current journal">Set as current</flux:button>@endunless
                                        @if ($sub->portal_url)<flux:button size="sm" variant="{{ $isCur ? 'primary' : 'filled' }}" icon-trailing="arrow-up-right" :href="$sub->portal_url" target="_blank">Open portal</flux:button>@endif
                                        <x-ui.menu>
                                            <x-slot:trigger><flux:button size="sm">Decision</flux:button></x-slot:trigger>
                                            @foreach (Submission::DECISIONS as $k => $l)<x-ui.menu-item wire:click="setDecision({{ $sub->id }}, '{{ $k }}')">{{ $l }}</x-ui.menu-item>@endforeach
                                            @if (! $isCur)<div class="my-1 border-t border-zinc-100 dark:border-zinc-800"></div><x-ui.menu-item wire:click="makeCurrent({{ $sub->id }})">Make current journal</x-ui.menu-item>@endif
                                        </x-ui.menu>
                                        <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editSubmission({{ $sub->id }})" />
                                    </div>
                                </div>
                                @if ($isCur && $sub->isWaiting() && $sub->daysInReview() !== null)
                                    <div class="mt-4 flex items-center gap-4 rounded-xl bg-zinc-50 p-3 dark:bg-zinc-800/40">
                                        <x-ui.ring :pct="$c['pct'] ?? 0" :size="54" :tone="$c ? ($c['overdue'] ? 'red' : ($c['pct'] > 75 ? 'amber' : 'indigo')) : 'zinc'" :label="$sub->daysInReview()" sub="days" />
                                        <div class="min-w-0 flex-1 text-sm">
                                            <p class="font-medium">{{ $sub->daysInReview() }} days in review</p>
                                            <p class="text-xs {{ $c && $c['overdue'] ? 'text-rose-500' : 'text-zinc-500' }}">@if ($c){{ $c['overdue'] ? ($c['days'] - $c['typical']).' days past the usual '.$c['typical'].'. A status query is fair.' : 'Usually '.$c['typical'].' days, so a decision around '.$c['expected_on']->format('j M').'.' }}@else Add the venue’s usual decision time to start the clock.@endif</p>
                                        </div>
                                    </div>
                                @elseif ($c)
                                    <div class="mt-3"><div class="mb-1 flex justify-between text-[11px] text-zinc-500"><span>Review clock</span><span class="num">{{ $c['days'] }} / {{ $c['typical'] }} days</span></div>
                                        <div class="clock-track"><div class="clock-fill grow-x {{ $c['overdue'] ? 'bg-rose-500' : 'bg-gradient-to-r from-indigo-500 to-violet-500' }}" style="width: {{ $c['pct'] }}%"></div></div></div>
                                @endif
                                @if ($sub->revision_due_on && in_array($sub->decision, ['major', 'minor']))
                                    <p class="mt-3 text-sm text-amber-600">Revision due {{ $sub->revision_due_on->format('j M Y') }} ({{ $sub->revision_due_on->diffForHumans() }})</p>
                                @endif
                                @if ($sub->note)
                                    <div x-data="{ open: false }" class="mt-3">
                                        <button x-on:click="open = ! open" class="flex items-center gap-1 text-xs text-zinc-500 hover:text-indigo-600">Notes <flux:icon.chevron-down variant="micro" class="size-4 transition" ::class="open && 'rotate-180'" /></button>
                                        <p x-show="open" x-collapse x-cloak class="pt-2 text-sm whitespace-pre-line text-zinc-600 dark:text-zinc-300">{{ $sub->note }}</p>
                                    </div>
                                @endif

                                @if (in_array($sub->decision, ['major', 'minor']) || $items->isNotEmpty())
                                    <div class="mt-4 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/40" x-data="{ open: {{ $doneN < $items->count() || $items->isEmpty() ? 'true' : 'false' }} }">
                                        <div class="flex items-center justify-between">
                                            <button x-on:click="open = ! open" class="flex items-center gap-1 text-xs font-semibold">Reviewer comments <span class="num font-normal text-zinc-500">{{ $doneN }}/{{ $items->count() }} answered</span><flux:icon.chevron-down variant="micro" class="size-4 transition" ::class="open && 'rotate-180'" /></button>
                                            <flux:button size="xs" variant="ghost" icon="plus" wire:click="openChecklist({{ $sub->id }})">Paste comments</flux:button>
                                        </div>
                                        @if ($items->isNotEmpty())
                                            <div class="clock-track mt-2 !h-1"><div class="clock-fill bg-emerald-500" style="width: {{ round($doneN / $items->count() * 100) }}%"></div></div>
                                        @endif
                                        <div class="mt-3 space-y-2" x-show="open" x-collapse>
                                            @foreach ($items as $it)
                                                <div class="group rounded-md bg-white p-2.5 ring-1 ring-zinc-200 dark:bg-zinc-900 dark:ring-zinc-700" wire:key="ri{{ $it->id }}" x-data="{ r: false }">
                                                    <div class="flex items-start gap-2.5">
                                                        <button wire:click="toggleItem({{ $it->id }})" class="mt-0.5 flex size-4 shrink-0 items-center justify-center rounded border transition {{ $it->done ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-zinc-300 dark:border-zinc-600' }}">@if ($it->done)<flux:icon.check variant="micro" class="size-3" />@endif</button>
                                                        <p class="flex-1 text-sm {{ $it->done ? 'text-zinc-400 line-through' : '' }}">{{ $it->comment }}</p>
                                                        <button x-on:click="r = ! r" class="text-xs text-zinc-400 hover:text-indigo-600">{{ $it->response ? 'Response' : 'Respond' }}</button>
                                                        <button wire:click="deleteItem({{ $it->id }})" class="text-zinc-300 opacity-0 group-hover:opacity-100 hover:text-rose-500"><flux:icon.x-mark variant="micro" /></button>
                                                    </div>
                                                    <div x-show="r" x-collapse x-cloak class="mt-2 pl-6">
                                                        <textarea rows="3" class="w-full rounded-md border-zinc-200 bg-zinc-50 p-2 text-sm dark:border-zinc-700 dark:bg-zinc-800" placeholder="How you addressed it" x-on:blur="$wire.saveResponse({{ $it->id }}, $event.target.value)">{{ $it->response }}</textarea>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                    @if ($hasPreprint && ! $preprintShown)
                        <x-ui.preprint-entry :paper="$paper" />
                    @endif
                    @if ($paper->submissions->isEmpty())
                        <div class="panel"><x-ui.empty title="Not submitted to a journal yet" text="Record a submission when you send this paper to a venue. The review clock starts from that date.">
                            <flux:button variant="primary" wire:click="newSubmission">Record submission</flux:button></x-ui.empty></div>
                    @endif
                    </div>

                    <div class="stagger space-y-5">
                    <div class="panel p-5">
                        <div class="mb-3 flex items-center justify-between">
                            <p class="eyebrow">Authors</p>
                            <flux:button size="xs" variant="ghost" icon="plus" wire:click="editAuthor">Add author</flux:button>
                        </div>
                        <div class="space-y-1">
                            @forelse ($paper->authors as $au)
                                <div class="group -mx-2 flex items-start gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="a{{ $au->id }}">
                                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 to-fuchsia-500 text-xs font-semibold text-white">{{ str($au->name)->explode(' ')->map(fn ($x) => mb_substr($x, 0, 1))->take(2)->implode('') }}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-medium">{{ $au->name }} @if ($au->corresponding)<span class="ml-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[10px] font-semibold text-indigo-600 dark:bg-indigo-500/10">Corresponding</span>@endif</p>
                                        <p class="text-xs text-zinc-500">{{ collect([$au->affiliation, $au->email, $au->orcid ? 'ORCID '.$au->orcid : null])->filter()->implode(' · ') }}</p>
                                        @if ($au->roles)<div class="mt-1.5 flex flex-wrap gap-1">@foreach ($au->roles as $r)<span class="rounded-full bg-zinc-100 px-2 py-0.5 text-[11px] dark:bg-zinc-800">{{ $r }}</span>@endforeach</div>@endif
                                    </div>
                                    <div class="flex opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100">
                                        <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveAuthor({{ $au->id }}, -1)" />
                                        <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveAuthor({{ $au->id }}, 1)" />
                                        <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="editAuthor({{ $au->id }})" />
                                    </div>
                                </div>
                            @empty
                                <button wire:click="editAuthor" class="text-sm text-zinc-400 hover:text-indigo-600">Add authors with their CRediT roles…</button>
                            @endforelse
                        </div>
                        @if ($credit)
                            <div class="mt-4 rounded-lg bg-zinc-50 p-3 dark:bg-zinc-800/40" x-data="{ copied: false }">
                                <div class="mb-1 flex items-center justify-between"><span class="text-[11px] font-semibold text-zinc-500">CRediT statement</span>
                                    <button class="text-xs text-zinc-500 hover:text-indigo-600" x-on:click="navigator.clipboard.writeText($refs.c.innerText); copied = true; setTimeout(() => copied = false, 1500)" x-text="copied ? 'Copied' : 'Copy'"></button></div>
                                <p x-ref="c" class="text-sm text-zinc-700 dark:text-zinc-300">{{ $credit }}</p>
                            </div>
                        @endif
                    </div>

                    <div class="panel p-5" x-data="{ open: false }">
                        @if ($paper->abstract)
                            <button type="button" x-on:click="open = ! open" class="group flex w-full items-center justify-between text-left" :aria-expanded="open">
                                <span class="eyebrow">Abstract <span class="num ml-1 font-normal normal-case tracking-normal">{{ str_word_count($paper->abstract) }} words</span></span>
                                <span class="flex items-center gap-1 text-xs text-zinc-500 group-hover:text-indigo-600">
                                    <span x-text="open ? 'Collapse' : 'Expand'">Expand</span>
                                    <flux:icon.chevron-down variant="micro" class="size-4 transition-transform duration-200" ::class="open && 'rotate-180'" />
                                </span>
                            </button>
                            <div x-show="open" x-collapse x-cloak>
                                <p class="mt-3 text-sm leading-relaxed whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $paper->abstract }}</p>
                            </div>
                        @else
                            <p class="eyebrow mb-2">Abstract</p>
                            <button wire:click="editPaper" class="text-sm text-zinc-400 hover:text-indigo-600">Add an abstract…</button>
                        @endif
                        @if ($paper->keywords)
                            <div class="mt-4 flex flex-wrap gap-1.5">
                                @foreach (preg_split('/[;,]/', $paper->keywords) as $k)@if (trim($k))<span class="rounded-full bg-zinc-100 px-2 py-0.5 text-xs text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300">{{ trim($k) }}</span>@endif @endforeach
                            </div>
                        @endif
                    </div>

                    @if ($paper->notes)
                        <div class="panel p-5"><p class="eyebrow mb-2">Notes</p><p class="text-sm whitespace-pre-line text-zinc-700 dark:text-zinc-300">{{ $paper->notes }}</p></div>
                    @endif
                    </div>
                </div>
                <aside class="min-w-0 space-y-4">
                    <dl class="panel space-y-4 p-4 text-sm">
                        @foreach (['Method' => $paper->method, 'Data' => $paper->dataset] as $k => $v)
                            <div><dt class="text-xs text-zinc-400">{{ $k }}</dt><dd class="mt-0.5 leading-snug text-zinc-700 dark:text-zinc-200">{{ $v ?: '—' }}</dd></div>
                        @endforeach
                        <div><dt class="text-xs text-zinc-400">Preprint</dt>
                            <dd class="mt-0.5 text-zinc-700 dark:text-zinc-200">@if ($paper->preprint_url)<a href="{{ $paper->preprint_url }}" target="_blank" class="text-indigo-600 hover:underline">{{ $paper->preprint_server ?: 'Open' }}</a>@elseif ($paper->preprint_server){{ $paper->preprint_server }}@else — @endif
                                @if ($paper->preprint_on)<span class="text-zinc-400"> · {{ $paper->preprint_on->format('j M Y') }}</span>@endif</dd></div>
                        @if ($paper->overleaf_url || $paper->drive_url)
                            <div><dt class="text-xs text-zinc-400">Links</dt><dd class="mt-0.5 flex gap-3">
                                @if ($paper->overleaf_url)<a href="{{ $paper->overleaf_url }}" target="_blank" class="text-indigo-600 hover:underline">Overleaf</a>@endif
                                @if ($paper->drive_url)<a href="{{ $paper->drive_url }}" target="_blank" class="text-indigo-600 hover:underline">Drive</a>@endif</dd></div>
                        @endif
                    </dl>

                    @if ($proof)
                        <button wire:click="preview({{ $proof->id }})" class="panel lift flex w-full items-center gap-3 p-4 text-left">
                            <span class="flex size-10 items-center justify-center rounded-lg bg-gradient-to-br from-rose-500 to-orange-400 text-white"><flux:icon.document-check variant="mini" /></span>
                            <span class="min-w-0"><span class="block text-sm font-semibold">Proof PDF</span><span class="block truncate text-xs text-zinc-500">{{ $proof->original_name }}</span></span>
                        </button>
                    @endif
                    <div class="panel p-4">
                        <h3 class="font-semibold">Next-venue queue</h3>
                        <p class="mt-0.5 text-xs text-zinc-500">If this round ends in rejection, the first venue becomes the next submission in one click.</p>
                        @if (($paper->needsNextVenue() || $paper->stage === 'ready') && $paper->queue->isNotEmpty())
                            <flux:button class="mt-3 w-full" variant="primary" icon-trailing="arrow-right" wire:click="submitNext">Submit to {{ str($paper->queue->first()->venue->name)->limit(28) }}</flux:button>
                        @endif
                        <div class="mt-3 space-y-1.5">
                            @foreach ($paper->queue as $i => $q)
                                <div class="group flex items-center gap-2 rounded-lg px-1 py-1.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800/50" wire:key="q{{ $q->id }}">
                                    <span class="num flex size-5 shrink-0 items-center justify-center rounded-full bg-indigo-50 text-[10px] font-semibold text-indigo-600 dark:bg-indigo-500/10">{{ $i + 1 }}</span>
                                    <span class="min-w-0 flex-1 truncate">{{ $q->venue->name }}</span>
                                    <span class="flex opacity-0 transition group-focus-within:opacity-100 group-hover:opacity-100">
                                        <button wire:click="moveQueue({{ $q->id }}, -1)" class="p-0.5 hover:text-indigo-600"><flux:icon.chevron-up variant="micro" /></button>
                                        <button wire:click="moveQueue({{ $q->id }}, 1)" class="p-0.5 hover:text-indigo-600"><flux:icon.chevron-down variant="micro" /></button>
                                        <button wire:click="removeQueue({{ $q->id }})" class="p-0.5 hover:text-rose-500"><flux:icon.x-mark variant="micro" /></button>
                                    </span>
                                </div>
                            @endforeach
                        </div>
                        <div class="mt-3 flex gap-2">
                            <flux:select wire:model="queueVenue" size="sm" placeholder="Add a venue…">
                                @foreach ($venues as $v)<flux:select.option value="{{ $v->id }}">{{ $v->name }}</flux:select.option>@endforeach
                            </flux:select>
                            <flux:button size="sm" icon="plus" wire:click="addToQueue" />
                        </div>
                    </div>
                </aside>
            </div>
        @endif

        {{-- ================= FILES ================= --}}
        @if ($tab === 'files')
            <div class="mb-4 flex items-center justify-between">
                <div><h2 class="font-semibold">Files</h2><p class="text-xs text-zinc-500">All optional. Upload manuscripts, cover letters, responses or the publisher’s proof PDF.</p></div>
                <flux:button size="sm" variant="primary" icon="arrow-up-tray" wire:click="openUpload">Upload</flux:button>
            </div>
            @if ($paper->files->isEmpty())
                <div class="panel"><x-ui.empty title="No files" text="Files are optional. Keep them here if you want the full manuscript and the proof next to the record."><flux:button wire:click="openUpload">Upload a file</flux:button></x-ui.empty></div>
            @else
                <div class="stagger grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($paper->files as $f)
                        @php $grad = ['proof' => 'from-rose-500 to-orange-400', 'manuscript' => 'from-indigo-500 to-violet-500', 'anonymized' => 'from-zinc-500 to-zinc-700', 'cover' => 'from-sky-500 to-cyan-400', 'response' => 'from-amber-500 to-yellow-400', 'supplementary' => 'from-emerald-500 to-teal-400'][$f->kind] ?? 'from-zinc-400 to-zinc-500'; @endphp
                        <div class="panel lift group p-4" wire:key="f{{ $f->id }}">
                            <div class="flex items-start gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-lg bg-gradient-to-br {{ $grad }} text-[10px] font-bold text-white uppercase">{{ str(pathinfo($f->original_name, PATHINFO_EXTENSION))->limit(4, '') }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="truncate text-sm font-medium" title="{{ $f->original_name }}">{{ $f->label ?: $f->original_name }}</p>
                                    <p class="text-xs text-zinc-500">{{ PaperFile::KINDS[$f->kind] }} · {{ $f->humanSize() }} · {{ $f->created_at->format('j M Y') }}</p>
                                </div>
                            </div>
                            <div class="mt-3 flex gap-1">
                                @if ($f->isPdf())<flux:button size="xs" icon="eye" wire:click="preview({{ $f->id }})">Preview</flux:button>@endif
                                <flux:button size="xs" variant="ghost" icon="arrow-down-tray" :href="route('files.download', $f)">Download</flux:button>
                                <flux:button size="xs" variant="ghost" icon="trash" class="ml-auto opacity-0 group-hover:opacity-100" wire:click="deleteFile({{ $f->id }})" wire:confirm="Delete this file?" />
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif

    </div>

    {{-- ================= MODALS ================= --}}
    <flux:modal name="paper-form" class="w-full max-w-2xl">
        @if ($modal === 'paper-form')
        <form wire:submit="savePaper" class="space-y-4">
            <flux:heading size="lg">Edit paper</flux:heading>
            <flux:input wire:model="p.title" label="Title" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="p.short_title" label="Short name" />
                <flux:select wire:model="p.stage" label="Stage">@foreach (Paper::STAGES as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="p.keywords" label="Keywords" placeholder="comma separated" />
                <div class="sm:col-span-3"><flux:input wire:model="p.tags" label="Tags" placeholder="Thesis, Preprint" description:trailing="Short labels such as Thesis or Preprint. Topics belong in Keywords." /></div>
                <flux:input wire:model="p.started_on" type="date" label="Started on" />
                <flux:input wire:model="p.method" label="Method" />
                <flux:input wire:model="p.dataset" label="Data" />
            </div>
            <flux:textarea wire:model="p.abstract" label="Abstract" rows="5" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="p.overleaf_url" label="Overleaf link" placeholder="https://" />
                <flux:input wire:model="p.drive_url" label="Drive link" placeholder="https://" />
            </div>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="p.preprint_server" label="Preprint server" placeholder="arXiv" />
                <flux:input wire:model="p.preprint_url" label="Preprint link" placeholder="https://arxiv.org/abs/…" />
                <flux:input wire:model="p.preprint_on" type="date" label="Posted on" />
            </div>
            <flux:textarea wire:model="p.notes" label="Notes" rows="3" />
            <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div>
        </form>
        @endif
    </flux:modal>

    <flux:modal name="sub-form" class="w-full max-w-xl">
        @if ($modal === 'sub-form')
        <form wire:submit="saveSubmission" class="space-y-4">
            <flux:heading size="lg">{{ $subId ? 'Edit submission' : 'Record submission' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="s.venue_id" label="Venue from your library">
                    <flux:select.option value="">Type a new one below</flux:select.option>
                    @foreach ($venues as $v)<flux:select.option value="{{ $v->id }}">{{ $v->name }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="s.venue_name" label="Venue name" />
                <flux:input wire:model="s.manuscript_id" label="Manuscript ID" placeholder="RNTW-2026-0123" />
                <flux:input wire:model="s.portal_url" label="Portal link" placeholder="https://" />
                <flux:input wire:model="s.submitted_on" type="date" label="Submitted on" />
                <flux:input wire:model="s.status_on" type="date" label="Status since" description:trailing="The day the current status began, e.g. the day it went under review" />
                <flux:select wire:model.live="s.decision" label="Status">@foreach (Submission::DECISIONS as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
                @if (! in_array($s['decision'] ?? 'submitted', Submission::WAITING, true))<flux:input wire:model="s.decision_on" type="date" label="Decision on" />@endif
                @if (in_array($s['decision'] ?? '', ['major', 'minor']))<flux:input wire:model="s.revision_due_on" type="date" label="Revision due" />@endif
            </div>
            <flux:checkbox wire:model="s.is_current" label="Current journal" description="The journal that has this paper now. Its status sets the paper’s stage, and it is listed first." />
            <flux:textarea wire:model="s.note" label="Note" rows="2" placeholder="Editor’s comments, reasons for the decision…" />
            <div class="flex items-center justify-between">
                <div>@if ($subId)<flux:button variant="ghost" class="!text-rose-600" wire:click="deleteSubmission" wire:confirm="Remove this submission from the history?">Remove</flux:button>@endif</div>
                <div class="flex gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div>
            </div>
        </form>
        @endif
    </flux:modal>

    <flux:modal name="comments" class="w-full max-w-xl">
        @if ($modal === 'comments')
        <form wire:submit="importComments" class="space-y-4">
            <flux:heading size="lg">Paste reviewer comments</flux:heading>
            <flux:text>Each paragraph or numbered point becomes one item on the checklist.</flux:text>
            <flux:textarea wire:model="comments" rows="10" placeholder="1. The sampling strategy needs more detail…&#10;2. Please discuss…" />
            <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Add to checklist</flux:button></div>
        </form>
        @endif
    </flux:modal>

    <flux:modal name="file-form" class="w-full max-w-lg">
        @if ($modal === 'file-form')
        <form wire:submit="saveFile" class="space-y-4">
            <flux:heading size="lg">Upload a file</flux:heading>
            <label class="flex cursor-pointer flex-col items-center justify-center rounded-xl border-2 border-dashed border-zinc-300 p-6 text-center transition hover:border-indigo-400 hover:bg-indigo-50/40 dark:border-zinc-700 dark:hover:bg-indigo-500/5">
                <flux:icon.cloud-arrow-up class="size-8 text-zinc-400" />
                <span class="mt-2 text-sm font-medium">{{ $upload ? $upload->getClientOriginalName() : 'Choose a file' }}</span>
                <span class="text-xs text-zinc-500">PDF, Word, LaTeX zip… up to 50 MB</span>
                <input type="file" wire:model="upload" class="sr-only" />
                <div wire:loading wire:target="upload" class="mt-2 text-xs text-indigo-600">Uploading…</div>
            </label>
            @error('upload')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="fileKind" label="Kind">@foreach (PaperFile::KINDS as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="fileLabel" label="Label (optional)" placeholder="v3, after R1" />
            </div>
            @if ($paper->submissions->isNotEmpty())
                <flux:select wire:model="fileSubmission" label="For submission (optional)">
                    <flux:select.option value="">None</flux:select.option>
                    @foreach ($paper->submissions as $sub)<flux:select.option value="{{ $sub->id }}">{{ $sub->venue_name }}</flux:select.option>@endforeach
                </flux:select>
            @endif
            <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary" wire:loading.attr="disabled" wire:target="upload">Upload</flux:button></div>
        </form>
        @endif
    </flux:modal>

    <flux:modal name="preview" class="w-full max-w-5xl !p-0">
        @if ($previewFile)
            <div class="flex items-center justify-between border-b border-zinc-200 px-5 py-3 dark:border-zinc-700">
                <p class="truncate text-sm font-medium">{{ $previewFile->label ?: $previewFile->original_name }}</p>
                <flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="route('files.download', $previewFile)" class="mr-8">Download</flux:button>
            </div>
            <iframe src="{{ route('files.show', $previewFile) }}" class="h-[78vh] w-full rounded-b-xl bg-zinc-100 dark:bg-zinc-900"></iframe>
        @endif
    </flux:modal>

    <flux:modal name="author-form" class="w-full max-w-xl">
        @if ($modal === 'author-form')
        <form wire:submit="saveAuthor" class="space-y-4">
            <flux:heading size="lg">{{ $authorId ? 'Edit author' : 'Add author' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="a.name" label="Name" />
                <flux:input wire:model="a.affiliation" label="Affiliation" />
                <flux:input wire:model="a.email" label="Email" type="email" />
                <flux:input wire:model="a.orcid" label="ORCID" placeholder="0000-0000-0000-0000" />
            </div>
            <flux:checkbox wire:model="a.corresponding" label="Corresponding author" />
            <div>
                <p class="mb-2 text-sm font-medium">CRediT roles</p>
                <div class="grid grid-cols-2 gap-2">@foreach (Author::CREDIT as $r)<flux:checkbox wire:model="a.roles" value="{{ $r }}" label="{{ $r }}" />@endforeach</div>
            </div>
            <div class="flex items-center justify-between">
                <div>@if ($authorId)<flux:button variant="ghost" class="!text-rose-600" wire:click="deleteAuthor" wire:confirm="Remove this author?">Remove</flux:button>@endif</div>
                <div class="flex gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div>
            </div>
        </form>
        @endif
    </flux:modal>
</div>
