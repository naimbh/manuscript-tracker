<?php

use App\Models\Publication;
use App\Support\CitationUpdater;
use App\Support\ScholarProfileUpdater;
use App\Support\VenueResearcher;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Published')] class extends Component {
    public ?int $editingId = null;
    public array $f = [];
    public string $sort = 'year';

    private const FIELDS = ['title', 'authors', 'kind', 'venue', 'volume', 'issue', 'pages', 'year', 'publisher', 'quartile', 'sjr', 'abs', 'abdc',
        'citations', 'scholar_url', 'cites_url', 'doi', 'url', 'notes'];

    public function edit(?int $id = null): void
    {
        $p = $id ? auth()->user()->publications()->findOrFail($id) : null;
        $this->editingId = $p?->id;
        $this->f = collect(self::FIELDS)->mapWithKeys(fn ($k) => [$k => $p?->$k ?? ''])->all();
        $this->f['kind'] = $p?->kind ?? 'article';
        $this->f['authors'] = $p?->authors ?? auth()->user()->name;
        $this->resetValidation();
        Flux::modal('pub-form')->show();
    }

    public function save(): void
    {
        $d = $this->validate([
            'f.title' => 'required|string|max:500', 'f.authors' => 'nullable|string|max:255', 'f.kind' => 'required|in:'.implode(',', array_keys(Publication::KINDS)),
            'f.venue' => 'nullable|string|max:255', 'f.volume' => 'nullable|string|max:40', 'f.issue' => 'nullable|string|max:40', 'f.pages' => 'nullable|string|max:40',
            'f.year' => 'nullable|integer|min:1900|max:2100', 'f.publisher' => 'nullable|string|max:255', 'f.quartile' => 'nullable|in:Q1,Q2,Q3,Q4',
            'f.sjr' => 'nullable|string|max:20', 'f.abs' => 'nullable|string|max:20', 'f.abdc' => 'nullable|string|max:20', 'f.citations' => 'nullable|integer|min:0',
            'f.scholar_url' => 'nullable|url|max:500', 'f.cites_url' => 'nullable|url|max:500', 'f.doi' => 'nullable|string|max:255', 'f.url' => 'nullable|url|max:500',
            'f.notes' => 'nullable|string|max:5000',
        ])['f'];
        $d = array_map(fn ($v) => $v === '' ? null : $v, $d);
        $this->editingId ? auth()->user()->publications()->findOrFail($this->editingId)->update($d) : auth()->user()->publications()->create($d);
        Flux::modal('pub-form')->close();
        Flux::toast(variant: 'success', text: 'Publication saved.');
    }

    public function delete(): void
    {
        auth()->user()->publications()->findOrFail($this->editingId)->delete();
        Flux::modal('pub-form')->close();
    }

    public string $scholarUrl = '';

    public function mount(): void
    {
        $this->scholarUrl = auth()->user()->scholar_url ?? '';
    }

    public function saveScholar(): void
    {
        $this->validate(['scholarUrl' => ['required', 'url', 'regex:#^https://scholar\.google\.[a-z.]+/citations\?#']]);
        $u = auth()->user();
        $changed = $u->scholar_url !== $this->scholarUrl;
        $u->forceFill(['scholar_url' => $this->scholarUrl] + ($changed ? ['scholar_stats' => null, 'scholar_checked_at' => null] : []))->save();
        Flux::modal('scholar')->close();
        Flux::toast(variant: 'success', text: 'Scholar profile saved.');
    }

    public function refreshProfile(): void
    {
        $run = \App\Models\AiRun::begin(auth()->user(), 'citations', 'Citation update', 1, route('publications'))->step('Reading your Google Scholar profile');
        try {
            [$n, $new] = app(ScholarProfileUpdater::class)->refresh(auth()->user());
            $run->finish();
            Flux::toast(variant: 'success', heading: 'Citations updated', text: "{$n} articles updated from Google Scholar.".($new ? ' Not in your list yet: '.implode('; ', array_slice($new, 0, 3)).'.' : ''));
        } catch (\Throwable $e) {
            $run->finish($e->getMessage());
            Flux::toast(variant: 'danger', heading: 'Update failed', text: $e->getMessage());
        }
    }

    public function refreshOne(int $id): array
    {
        $pub = auth()->user()->publications()->findOrFail($id);
        $before = $pub->citations;
        try {
            app(CitationUpdater::class)->refresh($pub);

            return ['ok' => true, 'title' => $pub->title, 'before' => $before, 'after' => $pub->citations];
        } catch (\Throwable $e) {
            return ['ok' => false, 'title' => $pub->title, 'error' => $e->getMessage()];
        }
    }

    public function with(): array
    {
        $pubs = auth()->user()->publications()->get();
        $cites = $pubs->pluck('citations')->filter()->sortDesc()->values();
        $h = 0;
        foreach ($cites as $i => $c) {
            if ($c >= $i + 1) {
                $h = $i + 1;
            }
        }
        $sorted = $this->sort === 'cites'
            ? $pubs->sortByDesc(fn ($p) => [$p->citations ?? -1, $p->year])
            : $pubs->sortByDesc(fn ($p) => [$p->year ?? 0, $p->citations ?? 0]);

        return [
            'pubs' => $sorted->values(),
            'total' => $cites->sum(),
            'h' => $h,
            'i10' => $cites->filter(fn ($c) => $c >= 10)->count(),
            'max' => max(1, $cites->first() ?? 1),
            'byYear' => $pubs->groupBy('year')->sortKeys(),
            'aiReady' => VenueResearcher::configured(),
            'aiStatus' => VenueResearcher::status(),
            'scholar' => auth()->user()->scholar_url,
            'stats' => auth()->user()->scholar_stats,
            'statsChecked' => auth()->user()->scholar_checked_at,
            'ranks' => $pubs->groupBy(fn ($p) => $p->quartile ?: 'NA')->map->count()->sortBy(fn ($n, $k) => $k === 'NA' ? 'Z' : $k),
            'lastCheck' => $pubs->max('checked_at'),
        ];
    }
}; ?>

