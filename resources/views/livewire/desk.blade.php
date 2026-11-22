<?php

use App\Models\Paper;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Desk')] class extends Component {
    public function submitNext(int $paperId): void
    {
        $paper = auth()->user()->papers()->findOrFail($paperId);
        if ($sub = $paper->submitToNext()) {
            Flux::toast(variant: 'success', heading: 'Submitted', text: "{$paper->displayTitle()} is now with {$sub->venue_name}.");
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $papers = $user->papers()->with(['submissions.venue', 'currentSubmission.venue', 'queue.venue'])->get();
        $subs = $papers->flatMap->submissions;

        $attention = collect();
        foreach ($papers as $p) {
            if ($s = $p->anonymityRisk()) {
                $attention->push(['tone' => 'red', 'icon' => 'eye', 'paper' => $p,
                    'text' => "Public preprint while under double-anonymous review at {$s->venue_name}."]);
            }
            $cur = $p->currentSubmission;
            if ($cur && ($c = $cur->clock()) && $c['overdue']) {
                $attention->push(['tone' => 'amber', 'icon' => 'clock', 'paper' => $p,
                    'text' => "{$cur->venue_name} is ".($c['days'] - $c['typical'])." days past its usual decision time. Consider a status query."]);
            }
            if ($cur && $cur->revision_due_on && in_array($cur->decision, ['major', 'minor']) && $cur->revision_due_on->diffInDays(now()) <= 14) {
                $attention->push(['tone' => 'amber', 'icon' => 'pencil-square', 'paper' => $p,
                    'text' => 'Revision due '.$cur->revision_due_on->diffForHumans().'.']);
            }
            if ($p->stage === 'ready') {
                $next = $p->queue->first();
                $attention->prepend(['tone' => 'violet', 'icon' => 'paper-airplane', 'paper' => $p,
                    'text' => $next ? "Ready to submit. Next venue: {$next->venue->name}." : 'Ready to submit. Add a venue to its next-venue queue.',
                    'next' => (bool) $next]);
            } elseif ($p->needsNextVenue()) {
                $attention->push(['tone' => 'indigo', 'icon' => 'arrow-right', 'paper' => $p,
                    'text' => $p->queue->first() ? "Ready for its next venue: {$p->queue->first()->venue->name}." : 'Rejected last time, and the next-venue queue is empty.',
                    'next' => (bool) $p->queue->first()]);
            }
        }

        // Journey graphic: last 12 months.
        $start = now()->subMonths(11)->startOfMonth();
        $end = now()->endOfMonth();
        $span = $start->diffInDays($end);
        $lanes = $papers->filter(fn ($p) => $p->submissions->contains(fn ($s) => $s->submitted_on && ($s->decision_on ?? now())->gte($start)))
            ->map(fn ($p) => [
                'paper' => $p,
                'bars' => $p->submissions->filter(fn ($s) => $s->submitted_on)->map(function ($s) use ($start, $span) {
                    $from = max(0, $start->diffInDays($s->submitted_on, false));
                    $to = max($from + 3, $start->diffInDays($s->decision === 'pending' ? now() : ($s->decision_on ?? now()), false));
                    return ['x' => $from / $span * 100, 'w' => max(1.2, ($to - $from) / $span * 100), 's' => $s];
                })->filter(fn ($b) => $b['x'] + $b['w'] > 0)->values(),
            ])->values();

        return [
            'stats' => [
                ['In review', $subs->where('decision', 'pending')->count(), 'clock', 'from-sky-500 to-indigo-500'],
                ['Revising', $papers->where('stage', 'revision')->count(), 'pencil-square', 'from-amber-400 to-orange-500'],
                ['Accepted', $papers->whereIn('stage', ['accepted', 'published'])->count(), 'check-badge', 'from-emerald-400 to-teal-500'],
                ['Sent this year', $subs->filter(fn ($s) => $s->submitted_on?->isCurrentYear())->count(), 'paper-airplane', 'from-fuchsia-500 to-violet-500'],
            ],
            'attention' => $attention,
            'clocks' => $subs->where('decision', 'pending')->sortBy('submitted_on')->values(),
            'deadlines' => $user->deadlines()->with('paper')->where('done', false)->where('due_on', '>=', today()->subDays(7))->limit(6)->get(),
            'lanes' => $lanes,
            'months' => collect(range(0, 11))->map(fn ($i) => $start->copy()->addMonths($i)),
            'todayX' => $start->diffInDays(now()) / $span * 100,
            'empty' => $papers->isEmpty(),
        ];
    }
}; ?>

