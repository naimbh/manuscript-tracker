<?php

use App\Models\Venue;
use App\Support\VenueCatalog;
use App\Support\VenueResearcher;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Venues')] class extends Component {
    #[Url(except: 'conference')]
    public string $tab = 'conference';

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: '')]
    public string $field = '';

    public ?int $editingId = null;
    public bool $formOpen = false;
    public array $form = [];

    private const FIELDS = ['name', 'kind', 'field', 'publisher', 'rank', 'quartile', 'acceptance_rate', 'first_decision', 'proceedings',
        'next_edition', 'deadline_on', 'deadline_label', 'deadline_expected', 'review_type', 'word_limit', 'decision_days', 'fee',
        'preprint_policy', 'portal_url', 'website_url', 'watch', 'notes'];

    public function mount(): void
    {
        $this->blankForm();
    }

    private function blankForm(): void
    {
        $this->form = array_fill_keys(self::FIELDS, '');
        $this->form['kind'] = $this->tab === 'journal' ? 'journal' : 'conference';
        $this->form['review_type'] = 'double';
        $this->form['preprint_policy'] = 'unknown';
        $this->form['deadline_expected'] = false;
        $this->form['watch'] = $this->form['kind'] === 'conference';
    }

    public function add(): void
    {
        $this->editingId = null;
        $this->blankForm();
        $this->resetValidation();
        $this->formOpen = true;
        Flux::modal('venue-form')->show();
    }

    public function edit(int $id): void
    {
        $v = auth()->user()->venues()->findOrFail($id);
        $this->editingId = $v->id;
        $this->form = collect(self::FIELDS)->mapWithKeys(fn ($f) => [$f => $f === 'deadline_on' ? $v->deadline_on?->toDateString() : $v->$f])->all();
        $this->resetValidation();
        $this->formOpen = true;
        Flux::modal('venue-form')->show();
    }

    public function save(): void
    {
        $data = $this->validate([
            'form.name' => 'required|string|max:255',
            'form.kind' => 'required|in:journal,conference,preprint',
            'form.field' => 'nullable|string|max:255',
            'form.publisher' => 'nullable|string|max:255',
            'form.rank' => 'nullable|string|max:255',
            'form.quartile' => 'nullable|in:Q1,Q2,Q3,Q4',
            'form.acceptance_rate' => 'nullable|string|max:255',
            'form.first_decision' => 'nullable|string|max:255',
            'form.proceedings' => 'nullable|string|max:255',
            'form.next_edition' => 'nullable|string|max:255',
            'form.deadline_on' => 'nullable|date',
            'form.deadline_label' => 'nullable|string|max:255',
            'form.deadline_expected' => 'boolean',
            'form.review_type' => 'required|in:'.implode(',', array_keys(Venue::REVIEW_TYPES)),
            'form.word_limit' => 'nullable|string|max:255',
            'form.decision_days' => 'nullable|integer|min:1|max:2000',
            'form.fee' => 'nullable|string|max:255',
            'form.preprint_policy' => 'required|in:'.implode(',', array_keys(Venue::PREPRINT_POLICIES)),
            'form.portal_url' => 'nullable|url|max:255',
            'form.website_url' => 'nullable|url|max:255',
            'form.watch' => 'boolean',
            'form.notes' => 'nullable|string|max:5000',
        ])['form'];
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $this->editingId
            ? auth()->user()->venues()->findOrFail($this->editingId)->update($data)
            : auth()->user()->venues()->create($data);

        $this->tab = $data['kind'] === 'journal' ? 'journal' : 'conference';
        Flux::modal('venue-form')->close();
        Flux::toast(variant: 'success', text: $this->editingId ? 'Venue updated.' : 'Venue added.');
    }

    public function delete(): void
    {
        auth()->user()->venues()->findOrFail($this->editingId)->delete();
        Flux::modal('venue-form')->close();
        Flux::toast(text: 'Venue removed.');
    }

    public function toggleWatch(int $id): void
    {
        $v = auth()->user()->venues()->findOrFail($id);
        $v->update(['watch' => ! $v->watch]);
    }

    public ?int $runId = null;

    public function startBatch(int $total): void
    {
        $this->runId = \App\Models\AiRun::begin(auth()->user(), 'venues', 'Updating '.($this->tab === 'journal' ? 'journals' : 'conferences'), $total, route('venues'))->id;
        $this->dispatch('ai-runs-changed');
    }

    public function refreshVenue(int $id): array
    {
        $venue = auth()->user()->venues()->findOrFail($id);
        $run = $this->runId ? auth()->user()->aiRuns()->find($this->runId) : null;
        $run?->step('Checking '.$venue->name);
        try {
            $r = app(VenueResearcher::class);
            $r->apply($venue, $r->research($venue));
            $run?->advance();

            return ['ok' => true, 'name' => $venue->name];
        } catch (\Throwable $e) {
            $run?->note('Error on '.$venue->name.': '.$e->getMessage());
            $run?->advance();

            return ['ok' => false, 'name' => $venue->name, 'error' => $e->getMessage()];
        }
    }

    public function notifyBatch(int $done, int $failed): void
    {
        if ($this->runId) {
            auth()->user()->aiRuns()->find($this->runId)?->finish();
            $this->runId = null;
        }
        auth()->user()->notify(new \App\Notifications\AiJobFinished('Venue update finished',
            "{$done} venues checked".($failed ? ", {$failed} failed." : '.'), $failed ? 'warning' : 'success', route('venues')));
        $this->dispatch('notifications-changed');
    }

    public function restoreCatalog(): void
    {
        $n = VenueCatalog::seedFor(auth()->user());
        Flux::toast(variant: 'success', text: $n ? "Added {$n} venues from the starter list." : 'You already have every venue in the starter list.');
    }

    public function with(): array
    {
        $all = auth()->user()->venues()->withCount('submissions')->get();
        $kind = $this->tab === 'journal' ? 'journal' : 'conference';
        $list = $all->where('kind', $kind);
        $fields = $list->pluck('field')->filter()->unique()->sort()->values();
        if ($this->field) {
            $list = $list->where('field', $this->field);
        }
        if ($q = mb_strtolower(trim($this->q))) {
            $list = $list->filter(fn ($v) => str_contains(mb_strtolower($v->name.' '.$v->publisher.' '.$v->field.' '.$v->next_edition), $q));
        }

        if ($kind === 'conference') {
            $list = $list->sortBy(fn ($v) => match (true) {
                $v->deadline_on && $v->deadline_on->gte(today()) => '0'.$v->deadline_on->format('Ymd'),
                $v->deadline_label !== null => '1'.$v->name,
                default => '2'.$v->name,
            });
        } else {
            $list = $list->sortBy(fn ($v) => ($v->quartile ?: 'Q9').$v->name);
        }

        return [
            'list' => $list->values(),
            'fields' => $fields,
            'counts' => ['conference' => $all->where('kind', 'conference')->count(), 'journal' => $all->where('kind', 'journal')->count()],
            'soon' => $all->where('kind', 'conference')->filter(fn ($v) => $v->deadline_on?->gte(today()))->sortBy('deadline_on')->take(4)->values(),
            'aiReady' => VenueResearcher::configured(),
            'aiStatus' => VenueResearcher::status(),
            'ids' => $list->pluck('id')->values(),
        ];
    }
}; ?>

