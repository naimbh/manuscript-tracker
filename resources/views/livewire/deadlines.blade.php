<?php

use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Deadlines')] class extends Component {
    public string $title = '';
    public string $due_on = '';
    public string $paper_id = '';

    public function add(): void
    {
        $d = $this->validate(['title' => 'required|string|max:255', 'due_on' => 'required|date', 'paper_id' => 'nullable']);
        $d['paper_id'] = auth()->user()->papers()->find($d['paper_id'])?->id;
        auth()->user()->deadlines()->create($d);
        $this->reset();
        Flux::toast(variant: 'success', text: 'Deadline added.');
    }

    public function toggle(int $id): void
    {
        $d = auth()->user()->deadlines()->findOrFail($id);
        $d->update(['done' => ! $d->done]);
    }

    public function remove(int $id): void
    {
        auth()->user()->deadlines()->findOrFail($id)->delete();
    }

    public function with(): array
    {
        $all = auth()->user()->deadlines()->with('paper')->get();

        return [
            'upcoming' => $all->where('done', false)->sortBy('due_on')->values(),
            'done' => $all->where('done', true)->sortByDesc('due_on')->take(20)->values(),
            'papers' => auth()->user()->papers()->orderBy('title')->get(['id', 'title', 'short_title']),
            'feed' => route('calendar.feed', auth()->user()->calendarToken()),
        ];
    }
}; ?>

<div class="page-in mx-auto w-full max-w-6xl space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div><p class="eyebrow">Deadlines</p><h1 class="mt-1 text-2xl font-semibold tracking-tight">What is due, and when</h1></div>
        <flux:button size="sm" icon="calendar-days" :href="route('settings.data')" wire:navigate>Add to my calendar</flux:button>
    </div>

    <form wire:submit="add" class="panel grid gap-3 p-4 sm:grid-cols-[1fr_auto_auto_auto] sm:items-end">
        <flux:input wire:model="title" label="Deadline" placeholder="CHI late-breaking work" />
        <flux:input wire:model="due_on" type="date" label="Due" />
        <flux:select wire:model="paper_id" label="Paper">
            <flux:select.option value="">General</flux:select.option>
            @foreach ($papers as $p)<flux:select.option value="{{ $p->id }}">{{ $p->displayTitle() }}</flux:select.option>@endforeach
        </flux:select>
        <flux:button type="submit" variant="primary" icon="plus">Add</flux:button>
    </form>

    <div class="stagger space-y-2">
        @forelse ($upcoming as $d)
            @php $left = $d->daysLeft(); $pct = max(4, min(100, 100 - $left * 100 / 60)); @endphp
            <div class="panel lift group flex items-center gap-4 p-4" wire:key="d{{ $d->id }}">
                <button wire:click="toggle({{ $d->id }})" class="flex size-5 shrink-0 items-center justify-center rounded-full border-2 border-zinc-300 transition hover:border-emerald-500 dark:border-zinc-600"></button>
                <div class="flex w-12 shrink-0 flex-col items-center rounded-lg border border-zinc-200 py-1 leading-none dark:border-zinc-700">
                    <span class="text-[10px] font-semibold text-rose-500 uppercase">{{ $d->due_on->format('M') }}</span>
                    <span class="num text-lg font-semibold">{{ $d->due_on->format('j') }}</span>
                </div>
                <div class="min-w-0 flex-1">
                    <p class="truncate font-medium">{{ $d->title }}</p>
                    <p class="truncate text-xs text-zinc-500">{{ $d->paper?->displayTitle() ?? 'General' }} · {{ $d->due_on->format('l, j F Y') }}</p>
                    <div class="clock-track mt-2 !h-1"><div class="clock-fill grow-x {{ $left < 0 ? 'bg-rose-500' : ($left <= 7 ? 'bg-amber-500' : 'bg-indigo-500') }}" style="width: {{ $pct }}%"></div></div>
                </div>
                <span class="num w-16 text-right text-sm font-semibold {{ $left < 0 ? 'text-rose-500' : ($left <= 7 ? 'text-amber-600' : 'text-zinc-500') }}">{{ $left < 0 ? abs($left).'d late' : ($left === 0 ? 'Today' : $left.'d') }}</span>
                <button wire:click="remove({{ $d->id }})" class="text-zinc-300 opacity-0 transition group-hover:opacity-100 hover:text-rose-500"><flux:icon.trash variant="micro" /></button>
            </div>
        @empty
            <div class="panel"><x-ui.empty art="calendar" title="No deadlines" text="Add conference dates, revision due dates or anything else you need to remember." /></div>
        @endforelse
    </div>

    @if ($done->isNotEmpty())
        <div>
            <p class="eyebrow mb-2">Done</p>
            <div class="space-y-1">
                @foreach ($done as $d)
                    <div class="flex items-center gap-3 px-4 py-2 text-sm text-zinc-400" wire:key="dd{{ $d->id }}">
                        <button wire:click="toggle({{ $d->id }})" class="flex size-5 items-center justify-center rounded-full bg-emerald-500 text-white"><flux:icon.check variant="micro" class="size-3" /></button>
                        <span class="flex-1 truncate line-through">{{ $d->title }}</span><span class="num">{{ $d->due_on->format('j M') }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
