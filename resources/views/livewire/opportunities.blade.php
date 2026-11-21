<?php

use App\Models\Opportunity;
use App\Support\OpportunityScanner;
use App\Support\VenueResearcher;
use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Title('Opportunities')] class extends Component {
    #[Url(except: 'open')]
    public string $show = 'open';

    public string $interests = '';

    public function mount(): void
    {
        $this->interests = OpportunityScanner::interestsFor(auth()->user());
    }

    public function saveInterests(): void
    {
        $this->validate(['interests' => 'required|string|max:1000']);
        auth()->user()->forceFill(['interests' => $this->interests])->save();
        Flux::modal('interests')->close();
        Flux::toast(variant: 'success', text: 'Interests saved. The next scan will use them.');
    }

    public function scanNow(): void
    {
        $user = auth()->user();
        if ($user->scans()->where('status', 'running')->where('created_at', '>', now()->subMinutes(15))->exists()) {
            return;
        }
        $scan = $user->scans()->create(['trigger' => 'manual']);
        $cmd = sprintf('cd %s && nohup %s artisan opportunities:scan --user=%d --scan=%d > /dev/null 2>&1 &',
            escapeshellarg(base_path()), escapeshellarg(config('services.ai.php_bin')), $user->id, $scan->id);
        exec($cmd);
        $this->dispatch('ai-runs-changed');
        Flux::toast(text: 'Scanning. You can keep working; you will be notified when it finishes.');
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, ['new', 'saved', 'dismissed'], true), 422);
        auth()->user()->opportunities()->findOrFail($id)->update(['status' => $status]);
    }

    public function addDeadline(int $id): void
    {
        $o = auth()->user()->opportunities()->findOrFail($id);
        if ($o->deadline_on) {
            auth()->user()->deadlines()->firstOrCreate(['title' => Str::limit($o->title, 200), 'due_on' => $o->deadline_on]);
            $o->update(['status' => 'saved']);
            Flux::toast(variant: 'success', text: 'Added to your deadlines.');
        }
    }

    public function with(): array
    {
        $user = auth()->user();
        $q = $user->opportunities()->orderByDesc('created_at')->orderByDesc('fit');
        $q = match ($this->show) {
            'saved' => $q->where('status', 'saved'),
            'dismissed' => $q->where('status', 'dismissed'),
            default => $q->where('status', '!=', 'dismissed')->where(fn ($w) => $w->whereNull('deadline_on')->orWhere('deadline_on', '>=', today())),
        };
        $weeks = $q->limit(200)->get()->groupBy(fn ($o) => $o->created_at->startOfWeek()->toDateString());
        $last = $user->scans()->first();

        return [
            'weeks' => $weeks,
            'last' => $last,
            'running' => $last && $last->status === 'running' && $last->created_at->gt(now()->subMinutes(15)),
            'aiReady' => VenueResearcher::configured(),
            'aiStatus' => VenueResearcher::status(),
            'scans' => $this->show === 'history' ? $user->scans()->latest()->limit(60)->get() : collect(),
            'runs' => $this->show === 'history' ? $user->aiRuns()->latest()->limit(60)->get() : collect(),
            'newThisWeek' => $user->opportunities()->where('status', 'new')->where('created_at', '>=', now()->startOfWeek())->count(),
        ];
    }
}; ?>