<div class="page-in mx-auto w-full max-w-6xl space-y-6"
     x-data="{
        busy: {}, run: false, done: 0, total: 0,
        async one(id) { this.busy[id] = true; const r = await $wire.refreshOne(id); this.busy[id] = false; return r; },
        async single(id) {
            const r = await this.one(id);
            $flux.toast(r.ok ? { variant: 'success', text: r.after === null ? 'Details updated; Scholar count not readable.' : 'Cited by ' + r.after + (r.before !== null && r.after !== r.before ? ' (was ' + r.before + ')' : '') }
                             : { variant: 'danger', heading: 'Update failed', text: r.error });
            $wire.$refresh();
        },
        async all(ids) {
            this.run = true; this.done = 0; this.total = ids.length; let changed = 0, failed = 0;
            for (const id of ids) { if (! this.run) break; const r = await this.one(id); this.done++; if (! r.ok) failed++; else if (r.after !== r.before) changed++; }
            this.run = false; $wire.$refresh();
            $flux.toast({ variant: failed ? 'warning' : 'success', text: changed + ' counts changed' + (failed ? ', ' + failed + ' failed.' : '.') });
        }
     }">

    <section class="hero relative overflow-hidden rounded-2xl border border-zinc-200/80 px-6 py-6 sm:px-8 dark:border-zinc-800">
        <div class="hero-grid pointer-events-none absolute inset-0 text-zinc-900 dark:text-white"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="eyebrow">Published</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Your published work</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                    Citation counts from Google Scholar.
                    @if ($lastCheck) Last checked {{ $lastCheck->diffForHumans() }}. @endif
                    Updated every {{ ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'][config('tracker.citations_day')] ?? 'week' }}.
                    @if ($scholar) <a href="{{ $scholar }}" target="_blank" class="font-medium text-indigo-600 hover:underline">Open Scholar profile ↗</a>@endif
                </p>
            </div>
            <div class="flex gap-2">
                @if (! $scholar)<flux:button icon="academic-cap" x-on:click="$flux.modal('scholar').show()">Connect Google Scholar</flux:button>@endif
                <flux:button icon="plus" wire:click="edit">Add</flux:button>
                <flux:button variant="primary" icon="sparkles" :disabled="! $aiReady" x-show="! run" wire:click="refreshProfile" wire:loading.attr="disabled" wire:target="refreshProfile" :tooltip="$aiStatus"><span wire:loading.remove wire:target="refreshProfile">Update from Scholar</span><span wire:loading wire:target="refreshProfile">Reading Scholar…</span></flux:button>
                <flux:button variant="danger" icon="stop" x-show="run" x-cloak x-on:click="run = false">Stop</flux:button>
            </div>
        </div>
        <div x-show="run" x-cloak class="relative mt-4">
            <div class="mb-1 flex justify-between text-xs text-zinc-600 dark:text-zinc-400"><span>Reading Google Scholar…</span><span class="num" x-text="done + ' / ' + total"></span></div>
            <div class="clock-track !h-2"><div class="clock-fill bg-gradient-to-r from-indigo-500 to-fuchsia-500" :style="'width:' + (total ? done / total * 100 : 0) + '%'"></div></div>
        </div>

    </section>

    @php
        $all = $stats['all'] ?? ['citations' => $total, 'h' => $h, 'i10' => $i10];
        $recent = $stats['recent'] ?? null;
        $years = collect($stats['by_year'] ?? []);
        $ymax = max(1, $years->max() ?? 1);
        $step = $ymax > 50 ? 30 : ($ymax > 20 ? 10 : 5);
        $top = (int) ceil($ymax / $step) * $step;
        $qcol = ['Q1' => 'bg-emerald-500', 'Q2' => 'bg-lime-500', 'Q3' => 'bg-amber-400', 'Q4' => 'bg-orange-400', 'NA' => 'bg-zinc-300 dark:bg-zinc-600'];
    @endphp
    <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
        {{-- Article list, Scholar style --}}
        <div class="space-y-4">
            @if ($pubs->isNotEmpty())
                <div class="panel overflow-hidden">
                    <div class="flex items-center justify-between bg-gradient-to-r from-sky-50 to-cyan-50 px-4 py-2.5 text-xs dark:from-sky-500/10 dark:to-cyan-500/5">
                        <span class="font-semibold text-zinc-700 dark:text-zinc-200">Journal rankings</span>
                        <span class="text-zinc-500">{{ $pubs->count() }} papers</span>
                    </div>
                    <div class="flex h-11 w-full text-[11px]">
                        @foreach ($ranks as $q => $n)
                            <div class="grow-x flex flex-col justify-center px-2 {{ $qcol[$q] ?? 'bg-zinc-200' }} {{ $q === 'NA' ? 'text-zinc-600 dark:text-zinc-200' : 'text-white' }}" style="width: {{ round($n / $pubs->count() * 100) }}%" title="{{ $q }}: {{ $n }}">
                                <span class="font-semibold">{{ $q }}</span><span class="num opacity-80">{{ round($n / $pubs->count() * 100) }}% · {{ $n }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="panel overflow-hidden">
                <div class="grid grid-cols-[1fr_4.5rem_3.5rem] items-center border-b border-zinc-200 bg-zinc-50 px-4 py-2.5 text-[11px] font-semibold tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:bg-zinc-900">
                    <button wire:click="$set('sort', 'year')" class="text-left {{ $sort === 'year' ? 'text-indigo-600' : '' }}">Title</button>
                    <button wire:click="$set('sort', 'cites')" class="text-right {{ $sort === 'cites' ? 'text-indigo-600' : '' }}">Cited by</button>
                    <button wire:click="$set('sort', 'year')" class="text-right {{ $sort === 'year' ? 'text-indigo-600' : '' }}">Year</button>
                </div>
                <div class="stagger divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($pubs as $p)
                        <div wire:key="pub{{ $p->id }}" class="group grid grid-cols-[1fr_4.5rem_3.5rem] gap-x-2 px-4 py-3.5 transition-colors hover:bg-zinc-50/80 dark:hover:bg-zinc-800/30" :class="busy[{{ $p->id }}] && 'bg-indigo-50/60 dark:bg-indigo-500/5'">
                            <div class="min-w-0">
                                <a href="{{ $p->scholar_url ?: ($p->url ?: '#') }}" target="_blank" class="leading-snug font-medium text-indigo-700 hover:underline dark:text-indigo-300">{{ $p->title }}</a>
                                <span class="ml-1 inline-block rounded-full px-1.5 py-px align-middle text-[10px] font-bold {{ $p->quartile ? ($qcol[$p->quartile].' text-white') : 'bg-zinc-200 text-zinc-500 dark:bg-zinc-700 dark:text-zinc-300' }}">{{ $p->quartile ?: 'NA' }}</span>
                                <p class="mt-0.5 text-[13px] text-zinc-500">{{ $p->authors }}</p>
                                <p class="text-[13px] text-zinc-500">
                                    @if ($p->sjr)<span class="num mr-1 text-[11px] text-zinc-400">{{ $p->sjr }}</span>@endif
                                    {{ $p->citation() ?: $p->publisher }}
                                </p>
                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] opacity-70 transition group-hover:opacity-100">
                                    <span class="text-zinc-400">{{ \App\Models\Publication::KINDS[$p->kind] ?? $p->kind }}</span>
                                    @if ($p->cites_url)<a href="{{ $p->cites_url }}" target="_blank" class="text-zinc-500 hover:text-indigo-600">Citing papers</a>@endif
                                    @if ($p->doi)<a href="https://doi.org/{{ $p->doi }}" target="_blank" class="text-zinc-500 hover:text-indigo-600">DOI</a>@endif
                                    @if ($p->url)<a href="{{ $p->url }}" target="_blank" class="text-zinc-500 hover:text-indigo-600">Publisher</a>@endif
                                    <span x-show="busy[{{ $p->id }}]" x-cloak class="flex items-center gap-1 text-indigo-600"><flux:icon.arrow-path variant="micro" class="size-3 animate-spin" />Checking…</span>
                                    <button class="text-zinc-500 hover:text-indigo-600 disabled:opacity-40" @disabled(! $aiReady) x-bind:disabled="busy[{{ $p->id }}] || run" x-on:click="single({{ $p->id }})">Update</button>
                                    <button class="text-zinc-500 hover:text-indigo-600" wire:click="edit({{ $p->id }})">Edit</button>
                                </div>
                            </div>
                            <div class="num pt-0.5 text-right">
                                @if ($p->citations)
                                    <a href="{{ $p->cites_url ?: '#' }}" target="_blank" class="text-sm font-medium text-indigo-700 hover:underline dark:text-indigo-300">{{ $p->citations }}</a>
                                @endif
                            </div>
                            <div class="num pt-0.5 text-right text-sm text-zinc-700 dark:text-zinc-300">{{ $p->year }}</div>
                        </div>
                    @empty
                        <x-ui.empty title="Nothing published yet" text="Add your published work, and its citations will be kept up to date."><flux:button wire:click="edit">Add publication</flux:button></x-ui.empty>
                    @endforelse
                </div>
                @if ($pubs->isNotEmpty())<div class="border-t border-zinc-100 py-2.5 text-center text-xs text-zinc-500 dark:border-zinc-800">Articles 1–{{ $pubs->count() }}</div>@endif
            </div>
        </div>

        {{-- Cited by box --}}
        <aside class="space-y-4">
            <div class="panel p-4">
                <div class="flex items-center justify-between">
                    <h2 class="font-semibold">Cited by</h2>
                    <button class="text-xs text-zinc-500 hover:text-indigo-600" x-on:click="$flux.modal('scholar').show()">{{ $scholar ? 'Profile' : 'Add profile' }}</button>
                </div>
                <table class="num mt-3 w-full text-sm">
                    <thead><tr class="border-b border-zinc-200 text-xs text-zinc-500 dark:border-zinc-700"><th class="pb-1.5 text-left font-medium"></th><th class="pb-1.5 text-right font-medium">All</th>@if ($recent)<th class="pb-1.5 text-right font-medium">Since {{ $stats['since'] }}</th>@endif</tr></thead>
                    <tbody>
                        @foreach (['citations' => 'Citations', 'h' => 'h-index', 'i10' => 'i10-index'] as $k => $l)
                            <tr><td class="py-1 text-zinc-600 dark:text-zinc-300">{{ $l }}</td><td class="py-1 text-right font-medium">{{ $all[$k] ?? '–' }}</td>@if ($recent)<td class="py-1 text-right font-medium">{{ $recent[$k] ?? '–' }}</td>@endif</tr>
                        @endforeach
                    </tbody>
                </table>

                @if ($years->isNotEmpty())
                    <div class="relative mt-5 h-40 pr-7">
                        @foreach ([0, 1, 2] as $g)
                            @php $val = $top - $g * ($top / 2); @endphp
                            <div class="absolute right-0 left-0 border-t border-zinc-100 dark:border-zinc-800" style="top: {{ $g * 50 }}%"><span class="num absolute -top-2 right-0 bg-white pl-1 text-[10px] text-zinc-400 dark:bg-zinc-900">{{ (int) $val }}</span></div>
                        @endforeach
                        <div class="absolute inset-0 right-7 flex items-end justify-end gap-2 border-b border-zinc-200 dark:border-zinc-700">
                            @foreach ($years as $y => $c)
                                <div class="group relative flex w-7 flex-col items-center justify-end" style="height: 100%">
                                    <span class="num pointer-events-none absolute -top-5 hidden rounded bg-zinc-900 px-1 text-[10px] text-white group-hover:block dark:bg-white dark:text-zinc-900">{{ $c }}</span>
                                    <div class="w-full origin-bottom rounded-t bg-gradient-to-t from-zinc-500 to-zinc-400 transition-all duration-700 group-hover:from-indigo-600 group-hover:to-fuchsia-500 dark:from-zinc-500 dark:to-zinc-400" style="height: {{ max(1, $c / $top * 100) }}%; animation: grow-y .9s cubic-bezier(.2,.7,.2,1) both {{ $loop->index * 0.08 }}s"></div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="flex justify-end gap-2 pr-7 pt-1">@foreach ($years as $y => $c)<span class="num w-7 text-center text-[10px] text-zinc-500">{{ $y }}</span>@endforeach</div>
                @endif

                <div class="mt-4 flex items-center justify-between border-t border-zinc-100 pt-3 text-[11px] text-zinc-400 dark:border-zinc-800">
                    <span>{{ $statsChecked ? 'From Scholar '.$statsChecked->diffForHumans() : ($stats['note'] ?? 'Not read from Scholar yet') }}</span>
                    <button class="font-medium text-indigo-600 hover:underline disabled:text-zinc-400 disabled:no-underline" @disabled(! $aiReady || ! $scholar) wire:click="refreshProfile" wire:loading.attr="disabled" wire:target="refreshProfile">
                        <span wire:loading.remove wire:target="refreshProfile">Refresh</span><span wire:loading wire:target="refreshProfile">Reading…</span>
                    </button>
                </div>
            </div>
        </aside>
    </div>

    <flux:modal name="scholar" class="w-full max-w-lg">
        <form wire:submit="saveScholar" class="space-y-4">
            <flux:heading size="lg">Google Scholar profile</flux:heading>
            <flux:text>The weekly update reads this page for your citation table, the per-year chart and each article’s count.</flux:text>
            <flux:input wire:model="scholarUrl" placeholder="https://scholar.google.com/citations?user=…" />
            <div class="flex justify-end gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div>
        </form>
    </flux:modal>

    <flux:modal name="pub-form" class="w-full max-w-2xl">
        <form wire:submit="save" class="space-y-4">
            <flux:heading size="lg">{{ $editingId ? 'Edit publication' : 'Add publication' }}</flux:heading>
            <flux:input wire:model="f.title" label="Title" />
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2"><flux:input wire:model="f.authors" label="Authors" /></div>
                <flux:select wire:model="f.kind" label="Type">@foreach (Publication::KINDS as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach</flux:select>
                <div class="sm:col-span-2"><flux:input wire:model="f.venue" label="Journal / venue" /></div>
                <flux:input wire:model="f.year" type="number" label="Year" />
                <flux:input wire:model="f.volume" label="Volume" />
                <flux:input wire:model="f.issue" label="Issue" />
                <flux:input wire:model="f.pages" label="Pages" />
                <flux:select wire:model="f.quartile" label="SJR quartile"><flux:select.option value="">–</flux:select.option>@foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q)<flux:select.option value="{{ $q }}">{{ $q }}</flux:select.option>@endforeach</flux:select>
                <flux:input wire:model="f.sjr" label="SJR score" />
                <flux:input wire:model="f.citations" type="number" label="Cited by" />
                <flux:input wire:model="f.abs" label="ABS" />
                <flux:input wire:model="f.abdc" label="ABDC" />
                <flux:input wire:model="f.publisher" label="Publisher / institution" />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="f.scholar_url" label="Scholar record" placeholder="https://scholar.google.com/…" />
                <flux:input wire:model="f.cites_url" label="Scholar “cited by” link" />
                <flux:input wire:model="f.doi" label="DOI" placeholder="10.xxxx/…" />
                <flux:input wire:model="f.url" label="Publisher link" />
            </div>
            <flux:textarea wire:model="f.notes" label="Notes" rows="2" />
            <div class="flex items-center justify-between">
                <div>@if ($editingId)<flux:button variant="ghost" class="!text-rose-600" wire:click="delete" wire:confirm="Remove this publication?">Remove</flux:button>@endif</div>
                <div class="flex gap-2"><flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close><flux:button type="submit" variant="primary">Save</flux:button></div>
            </div>
        </form>
    </flux:modal>
</div>