<div class="page-in mx-auto w-full max-w-6xl space-y-6"
     x-data="{
        busy: {}, run: null, done: 0, total: 0, errors: [],
        async one(id) {
            this.busy[id] = true;
            const r = await $wire.refreshVenue(id);
            this.busy[id] = false;
            if (! r.ok) this.errors.push(r.name + ': ' + r.error);
            return r;
        },
        async single(id) {
            this.errors = [];
            const r = await this.one(id);
            $flux.toast(r.ok ? { variant: 'success', text: 'Updated ' + r.name + '.' } : { variant: 'danger', heading: 'Update failed', text: r.error });
            $wire.$refresh();
        },
        async all(ids) {
            this.errors = []; this.done = 0; this.total = ids.length; this.run = true; await $wire.startBatch(ids.length);
            for (const id of ids) { if (! this.run) break; await this.one(id); this.done++; }
            this.run = null; $wire.notifyBatch(this.done, this.errors.length); $wire.$refresh();
            $flux.toast({ variant: this.errors.length ? 'warning' : 'success', text: 'Checked ' + this.done + ' venues' + (this.errors.length ? ', ' + this.errors.length + ' failed.' : '.') });
        }
     }">

    {{-- Header --}}
    <section class="hero relative overflow-hidden rounded-2xl border border-zinc-200/80 px-4 py-5 sm:px-8 sm:py-6 dark:border-zinc-800">
        <div class="hero-grid pointer-events-none absolute inset-0 text-zinc-900 dark:text-white"></div>
        <div class="relative flex flex-col gap-4 sm:flex-row sm:flex-wrap sm:items-end sm:justify-between">
            <div>
                <p class="eyebrow">Venue library</p>
                <h1 class="mt-1 text-xl font-semibold tracking-tight sm:text-2xl">Where your papers can go</h1>
                <p class="mt-1 max-w-xl text-sm text-zinc-600 dark:text-zinc-400">Conferences that publish proceedings, and journals with their quartile, speed and acceptance rate. Keep them current with one click.</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <flux:tooltip :content="$aiStatus">
                    <span class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-xs font-medium {{ $aiReady ? 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-zinc-200 bg-white/70 text-zinc-500 dark:border-zinc-700 dark:bg-zinc-900/60' }}">
                        <flux:icon.sparkles variant="micro" /> {{ $aiReady ? 'AI updates on' : 'AI updates off' }}
                    </span>
                </flux:tooltip>
                <flux:button icon="plus" wire:click="add">Add venue</flux:button>
                <template x-if="! run">
                    <flux:button variant="primary" icon="sparkles" x-on:click="all({{ $ids->toJson() }})" :disabled="! $aiReady">Update {{ $tab === 'journal' ? 'journals' : 'conferences' }}</flux:button>
                </template>
                <template x-if="run">
                    <flux:button variant="danger" icon="stop" x-on:click="run = false">Stop</flux:button>
                </template>
            </div>
        </div>

        {{-- Progress --}}
        <div x-show="run" x-transition.opacity class="relative mt-5" x-cloak>
            <div class="mb-1.5 flex justify-between text-xs text-zinc-600 dark:text-zinc-400">
                <span class="flex items-center gap-2"><flux:icon.arrow-path variant="micro" class="animate-spin" /> Searching the web for the latest calls and journal data…</span>
                <span class="num" x-text="done + ' / ' + total"></span>
            </div>
            <div class="clock-track !h-2"><div class="clock-fill bg-gradient-to-r from-indigo-500 to-fuchsia-500" :style="'width:' + (total ? done / total * 100 : 0) + '%'"></div></div>
        </div>

        @if ($tab === 'conference' && $soon->isNotEmpty())
            <div class="stagger relative mt-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($soon as $v)
                    @php $d = $v->deadlineDays(); @endphp
                    <div class="lift rounded-xl border border-white/60 bg-white/70 p-3.5 backdrop-blur dark:border-white/5 dark:bg-zinc-900/60">
                        <div class="flex items-center justify-between">
                            <span class="num text-2xl font-semibold tracking-tight">{{ $d }}<span class="ml-0.5 text-sm font-medium text-zinc-400">days</span></span>
                            @if ($v->deadline_expected)<span class="rounded-full border border-dashed border-amber-400 px-1.5 text-[10px] font-medium text-amber-600">expected</span>@endif
                        </div>
                        <div class="mt-1 truncate text-sm font-medium">{{ str($v->name)->before(' (') }}</div>
                        <div class="truncate text-xs text-zinc-500">{{ $v->deadline_on->format('j M Y') }} · {{ $v->deadline_label }}</div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- Errors from AI runs --}}
    <template x-if="errors.length">
        <flux:callout variant="warning" icon="exclamation-triangle" heading="Some updates did not finish">
            <flux:callout.text><ul class="list-disc pl-4"><template x-for="e in errors"><li x-text="e"></li></template></ul></flux:callout.text>
        </flux:callout>
    </template>

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="relative flex rounded-lg bg-zinc-100 p-0.5 text-sm font-medium dark:bg-zinc-800">
            @foreach (['conference' => 'Conferences', 'journal' => 'Journals'] as $k => $label)
                <button wire:click="$set('tab', '{{ $k }}'); $set('field', '')" class="relative rounded-md px-3.5 py-1.5 transition {{ $tab === $k ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                    {{ $label }} <span class="num ml-1 text-xs opacity-60">{{ $counts[$k] }}</span>
                </button>
            @endforeach
        </div>
        <div class="w-full sm:w-64"><flux:input wire:model.live.debounce.150ms="q" data-search icon="magnifying-glass" size="sm" placeholder="Search venues" clearable /></div>
        <div class="-mx-4 flex w-[calc(100%+2rem)] gap-1.5 overflow-x-auto px-4 pb-1 sm:mx-0 sm:w-auto sm:flex-wrap sm:overflow-visible sm:px-0 sm:pb-0 [&>button]:shrink-0">
            <button wire:click="$set('field', '')" class="rounded-full px-2.5 py-1 text-xs font-medium transition {{ $field === '' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300' }}">All fields</button>
            @foreach ($fields as $f)
                <button wire:click="$set('field', @js($f))" class="rounded-full px-2.5 py-1 text-xs font-medium transition {{ $field === $f ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : 'bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $f }}</button>
            @endforeach
        </div>
    </div>

    @if ($list->isEmpty())
        <div class="panel">
            <x-ui.empty art="venue" title="No venues here" text="Add one yourself, or bring back the starter list of IS, iSchool and HCI venues.">
                <div class="flex gap-2"><flux:button icon="plus" wire:click="add">Add venue</flux:button><flux:button variant="ghost" wire:click="restoreCatalog">Restore starter list</flux:button></div>
            </x-ui.empty>
        </div>
    @elseif ($tab === 'conference')
        {{-- Conference cards --}}
        <div class="stagger grid gap-3 md:grid-cols-2" wire:key="conf-{{ $field }}-{{ $q }}">
            @foreach ($list as $v)
                @php $d = $v->deadlineDays(); $past = $d !== null && $d < 0; @endphp
                <article wire:key="v{{ $v->id }}" class="panel lift group relative flex gap-3 p-3.5 sm:gap-4 sm:p-4" :class="busy[{{ $v->id }}] && 'ring-2 ring-indigo-400/60'">
                    {{-- Date block --}}
                    <div class="flex w-14 shrink-0 flex-col items-center justify-center rounded-xl border py-2 text-center leading-none sm:w-16
                        {{ $v->deadline_on && ! $past ? ($v->deadline_expected ? 'border-dashed border-amber-300 bg-amber-50/60 dark:border-amber-500/40 dark:bg-amber-500/5' : 'border-indigo-200 bg-indigo-50/70 dark:border-indigo-500/30 dark:bg-indigo-500/10') : 'border-zinc-200 bg-zinc-50 dark:border-zinc-700 dark:bg-zinc-800/50' }}">
                        @if ($v->deadline_on)
                            <span class="text-[10px] font-semibold uppercase tracking-wide {{ $past ? 'text-zinc-400' : 'text-rose-500' }}">{{ $v->deadline_on->format('M') }}</span>
                            <span class="num mt-0.5 text-xl font-semibold {{ $past ? 'text-zinc-400 line-through' : '' }}">{{ $v->deadline_on->format('j') }}</span>
                            <span class="num mt-1 text-[10px] text-zinc-500">{{ $past ? 'passed' : ($d === 0 ? 'today' : $d.'d left') }}</span>
                        @else
                            <flux:icon.calendar class="size-5 text-zinc-300 dark:text-zinc-600" />
                            <span class="mt-1 text-[10px] text-zinc-400">{{ $v->deadline_label ? 'see note' : 'no date' }}</span>
                        @endif
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="text-sm leading-snug font-semibold">{{ $v->name }}</h3>
                                <p class="mt-0.5 text-xs text-zinc-500">{{ collect([$v->publisher, $v->field])->filter()->implode(' · ') }}</p>
                            </div>
                            <x-ui.menu class="shrink-0 sm:hidden">
                                <x-ui.menu-item wire:click="toggleWatch({{ $v->id }})">{{ $v->watch ? 'Stop watching' : 'Watch weekly' }}</x-ui.menu-item>
                                @if ($aiReady)<x-ui.menu-item x-on:click="single({{ $v->id }})">Update with AI</x-ui.menu-item>@endif
                                <x-ui.menu-item wire:click="edit({{ $v->id }})">Edit</x-ui.menu-item>
                            </x-ui.menu>
                            <div class="hidden shrink-0 items-center opacity-60 transition group-hover:opacity-100 sm:flex">
                                <button type="button" wire:click="toggleWatch({{ $v->id }})" title="{{ $v->watch ? 'Watched: updated weekly' : 'Watch for weekly updates' }}" class="inline-flex size-7 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200 {{ $v->watch ? '!text-indigo-600 dark:!text-indigo-400' : '' }}"><flux:icon :name="$v->watch ? 'bell-alert' : 'bell'" variant="micro" /></button>
                                <button type="button" x-on:click="single({{ $v->id }})" :disabled="busy[{{ $v->id }}] || run" @disabled(! $aiReady) title="Update with AI" class="inline-flex size-7 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"><flux:icon.sparkles variant="micro" /></button>
                                <button type="button" wire:click="edit({{ $v->id }})" title="Edit" class="inline-flex size-7 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"><flux:icon.pencil-square variant="micro" /></button>
                            </div>
                        </div>

                        <div class="mt-2.5 flex flex-wrap gap-1.5">
                            @if ($v->rank)<span class="rounded-md bg-violet-50 px-1.5 py-0.5 text-[11px] font-medium text-violet-700 dark:bg-violet-500/10 dark:text-violet-300">{{ $v->rank }}</span>@endif
                            @if ($v->acceptance_rate)<span class="rounded-md bg-sky-50 px-1.5 py-0.5 text-[11px] font-medium text-sky-700 dark:bg-sky-500/10 dark:text-sky-300">{{ $v->acceptance_rate }} accepted</span>@endif
                            @if ($v->proceedings)<span class="inline-flex items-center gap-1 rounded-md bg-zinc-100 px-1.5 py-0.5 text-[11px] font-medium text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300"><flux:icon.book-open variant="micro" class="size-3" />{{ $v->proceedings }}</span>@endif
                            @if ($v->submissions_count)<span class="rounded-md bg-emerald-50 px-1.5 py-0.5 text-[11px] font-medium text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">{{ $v->submissions_count }} of your submissions</span>@endif
                        </div>

                        <dl class="mt-2.5 space-y-1 text-xs">
                            @if ($v->next_edition)
                                <div class="flex gap-1.5 text-zinc-600 dark:text-zinc-300"><flux:icon.map-pin variant="micro" class="mt-px size-3.5 shrink-0 text-zinc-400" />{{ $v->next_edition }}</div>
                            @endif
                            @if ($v->deadline_label)
                                <div class="flex gap-1.5 text-zinc-600 dark:text-zinc-300"><flux:icon.clock variant="micro" class="mt-px size-3.5 shrink-0 text-zinc-400" />{{ $v->deadline_label }}@if ($v->deadline_expected)<span class="text-amber-600">· expected</span>@endif</div>
                            @endif
                        </dl>

                        <div class="mt-3 flex items-center justify-between border-t border-zinc-100 pt-2.5 text-[11px] text-zinc-400 dark:border-zinc-800">
                            <span x-show="! busy[{{ $v->id }}]">
                                @if ($v->checked_at) Checked {{ $v->checked_at->diffForHumans() }}@if ($v->sources) · <a href="{{ $v->sources[0] }}" target="_blank" class="hover:text-indigo-600">{{ count($v->sources) }} {{ str('source')->plural(count($v->sources)) }}</a>@endif
                                @else Not checked yet
                                @endif
                            </span>
                            <span x-show="busy[{{ $v->id }}]" x-cloak class="flex items-center gap-1.5 text-indigo-600"><flux:icon.arrow-path variant="micro" class="size-3 animate-spin" /> Searching…</span>
                            @if ($v->website_url)
                                <a href="{{ $v->website_url }}" target="_blank" class="inline-flex items-center gap-1 font-medium text-zinc-500 hover:text-indigo-600">Website <flux:icon.arrow-up-right variant="micro" class="size-3" /></a>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @else
        {{-- Journal cards (phones) --}}
        <div class="stagger space-y-2 sm:hidden">
            @foreach ($list as $v)
                @php $qc = ['Q1' => 'bg-emerald-500 text-white', 'Q2' => 'bg-lime-500 text-white', 'Q3' => 'bg-amber-400 text-white', 'Q4' => 'bg-zinc-400 text-white'][$v->quartile] ?? 'border border-dashed border-zinc-300 text-zinc-400 dark:border-zinc-600'; @endphp
                <div wire:key="jm{{ $v->id }}" class="panel p-3.5" :class="busy[{{ $v->id }}] && 'ring-2 ring-indigo-400/60'">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-6 min-w-8 shrink-0 items-center justify-center rounded-md px-1.5 text-xs font-bold {{ $qc }}">{{ $v->quartile ?? '?' }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm leading-snug font-medium">{{ $v->name }}</p>
                            <p class="mt-0.5 text-xs text-zinc-500">{{ collect([$v->publisher, $v->field])->filter()->implode(' · ') }}</p>
                        </div>
                        <x-ui.menu>
                            @if ($v->website_url)<x-ui.menu-item :href="$v->website_url" target="_blank">Website</x-ui.menu-item>@endif
                            @if ($aiReady)<x-ui.menu-item x-on:click="single({{ $v->id }})">Update with AI</x-ui.menu-item>@endif
                            <x-ui.menu-item wire:click="edit({{ $v->id }})">Edit</x-ui.menu-item>
                        </x-ui.menu>
                    </div>
                    <dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1.5 text-xs">
                        <div><dt class="text-zinc-400">First decision</dt><dd class="text-zinc-700 dark:text-zinc-300">{{ $v->first_decision ?? '–' }}</dd></div>
                        <div><dt class="text-zinc-400">Acceptance</dt><dd class="text-zinc-700 dark:text-zinc-300">{{ $v->acceptance_rate ?? '–' }}</dd></div>
                        <div><dt class="text-zinc-400">Review</dt><dd class="text-zinc-700 dark:text-zinc-300">{{ \App\Models\Venue::REVIEW_TYPES[$v->review_type] ?? '–' }}</dd></div>
                        <div><dt class="text-zinc-400">Fee</dt><dd class="truncate text-zinc-700 dark:text-zinc-300">{{ $v->fee ?? '–' }}</dd></div>
                    </dl>
                    <p x-show="busy[{{ $v->id }}]" x-cloak class="mt-2 flex items-center gap-1 text-[11px] text-indigo-600"><flux:icon.arrow-path variant="micro" class="size-3 animate-spin" />Searching…</p>
                </div>
            @endforeach
        </div>

        {{-- Journal table --}}
        <div class="panel hidden overflow-hidden sm:block">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="border-b border-zinc-200 bg-zinc-50/80 text-left text-[11px] font-semibold tracking-wide text-zinc-500 uppercase dark:border-zinc-800 dark:bg-zinc-900">
                            <th class="px-4 py-2.5">Journal</th>
                            <th class="px-3 py-2.5">Quartile</th>
                            <th class="px-3 py-2.5">First decision</th>
                            <th class="px-3 py-2.5">Acceptance</th>
                            <th class="px-3 py-2.5">Review</th>
                            <th class="px-3 py-2.5">Fee</th>
                            <th class="px-3 py-2.5">Words</th>
                            <th class="px-3 py-2.5"></th>
                        </tr>
                    </thead>
                    <tbody class="stagger">
                        @foreach ($list as $v)
                            @php $qc = ['Q1' => 'bg-emerald-500 text-white', 'Q2' => 'bg-lime-500 text-white', 'Q3' => 'bg-amber-400 text-white', 'Q4' => 'bg-zinc-400 text-white'][$v->quartile] ?? 'border border-dashed border-zinc-300 text-zinc-400 dark:border-zinc-600'; @endphp
                            <tr wire:key="j{{ $v->id }}" class="group border-b border-zinc-100 transition-colors last:border-0 hover:bg-zinc-50/70 dark:border-zinc-800/70 dark:hover:bg-zinc-800/30" :class="busy[{{ $v->id }}] && 'bg-indigo-50/60 dark:bg-indigo-500/5'">
                                <td class="px-4 py-3">
                                    <div class="font-medium">{{ $v->name }}</div>
                                    <div class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs text-zinc-500">
                                        <span>{{ collect([$v->publisher, $v->field])->filter()->implode(' · ') }}</span>
                                        @if ($v->submissions_count)<span class="text-emerald-600">· {{ $v->submissions_count }} of yours</span>@endif
                                        <span x-show="busy[{{ $v->id }}]" x-cloak class="flex items-center gap-1 text-indigo-600"><flux:icon.arrow-path variant="micro" class="size-3 animate-spin" />Searching…</span>
                                    </div>
                                </td>
                                <td class="px-3 py-3"><span class="inline-flex h-6 min-w-8 items-center justify-center rounded-md px-1.5 text-xs font-bold {{ $qc }}">{{ $v->quartile ?? '?' }}</span></td>
                                <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ $v->first_decision ?? '–' }}</td>
                                <td class="px-3 py-3 text-zinc-600 dark:text-zinc-300">{{ $v->acceptance_rate ?? '–' }}</td>
                                <td class="px-3 py-3 text-xs text-zinc-500">{{ \App\Models\Venue::REVIEW_TYPES[$v->review_type] ?? '–' }}</td>
                                <td class="px-3 py-3 text-xs text-zinc-500">{{ $v->fee ?? '–' }}</td>
                                <td class="px-3 py-3 text-xs text-zinc-500">{{ $v->word_limit ?? '–' }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-0.5 opacity-50 transition group-hover:opacity-100">
                                        @if ($v->website_url)<a href="{{ $v->website_url }}" target="_blank" title="Website" class="inline-flex size-7 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"><flux:icon.arrow-up-right variant="micro" /></a>@endif
                                        <button type="button" x-on:click="single({{ $v->id }})" :disabled="busy[{{ $v->id }}] || run" @disabled(! $aiReady) title="{{ $v->checked_at ? 'Checked '.$v->checked_at->diffForHumans() : 'Update with AI' }}" class="inline-flex size-7 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"><flux:icon.sparkles variant="micro" /></button>
                                        <button type="button" wire:click="edit({{ $v->id }})" title="Edit" class="inline-flex size-7 items-center justify-center rounded-md text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 disabled:opacity-40 dark:hover:bg-zinc-800 dark:hover:text-zinc-200"><flux:icon.pencil-square variant="micro" /></button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-zinc-100 px-4 py-2.5 text-[11px] text-zinc-400 dark:border-zinc-800">
                <span>Quartiles follow SJR. A dashed “?” means not checked yet; run an AI update to fill gaps from the publisher’s pages.</span>
                <button wire:click="restoreCatalog" class="hover:text-indigo-600">Restore starter list</button>
            </div>
        </div>
    @endif

    {{-- Add / edit --}}
    <flux:modal name="venue-form" class="w-full max-w-2xl">
        @if ($formOpen)
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? 'Edit venue' : 'Add venue' }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="sm:col-span-2"><flux:input wire:model="form.name" label="Name" placeholder="CHI 2027" /></div>
                <flux:select wire:model.live="form.kind" label="Type">
                    @foreach (Venue::KINDS as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="form.publisher" label="Publisher" />
                <flux:input wire:model="form.field" label="Field" placeholder="Information systems" />
                <flux:input wire:model="form.rank" label="Rank" placeholder="CORE A*" />
            </div>

            @if ($form['kind'] === 'journal')
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:select wire:model="form.quartile" label="Quartile (SJR)">
                        <flux:select.option value="">Not set</flux:select.option>
                        @foreach (['Q1', 'Q2', 'Q3', 'Q4'] as $q)<flux:select.option value="{{ $q }}">{{ $q }}</flux:select.option>@endforeach
                    </flux:select>
                    <flux:input wire:model="form.first_decision" label="First decision" placeholder="28 days (median)" />
                    <flux:input wire:model="form.acceptance_rate" label="Acceptance rate" placeholder="12%" />
                    <flux:input wire:model="form.fee" label="Fee / APC" placeholder="No fee" />
                    <flux:input wire:model="form.word_limit" label="Word limit" placeholder="8,000 incl. references" />
                    <flux:input wire:model="form.decision_days" type="number" label="Usual days to decision" description:trailing="Starts the review clock." />
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="sm:col-span-2"><flux:input wire:model="form.next_edition" label="Next edition" placeholder="10–14 May 2027, Pittsburgh" /></div>
                    <flux:input wire:model="form.acceptance_rate" label="Acceptance rate" placeholder="~25%" />
                    <flux:input wire:model="form.deadline_on" type="date" label="Next deadline" />
                    <flux:input wire:model="form.deadline_label" label="Deadline note" placeholder="Full papers" />
                    <div class="flex items-end pb-2"><flux:checkbox wire:model="form.deadline_expected" label="Date is expected, not official" /></div>
                    <div class="sm:col-span-2"><flux:input wire:model="form.proceedings" label="Proceedings" placeholder="ACM Digital Library" /></div>
                    <flux:input wire:model="form.decision_days" type="number" label="Usual days to decision" />
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="form.review_type" label="Review">
                    @foreach (Venue::REVIEW_TYPES as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach
                </flux:select>
                <flux:select wire:model="form.preprint_policy" label="Preprint policy">
                    @foreach (Venue::PREPRINT_POLICIES as $k => $l)<flux:select.option value="{{ $k }}">{{ $l }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="form.website_url" label="Website" placeholder="https://" />
                <flux:input wire:model="form.portal_url" label="Submission portal" placeholder="https://" />
            </div>
            <flux:textarea wire:model="form.notes" label="Notes" rows="2" />
            <flux:checkbox wire:model="form.watch" label="Watch: refresh this venue with AI every week" />

            <div class="flex items-center justify-between">
                <div>@if ($editingId)<flux:button variant="ghost" class="!text-rose-600" wire:click="delete" wire:confirm="Remove this venue? Your submissions to it stay.">Remove</flux:button>@endif</div>
                <div class="flex gap-2">
                    <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                    <flux:button type="submit" variant="primary">Save</flux:button>
                </div>
            </div>
        </form>
        @endif
    </flux:modal>
</div>
