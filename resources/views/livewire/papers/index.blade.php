<?php

use App\Models\Paper;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new class extends Component {
    public ?Paper $paper = null;

    #[Url(except: '')]
    public string $q = '';

    #[Url(except: 'active')]
    public string $filter = 'active';

    public function mount(?Paper $paper = null): void
    {
        if ($paper?->exists) {
            abort_unless($paper->user_id === auth()->id(), 404);
            $this->paper = $paper;
        }
    }

    #[On('paper-updated')]
    public function refreshList(): void {}

    #[On('paper-deleted')]
    public function paperDeleted(): void
    {
        $this->redirectRoute('papers.index', navigate: true);
    }

    public function rendering($view): void
    {
        $view->title($this->paper?->displayTitle() ?? 'Papers');
    }

    public function with(): array
    {
        $all = auth()->user()->papers()->with(['currentSubmission.venue', 'queue.venue', 'submissions'])->latest('updated_at')->get();
        $groups = [
            'active' => fn ($p) => ! in_array($p->stage, ['published', 'shelved']),
            'review' => fn ($p) => in_array($p->stage, ['under_review', 'revision']),
            'done' => fn ($p) => in_array($p->stage, ['accepted', 'published']),
            'all' => fn ($p) => true,
        ];
        $list = $all->filter($groups[$this->filter] ?? $groups['active']);
        if (str_starts_with(trim($this->q), '#')) {
            $tag = mb_strtolower(ltrim(trim($this->q), '#'));
            $list = $list->filter(fn ($p) => collect($p->tags ?? [])->contains(fn ($t) => mb_strtolower($t) === $tag));
        } elseif ($q = trim($this->q)) {
            $needle = mb_strtolower($q);
            $list = $list->filter(fn ($p) => str_contains(mb_strtolower($p->title.' '.$p->short_title.' '.$p->keywords.' '.$p->method.' '.$p->currentSubmission?->venue_name), $needle));
        }

        return [
            'tags' => $all->flatMap(fn ($p) => $p->tags ?? [])->unique(fn ($t) => mb_strtolower($t))->sort()->values(),
            'list' => $list->values(),
            'counts' => collect($groups)->map(fn ($f) => $all->filter($f)->count()),
        ];
    }
}; ?>