<div class="page-in mx-auto w-full max-w-6xl space-y-6" @if ($running) wire:poll.6s @endif>
    <section class="hero relative overflow-hidden rounded-2xl border border-zinc-200/80 px-6 py-6 sm:px-8 dark:border-zinc-800">
        <div class="hero-grid pointer-events-none absolute inset-0 text-zinc-900 dark:text-white"></div>
        <div class="relative flex flex-wrap items-end justify-between gap-4">
            <div class="max-w-xl">
                <p class="eyebrow">Opportunities</p>
                <h1 class="mt-1 text-2xl font-semibold tracking-tight">Calls that fit your work</h1>
                <p class="mt-1 text-sm text-zinc-600 dark:text-zinc-400">Every day, the AI reads new calls from WikiCFP and the publishers’ call pages, keeps only those with proceedings or a journal behind them, and ranks them against your interests.</p>
                <button class="mt-3 inline-flex max-w-full items-center gap-1.5 truncate rounded-full bg-white/70 px-3 py-1 text-xs text-zinc-600 ring-1 ring-zinc-200 hover:ring-indigo-300 dark:bg-zinc-900/60 dark:text-zinc-300 dark:ring-zinc-700" x-on:click="$flux.modal('interests').show()">
                    <flux:icon.adjustments-horizontal variant="micro" class="size-3.5 shrink-0" /> <span class="truncate">{{ $interests }}</span>
                </button>
            </div>
            <div class="flex flex-col items-end gap-2">
                <flux:button variant="primary" icon="{{ $running ? 'arrow-path' : 'magnifying-glass-circle' }}" wire:click="scanNow" :disabled="$running || ! $aiReady" class="{{ $running ? '[&_svg]:animate-spin' : '' }}">
                    {{ $running ? 'Scanning…' : 'Scan now' }}
                </flux:button>
                <span class="text-[11px] text-zinc-500">
                    @if (! $aiReady) {{ $aiStatus }}
                    @elseif ($last) Last scan {{ $last->created_at->diffForHumans() }} · {{ $last->status === 'failed' ? 'failed' : ($last->status === 'running' ? 'in progress' : $last->found.' new') }}
                    @else Daily at {{ config('tracker.scan_time') }}
                    @endif
                </span>
            </div>
        </div>
        @if ($running)
            <div class="relative mt-5 overflow-hidden rounded-full"><div class="clock-track !h-1.5"><div class="h-full w-1/3 animate-[scan_1.6s_ease-in-out_infinite] rounded-full bg-gradient-to-r from-indigo-500 to-fuchsia-500"></div></div></div>
        @endif
    </section>

    @if ($last?->status === 'failed')
        <flux:callout variant="warning" icon="exclamation-triangle" heading="The last scan did not finish">
            <flux:callout.text>{{ $last->error }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="flex items-center gap-1 rounded-lg bg-zinc-100 p-0.5 text-sm font-medium w-fit dark:bg-zinc-800">
        @foreach (['open' => 'Open calls', 'saved' => 'Saved', 'dismissed' => 'Dismissed', 'history' => 'History'] as $k => $l)
            <button wire:click="$set('show', '{{ $k }}')" class="rounded-md px-3 py-1.5 transition {{ $show === $k ? 'bg-white text-zinc-900 shadow-xs dark:bg-zinc-700 dark:text-white' : 'text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' }}">{{ $l }}</button>
        @endforeach
    </div>

    @if ($show === 'history')
        @php $badge = fn ($st) => ['done' => 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300', 'failed' => 'bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300', 'running' => 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'][$st] ?? 'bg-zinc-100 text-zinc-600'; @endphp
        <section class="space-y-3">
            <h2 class="font-semibold">Scans</h2>
            <div class="panel divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($scans as $sc)
                    <div x-data="{ open: false }" wire:key="sc{{ $sc->id }}" class="px-4 py-3">
                        <button x-on:click="open = ! open" class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 text-left text-sm">
                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $badge($sc->status) }}">{{ ucfirst($sc->status) }}</span>
                            <span class="font-medium">{{ $sc->created_at->format('j M Y, H:i') }}</span>
                            <span class="text-xs text-zinc-500">{{ $sc->trigger === 'daily' ? 'Daily' : 'Manual' }}</span>
                            <span class="text-xs text-zinc-500">{{ $sc->candidates }} candidates · {{ $sc->found }} new</span>
                            @if ($sc->finished_at)<span class="num text-xs text-zinc-400">{{ $sc->created_at->diffInSeconds($sc->finished_at) < 90 ? (int) $sc->created_at->diffInSeconds($sc->finished_at).'s' : round($sc->created_at->diffInMinutes($sc->finished_at), 1).' min' }}</span>@endif
                            @if ($sc->provider)<span class="text-xs text-zinc-400">· {{ $sc->provider }}</span>@endif
                            <flux:icon.chevron-down variant="micro" class="ml-auto size-4 text-zinc-400 transition" ::class="open && 'rotate-180'" />
                        </button>
                        @if ($sc->error)<p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ $sc->error }}</p>@endif
                        <pre x-show="open" x-collapse x-cloak class="mt-2 max-h-64 overflow-auto rounded-lg bg-zinc-50 p-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">{{ $sc->log ?: 'No log recorded for this scan.' }}</pre>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-zinc-500">No scans yet.</p>
                @endforelse
            </div>

            <h2 class="pt-4 font-semibold">All AI jobs</h2>
            <div class="panel divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($runs as $r)
                    <div x-data="{ open: false }" wire:key="rn{{ $r->id }}" class="px-4 py-3">
                        <button x-on:click="open = ! open" class="flex w-full flex-wrap items-center gap-x-3 gap-y-1 text-left text-sm">
                            <span class="rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $badge($r->status) }}">{{ ucfirst($r->status) }}</span>
                            <span class="font-medium">{{ $r->label }}</span>
                            <span class="text-xs text-zinc-500">{{ $r->created_at->format('j M Y, H:i') }}</span>
                            @if ($r->total > 1)<span class="num text-xs text-zinc-500">{{ $r->done }}/{{ $r->total }}</span>@endif
                            @if ($r->provider)<span class="text-xs text-zinc-400">· {{ $r->provider }}</span>@endif
                            <flux:icon.chevron-down variant="micro" class="ml-auto size-4 text-zinc-400 transition" ::class="open && 'rotate-180'" />
                        </button>
                        @if ($r->error)<p class="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-500/10 dark:text-rose-300">{{ $r->error }}</p>@endif
                        <pre x-show="open" x-collapse x-cloak class="mt-2 max-h-64 overflow-auto rounded-lg bg-zinc-50 p-3 font-mono text-[11px] leading-relaxed whitespace-pre-wrap text-zinc-600 dark:bg-zinc-900 dark:text-zinc-400">{{ $r->log ?: 'No log recorded.' }}</pre>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-sm text-zinc-500">No AI jobs yet.</p>
                @endforelse
            </div>
        </section>
    @else
    @forelse ($weeks as $week => $items)
        <section wire:key="w{{ $week }}">
            <div class="mb-3 flex items-baseline gap-3">
                <h2 class="font-semibold">{{ \Illuminate\Support\Carbon::parse($week)->isCurrentWeek() ? 'This week' : 'Week of '.\Illuminate\Support\Carbon::parse($week)->format('j M') }}</h2>
                <span class="num text-xs text-zinc-400">{{ $items->count() }} {{ str('call')->plural($items->count()) }}</span>
            </div>
            <div class="stagger grid gap-3">
                @foreach ($items as $o)
                    @php $days = $o->deadline_on ? (int) now()->startOfDay()->diffInDays($o->deadline_on, false) : null; @endphp
                    <article wire:key="o{{ $o->id }}" class="panel lift flex gap-4 p-4">
                        <div class="flex w-14 shrink-0 flex-col items-center gap-1.5 pt-0.5">
                            <div class="flex gap-0.5" aria-label="Fit {{ $o->fit }} of 5">
                                @for ($i = 1; $i <= 5; $i++)<span class="h-4 w-1.5 rounded-full {{ $i <= $o->fit ? 'bg-gradient-to-t from-indigo-500 to-fuchsia-500' : 'bg-zinc-200 dark:bg-zinc-700' }}"></span>@endfor
                            </div>
                            <span class="text-[10px] font-medium text-zinc-400 uppercase">fit</span>
                            @if ($days !== null)
                                <span class="num mt-1 rounded-md px-1.5 py-0.5 text-[11px] font-semibold {{ $days <= 14 ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10' : 'bg-zinc-100 text-zinc-600 dark:bg-zinc-800 dark:text-zinc-300' }}">{{ $days }}d</span>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-md bg-indigo-50 px-1.5 py-0.5 text-[11px] font-medium text-indigo-700 dark:bg-indigo-500/10 dark:text-indigo-300">{{ Opportunity::KINDS[$o->kind] ?? $o->kind }}</span>
                                @if ($o->status === 'new' && $o->created_at->gt(now()->subDays(2)))<span class="rounded-md bg-emerald-500 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase">new</span>@endif
                                @if ($o->outlet)<span class="text-[11px] text-zinc-500">{{ $o->outlet }}</span>@endif
                            </div>
                            <h3 class="mt-1.5 font-semibold leading-snug"><a href="{{ $o->url }}" target="_blank" class="hover:text-indigo-600">{{ $o->title }}</a></h3>
                            <p class="text-xs text-zinc-500">{{ collect([$o->venue, $o->publisher, $o->event])->filter()->implode(' · ') }}</p>
                            @if ($o->summary)<p class="mt-2 text-sm text-zinc-600 dark:text-zinc-300">{{ $o->summary }}</p>@endif
                            @if ($o->why)<p class="mt-1.5 flex gap-1.5 text-sm text-indigo-700 dark:text-indigo-300"><flux:icon.sparkles variant="micro" class="mt-0.5 size-3.5 shrink-0" />{{ $o->why }}</p>@endif
                            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
                                @if ($o->deadline_on)<span class="text-zinc-500">Deadline {{ $o->deadline_on->format('j M Y') }}</span>@endif
                                <span class="flex-1"></span>
                                <flux:button size="xs" variant="ghost" icon="arrow-up-right" :href="$o->url" target="_blank">Call</flux:button>
                                @if ($o->deadline_on)<flux:button size="xs" variant="ghost" icon="calendar-days" wire:click="addDeadline({{ $o->id }})">Add deadline</flux:button>@endif
                                @if ($o->status !== 'saved')<flux:button size="xs" variant="ghost" icon="bookmark" wire:click="setStatus({{ $o->id }}, 'saved')">Save</flux:button>@endif
                                @if ($o->status !== 'dismissed')<flux:button size="xs" variant="ghost" icon="x-mark" wire:click="setStatus({{ $o->id }}, 'dismissed')">Dismiss</flux:button>
                                @else<flux:button size="xs" variant="ghost" icon="arrow-uturn-left" wire:click="setStatus({{ $o->id }}, 'new')">Restore</flux:button>@endif
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @empty
        <div class="panel">
            <x-ui.empty title="{{ $show === 'open' ? 'No open calls yet' : 'Nothing here' }}" text="{{ $show === 'open' ? 'Run a scan now, or wait for the daily scan at '.config('tracker.scan_time').'.' : '' }}">
                @if ($show === 'open' && $aiReady)<flux:button variant="primary" wire:click="scanNow">Scan now</flux:button>@endif
            </x-ui.empty>
        </div>
    @endforelse
    @endif

    <flux:modal name="interests" class="w-full max-w-lg">
        <form wire:submit="saveInterests" class="space-y-4">
            <flux:heading size="lg">Your interests</flux:heading>
            <flux:text>Separate topics with commas. The first six are also used to search WikiCFP.</flux:text>
            <flux:textarea wire:model="interests" rows="4" placeholder="platform labor, gig economy, ICT4D, generative AI and work, CSCW" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">Save</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