<div class="page-in mx-auto w-full max-w-6xl space-y-6">
    {{-- Hero --}}
    <section class="hero relative overflow-hidden rounded-2xl border border-zinc-200/80 px-4 py-5 sm:px-8 sm:py-7 dark:border-zinc-800">
        <div class="hero-grid pointer-events-none absolute inset-0 text-zinc-900 dark:text-white"></div>
        <div class="relative">
            <p class="eyebrow">{{ now()->format('l, j F Y') }}</p>
            <h1 class="mt-1 text-xl font-semibold tracking-tight sm:text-3xl">
                Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}, {{ str(auth()->user()->name)->before(' ') }}.
            </h1>
            <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">
                @if ($attention->isEmpty()) Nothing needs you today. Your papers are where they should be.
                @else {{ $attention->count() }} {{ str('thing')->plural($attention->count()) }} could use your attention.
                @endif
            </p>
            <div class="stagger mt-5 grid grid-cols-2 gap-2 sm:mt-6 sm:gap-3 lg:grid-cols-4">
                @foreach ($stats as [$label, $value, $icon, $grad])
                    <div class="lift rounded-xl border border-white/60 bg-white/70 p-3 backdrop-blur sm:p-4 dark:border-white/5 dark:bg-zinc-900/60">
                        <div class="flex items-center justify-between">
                            <span class="text-xs font-medium text-zinc-500">{{ $label }}</span>
                            <span class="flex size-7 items-center justify-center rounded-lg bg-gradient-to-br {{ $grad }} text-white shadow-sm">
                                <flux:icon :name="$icon" variant="micro" />
                            </span>
                        </div>
                        <x-ui.count :value="$value" class="mt-1 block text-2xl font-semibold tracking-tight sm:mt-2 sm:text-3xl" />
                    </div>
                @endforeach
            </div>
        </div>
    </section>

    @if ($empty)
        <div class="panel">
            <x-ui.empty title="Your desk is clear" text="Add your first paper and its submissions. The desk will then show review clocks, deadlines and what to do next.">
                <flux:modal.trigger name="new-paper"><flux:button variant="primary" icon="plus">Add a paper</flux:button></flux:modal.trigger>
            </x-ui.empty>
        </div>
    @else
        <div class="grid grid-cols-[minmax(0,1fr)] gap-6 lg:grid-cols-5">
            {{-- Attention --}}
            <section class="panel p-4 sm:p-5 lg:col-span-3">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Needs attention</h2>
                    <span class="num text-xs text-zinc-400">{{ $attention->count() }}</span>
                </div>
                @forelse ($attention as $a)
                    @php $tones = ['red' => 'bg-rose-500/10 text-rose-600 dark:text-rose-400', 'amber' => 'bg-amber-500/10 text-amber-600 dark:text-amber-400', 'indigo' => 'bg-indigo-500/10 text-indigo-600 dark:text-indigo-400', 'violet' => 'bg-gradient-to-br from-violet-600 to-fuchsia-600 text-white shadow-sm shadow-violet-500/30']; @endphp
                    <div class="stagger">
                        <div class="flex items-start gap-3 border-t border-zinc-100 py-3 first:border-0 dark:border-zinc-800 {{ $a['tone'] === 'violet' ? '-mx-2 rounded-xl border-transparent bg-violet-50/70 px-2 ring-1 ring-violet-200 dark:bg-violet-500/10 dark:ring-violet-500/30' : '' }}">
                            <span class="mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-lg {{ $tones[$a['tone']] }}">
                                <flux:icon :name="$a['icon']" variant="mini" />
                            </span>
                            <div class="min-w-0 flex-1">
                                <a href="{{ route('papers.index', $a['paper']) }}" wire:navigate class="text-sm font-medium hover:text-indigo-600">{{ $a['paper']->displayTitle() }}</a>
                                <p class="text-sm break-words text-zinc-500">{{ $a['text'] }}</p>
                            </div>
                            @if (! empty($a['next']))
                                <flux:button size="sm" variant="primary" wire:click="submitNext({{ $a['paper']->id }})" icon-trailing="arrow-right">Submit</flux:button>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="flex items-center gap-3 rounded-lg bg-emerald-500/5 p-4 text-sm text-emerald-700 dark:text-emerald-400">
                        <flux:icon.check-circle variant="mini" /> All clear. No overdue reviews, risky preprints or stalled papers.
                    </div>
                @endforelse
            </section>

            {{-- Deadlines --}}
            <section class="panel p-4 sm:p-5 lg:col-span-2">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="font-semibold">Coming up</h2>
                    <a href="{{ route('deadlines') }}" wire:navigate class="text-xs text-zinc-500 hover:text-indigo-600">All deadlines</a>
                </div>
                <div class="stagger space-y-1">
                    @forelse ($deadlines as $d)
                        @php $left = $d->daysLeft(); @endphp
                        <div class="flex items-center gap-3 rounded-lg px-2 py-2 hover:bg-zinc-50 dark:hover:bg-zinc-800/50">
                            <div class="flex w-11 shrink-0 flex-col items-center rounded-lg border border-zinc-200 py-1 leading-none dark:border-zinc-700">
                                <span class="text-[10px] font-semibold uppercase text-rose-500">{{ $d->due_on->format('M') }}</span>
                                <span class="num text-base font-semibold">{{ $d->due_on->format('j') }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium">{{ $d->title }}</div>
                                <div class="truncate text-xs text-zinc-500">{{ $d->paper?->displayTitle() ?? 'General' }}</div>
                            </div>
                            <span class="num text-xs font-medium {{ $left < 0 ? 'text-rose-500' : ($left <= 7 ? 'text-amber-600' : 'text-zinc-400') }}">
                                {{ $left < 0 ? abs($left).'d late' : ($left === 0 ? 'today' : $left.'d') }}
                            </span>
                        </div>
                    @empty
                        <x-ui.empty art="calendar" title="No deadlines" text="Add conference dates or revision due dates on the Deadlines page." class="!py-6" />
                    @endforelse
                </div>
            </section>
        </div>

        {{-- Review clocks --}}
        @if ($clocks->isNotEmpty())
            <section>
                <div class="mb-3 flex flex-col gap-0.5 sm:flex-row sm:items-baseline sm:justify-between">
                    <h2 class="font-semibold">Review clocks</h2>
                    <span class="text-xs text-zinc-400">Days in review against each venue’s usual decision time</span>
                </div>
                <div class="stagger grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($clocks as $s)
                        @php $c = $s->clock(); $days = $s->daysInReview(); @endphp
                        <a href="{{ route('papers.index', $s->paper_id) }}" wire:navigate class="panel lift flex items-center gap-4 p-4">
                            @if ($c)
                                <x-ui.ring :pct="$c['pct']" :tone="$c['overdue'] ? 'red' : ($c['pct'] > 75 ? 'amber' : 'indigo')" :label="$days" sub="days" />
                            @else
                                <x-ui.ring :pct="0" tone="zinc" :label="$days ?? '–'" sub="days" />
                            @endif
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold">{{ $s->paper->displayTitle() }}</div>
                                <div class="truncate text-sm text-zinc-500">{{ $s->venue_name }}</div>
                                <div class="mt-1 text-xs {{ $c && $c['overdue'] ? 'text-rose-500' : 'text-zinc-400' }}">
                                    @if ($c) {{ $c['overdue'] ? 'Past the usual '.$c['typical'].' days' : 'Decision expected around '.$c['expected_on']->format('j M') }}
                                    @else Add the venue’s decision time to start the clock
                                    @endif
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </section>
        @endif

        {{-- Journey map --}}
        @if ($lanes->isNotEmpty())
            <section class="panel overflow-hidden p-4 sm:p-5">
                <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-baseline sm:justify-between">
                    <h2 class="font-semibold">The last twelve months</h2>
                    <div class="flex flex-wrap gap-3 text-xs text-zinc-500">
                        <span class="flex items-center gap-1.5"><i class="size-2 rounded-full bg-sky-500"></i>In review</span>
                        <span class="flex items-center gap-1.5"><i class="size-2 rounded-full bg-amber-500"></i>Revision</span>
                        <span class="flex items-center gap-1.5"><i class="size-2 rounded-full bg-rose-500"></i>Rejected</span>
                        <span class="flex items-center gap-1.5"><i class="size-2 rounded-full bg-emerald-500"></i>Accepted</span>
                    </div>
                </div>
                <div class="relative">
                    <div class="ml-24 grid grid-cols-12 border-b sm:ml-36 max-sm:[&>span:nth-child(even)]:invisible border-zinc-100 pb-1 text-[10px] uppercase tracking-wide text-zinc-400 dark:border-zinc-800">
                        @foreach ($months as $m)<span>{{ $m->format('M') }}</span>@endforeach
                    </div>
                    <div class="space-y-2 pt-3">
                        @foreach ($lanes as $i => $lane)
                            <div class="flex items-center">
                                <a href="{{ route('papers.index', $lane['paper']) }}" wire:navigate class="w-24 shrink-0 truncate pr-2 text-[11px] font-medium hover:text-indigo-600 sm:w-36 sm:pr-3 sm:text-xs">{{ $lane['paper']->displayTitle() }}</a>
                                <div class="relative h-6 flex-1 rounded-md bg-zinc-50 dark:bg-zinc-800/40">
                                    @foreach ($lane['bars'] as $b)
                                        @php $col = ['blue' => 'bg-gradient-to-r from-sky-400 to-sky-500', 'amber' => 'bg-gradient-to-r from-amber-400 to-amber-500', 'red' => 'bg-gradient-to-r from-rose-400 to-rose-500', 'green' => 'bg-gradient-to-r from-emerald-400 to-emerald-500', 'zinc' => 'bg-zinc-300 dark:bg-zinc-600'][$b['s']->tone()]; @endphp
                                        <div class="grow-x group absolute top-1 h-4 rounded {{ $col }} shadow-sm" style="left: {{ $b['x'] }}%; width: {{ min($b['w'], 100 - $b['x']) }}%; animation-delay: {{ 0.1 + $i * 0.08 }}s">
                                            <span class="pointer-events-none absolute bottom-full left-0 mb-1 hidden whitespace-nowrap rounded-md bg-zinc-900 px-2 py-1 text-[11px] text-white shadow-lg group-hover:block dark:bg-white dark:text-zinc-900">
                                                {{ $b['s']->venue_name }} · {{ \App\Models\Submission::DECISIONS[$b['s']->decision] }} · {{ $b['s']->daysInReview() }} days
                                            </span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="pointer-events-none absolute top-0 bottom-0 ml-24 sm:ml-36" style="left: 0; right: 0">
                        <div class="absolute top-0 bottom-0 w-px bg-indigo-500/70" style="left: {{ $todayX }}%">
                            <span class="absolute -top-0.5 -translate-x-full rounded bg-indigo-600 px-1 text-[9px] font-semibold text-white">today</span>
                        </div>
                    </div>
                </div>
            </section>
        @endif
    @endif
</div>