<div class="-m-4 flex min-h-[calc(100dvh-3.5rem)] sm:-m-6 lg:-m-8 lg:min-h-dvh">
    {{-- List --}}
    <aside class="{{ $paper ? 'hidden lg:flex' : 'flex' }} w-full shrink-0 flex-col lg:sticky lg:top-0 lg:h-screen lg:self-start border-r border-zinc-200/70 bg-white/40 lg:w-96 xl:w-[26rem] dark:border-zinc-800 dark:bg-zinc-900/30">
        <div class="sticky top-0 z-10 space-y-3 border-b border-zinc-200/80 bg-[#f7f8fc]/90 p-4 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/80">
            <div class="flex items-center justify-between">
                <h1 class="text-lg font-semibold tracking-tight">Papers</h1>
                <flux:modal.trigger name="new-paper">
                    <flux:button size="sm" variant="ghost" icon="plus" aria-label="New paper" />
                </flux:modal.trigger>
            </div>
            <flux:input wire:model.live.debounce.150ms="q" data-search icon="magnifying-glass" placeholder="Search titles, venues, keywords" size="sm" clearable>
                <x-slot name="iconTrailing"><span class="kbd">/</span></x-slot>
            </flux:input>
            <div class="flex items-stretch gap-2"><div class="flex min-w-0 flex-1 gap-0.5 rounded-lg bg-zinc-100 p-0.5 text-[11px] font-medium sm:text-xs dark:bg-zinc-800">
                @foreach (['active' => 'Active', 'review' => 'In review', 'done' => 'Accepted', 'all' => 'All'] as $k => $label)
                    <button wire:click="$set('filter', '{{ $k }}')"
                            class="flex-1 rounded-md px-1 py-1.5 whitespace-nowrap transition sm:px-1.5 {{ $filter === $k ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">
                        {{ $label }} <span class="num opacity-60">{{ $counts[$k] }}</span>
                    </button>
                @endforeach
            </div>
                @if ($tags->isNotEmpty())
                    <flux:dropdown align="end">
                        <button class="flex h-full items-center gap-1 rounded-lg px-2.5 text-xs font-medium transition {{ str_starts_with($q, '#') ? 'bg-indigo-50 text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300' : 'text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800' }}">
                            <flux:icon.tag variant="micro" class="size-3.5" /><span class="max-[359px]:sr-only">{{ str_starts_with($q, '#') ? ltrim($q, '#') : 'Tags' }}</span>
                        </button>
                        <flux:menu>
                            @if (str_starts_with($q, '#'))<flux:menu.item icon="x-mark" wire:click="$set('q', '')">Clear tag</flux:menu.item><flux:menu.separator />@endif
                            @foreach ($tags as $t)<flux:menu.item wire:click="$set('q', @js('#'.$t))">{{ $t }}</flux:menu.item>@endforeach
                        </flux:menu>
                    </flux:dropdown>
                @endif
            </div>
        </div>

        <nav class="stagger flex-1 space-y-2 overflow-y-auto p-3" wire:loading.class="opacity-60" wire:target="q,filter">
            @forelse ($list as $p)
                @php
                    $cur = $p->currentSubmission; $clock = $cur?->clock(); $next = $p->queue->first();
                    $tone = $cur ? $cur->tone() : (in_array($p->stage, ['ready']) ? 'violet' : 'zinc');
                    $tint = [
                        'blue' => 'before:bg-sky-500 bg-sky-50/60 hover:bg-sky-50 dark:bg-sky-500/[.06] dark:hover:bg-sky-500/10',
                        'amber' => 'before:bg-amber-500 bg-amber-50/60 hover:bg-amber-50 dark:bg-amber-500/[.06] dark:hover:bg-amber-500/10',
                        'red' => 'before:bg-rose-500 bg-rose-50/50 hover:bg-rose-50 dark:bg-rose-500/[.06] dark:hover:bg-rose-500/10',
                        'green' => 'before:bg-emerald-500 bg-emerald-50/60 hover:bg-emerald-50 dark:bg-emerald-500/[.06] dark:hover:bg-emerald-500/10',
                        'violet' => 'before:bg-violet-500 bg-violet-50/60 hover:bg-violet-50 dark:bg-violet-500/[.06] dark:hover:bg-violet-500/10',
                        'zinc' => 'before:bg-zinc-300 bg-white/80 hover:bg-white dark:bg-zinc-900 dark:hover:bg-zinc-800/60',
                    ][$tone];
                    $trail = $p->submissions->sortBy(fn ($x) => [$x->submitted_on?->timestamp ?? 0, $x->id])->values();
                    $dot = ['blue' => 'bg-sky-500', 'amber' => 'bg-amber-500', 'red' => 'bg-rose-500', 'green' => 'bg-emerald-500', 'zinc' => 'bg-zinc-400'];
                @endphp
                <a href="{{ route('papers.index', $p) }}" wire:navigate wire:key="p{{ $p->id }}"
                   class="group relative block overflow-hidden rounded-xl py-3 pr-3 pl-4 transition before:absolute before:inset-y-0 before:left-0 before:w-1 {{ $tint }}
                          {{ $paper?->id === $p->id ? 'shadow-md ring-2 shadow-zinc-900/5 ring-indigo-500/70 dark:ring-indigo-400/60' : 'ring-1 ring-zinc-200/70 dark:ring-zinc-800' }}">
                    <div class="flex items-center justify-between gap-2">
                        <span class="truncate text-[10px] font-bold tracking-[.08em] text-zinc-500 uppercase dark:text-zinc-400">{{ $p->short_title ?: 'Paper' }}</span>
                        @if ($cur)<x-ui.decision :s="$cur" class="!py-0 !text-[11px]" />@else<x-ui.stage :stage="$p->stage" />@endif
                    </div>
                    <div class="mt-1 line-clamp-3 text-[13.5px] leading-snug font-semibold text-zinc-900 dark:text-zinc-50">{{ $p->title }}</div>
                    @if ($p->tags)<div class="mt-1.5 flex flex-wrap gap-1">@foreach ($p->tags as $t)<x-ui.tag :tag="$t" />@endforeach</div>@endif

                    @if ($trail->isNotEmpty())
                        <div class="mt-2 flex flex-wrap items-center gap-1">
                            @foreach ($trail as $i => $t)
                                @if ($i > 0)<flux:icon.chevron-right variant="micro" class="size-3 text-zinc-300 dark:text-zinc-600" />@endif
                                <span class="inline-flex max-w-[15rem] items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] {{ $t->id === $cur?->id ? 'bg-white font-semibold text-zinc-800 shadow-xs ring-1 ring-zinc-200 dark:bg-zinc-800 dark:text-zinc-100 dark:ring-zinc-700' : 'text-zinc-500 line-through decoration-zinc-300 dark:decoration-zinc-600' }}" title="{{ $t->venue_name }} · {{ \App\Models\Submission::DECISIONS[$t->decision] }}">
                                    <span class="size-1.5 shrink-0 rounded-full {{ $dot[$t->tone()] }}"></span>
                                    <span class="truncate">{{ str($t->venue_name)->before(' (')->limit(34) }}</span>
                                </span>
                            @endforeach
                        </div>
                    @elseif ($next)
                        <div class="mt-2 flex items-center gap-1.5 text-[11px] text-zinc-500"><flux:icon.arrow-right variant="micro" class="size-3 text-violet-500" />Next: <span class="truncate font-medium text-zinc-700 dark:text-zinc-300">{{ $next->venue->name }}</span></div>
                    @endif

                    @if ($cur)
                        <div class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-zinc-500">
                            @if ($cur->manuscript_id)<span class="font-mono text-zinc-600 dark:text-zinc-300">{{ $cur->manuscript_id }}</span>@endif
                            @if ($cur->submitted_on)<span>Sent {{ $cur->submitted_on->format('j M') }}</span>@endif
                            @if ($cur->status_on)<span>· {{ \App\Models\Submission::DECISIONS[$cur->decision] }} {{ $cur->status_on->format('j M') }}</span>@endif
                            @if ($cur->decision === 'pending' && $cur->daysInReview() !== null)<span class="num ml-auto font-semibold {{ $clock && $clock['overdue'] ? 'text-rose-500' : 'text-zinc-600 dark:text-zinc-300' }}">{{ $cur->daysInReview() }}d</span>@endif
                        </div>
                    @endif
                    @if ($clock)
                        <div class="clock-track mt-2 !h-1 !bg-white/70 dark:!bg-zinc-800"><div class="clock-fill grow-x {{ $clock['overdue'] ? 'bg-rose-500' : 'bg-indigo-500' }}" style="width: {{ $clock['pct'] }}%"></div></div>
                    @endif
                </a>
            @empty
                <div class="px-4 py-10 text-center text-sm text-zinc-500">
                    {{ $q ? 'No papers match “'.$q.'”.' : 'No papers here yet.' }}
                </div>
            @endforelse
        </nav>
    </aside>

    {{-- Record --}}
    <section class="{{ $paper ? 'flex' : 'hidden lg:flex' }} min-w-0 flex-1 flex-col">
        @if ($paper)
            <livewire:papers.detail :paper="$paper" :key="'detail-'.$paper->id" />
        @else
            <div class="flex flex-1 items-center justify-center">
                <x-ui.empty title="Pick a paper" text="Choose a paper on the left to see its submissions, files and next steps. Press N to add a new one.">
                    <flux:modal.trigger name="new-paper"><flux:button icon="plus">New paper</flux:button></flux:modal.trigger>
                </x-ui.empty>
            </div>
        @endif
    </section>
</div>
