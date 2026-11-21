<?php

use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component {
    /** Newest notification already announced, so each one pops up only once. */
    public ?string $seen = null;

    public function mount(): void
    {
        $this->seen = auth()->user()->unreadNotifications()->latest()->value('id');
    }

    #[On('notifications-changed')]
    #[On('ai-runs-changed')]
    public function check(): void
    {
        // Jobs that died without finishing (e.g. the machine slept) are closed after 20 minutes.
        auth()->user()->aiRuns()->where('status', 'running')->where('updated_at', '<', now()->subMinutes(20))
            ->update(['status' => 'failed', 'error' => 'Stopped responding.', 'finished_at' => now()]);
        $fresh = auth()->user()->unreadNotifications()->latest()->limit(5)->get();
        $new = $this->seen ? $fresh->takeWhile(fn ($n) => $n->id !== $this->seen) : $fresh->filter(fn ($n) => $n->created_at->gt(now()->subMinutes(2)));
        foreach ($new->reverse() as $n) {
            $this->dispatch('ai-job-finished', title: $n->data['title'], body: $n->data['body'], status: $n->data['status'] ?? 'success', url: $n->data['url'] ?? null);
        }
        if ($fresh->isNotEmpty()) {
            $this->seen = $fresh->first()->id;
        }
    }

    public function openItem(string $id): void
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();
        if ($url = $n->data['url'] ?? null) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        auth()->user()->unreadNotifications->markAsRead();
    }

    public function with(): array
    {
        return [
            'items' => auth()->user()->notifications()->latest()->limit(12)->get(),
            'unread' => auth()->user()->unreadNotifications()->count(),
            'running' => auth()->user()->aiRuns()->where('status', 'running')->latest()->get(),
        ];
    }
}; ?>

<div @if ($running->isNotEmpty()) wire:poll.3s="check" @else wire:poll.20s="check" @endif
     x-data="{
        open: false,
        perm: ('Notification' in window) ? Notification.permission : 'unsupported',
        ask() { if (this.perm === 'default') Notification.requestPermission().then(p => this.perm = p); },
     }"
     x-on:ai-job-finished.window="
        const d = $event.detail;
        $flux.toast({ heading: d.title, text: d.body, variant: d.status === 'danger' ? 'danger' : (d.status === 'warning' ? 'warning' : 'success'), duration: 8000 });
        if (perm === 'granted' && document.visibilityState !== 'visible') {
            const n = new Notification(d.title, { body: d.body, icon: '/icon-512.png', tag: 'mt-' + d.title });
            n.onclick = () => { window.focus(); if (d.url) Livewire.navigate(d.url); n.close(); };
        }
     ">
    @if ($running->isNotEmpty())
        <div class="mb-2 space-y-2 rounded-xl border border-indigo-200/70 bg-gradient-to-br from-indigo-50 to-fuchsia-50/60 p-3 dark:border-indigo-500/20 dark:from-indigo-500/10 dark:to-fuchsia-500/5" wire:transition>
            <div class="flex items-center gap-1.5 text-[11px] font-semibold tracking-wide text-indigo-700 uppercase dark:text-indigo-300">
                <flux:icon.sparkles variant="micro" class="size-3.5 animate-pulse" /> AI at work
            </div>
            @foreach ($running as $r)
                @php $pct = $r->percent(); @endphp
                <a href="{{ $r->url ?? '#' }}" wire:navigate wire:key="run{{ $r->id }}" class="block">
                    <div class="flex items-baseline justify-between gap-2">
                        <span class="truncate text-xs font-medium text-zinc-800 dark:text-zinc-100">{{ $r->label }}</span>
                        <span class="num shrink-0 text-[10px] text-zinc-500">{{ $r->total > 1 ? $r->done.'/'.$r->total : '' }} {{ $r->created_at->diffForHumans(null, true, true) }}</span>
                    </div>
                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-white/80 dark:bg-zinc-800">
                        <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-fuchsia-500 transition-[width] duration-700 {{ $pct === null ? 'w-1/3 animate-[scan_1.6s_ease-in-out_infinite]' : '' }}" @if ($pct !== null) style="width: {{ max(4, $pct) }}%" @endif></div>
                    </div>
                    <p class="mt-1 truncate text-[11px] text-zinc-500" title="{{ $r->step }}">{{ $r->step }}</p>
                </a>
            @endforeach
        </div>
    @endif
    <flux:dropdown position="top" align="start">
        <button type="button" class="relative flex w-full items-center gap-2 rounded-lg px-3 py-2 text-sm text-zinc-600 transition hover:bg-zinc-800/5 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-white/5 dark:hover:text-white">
            <span class="relative">
                <flux:icon.bell class="size-5" />
                @if ($unread)<span class="absolute -top-1.5 -right-1.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-500 px-1 text-[10px] font-bold text-white ring-2 ring-white dark:ring-zinc-900">{{ $unread > 9 ? '9+' : $unread }}</span>@endif
            </span>
            <span>Notifications</span>
        </button>

        <flux:menu class="w-80 !p-0">
            <div class="flex items-center justify-between border-b border-zinc-100 px-4 py-3 dark:border-zinc-700">
                <span class="text-sm font-semibold">Notifications</span>
                @if ($unread)<button wire:click="markAllRead" class="text-xs text-zinc-500 hover:text-indigo-600">Mark all read</button>@endif
            </div>
            <div class="max-h-96 overflow-y-auto">
                @forelse ($items as $n)
                    @php $tone = ['success' => 'bg-emerald-500', 'warning' => 'bg-amber-500', 'danger' => 'bg-rose-500'][$n->data['status'] ?? 'success'] ?? 'bg-zinc-400'; @endphp
                    <button wire:click="openItem('{{ $n->id }}')" wire:key="n{{ $n->id }}" class="flex w-full gap-3 px-4 py-3 text-left transition hover:bg-zinc-50 dark:hover:bg-zinc-800/60 {{ $n->read_at ? 'opacity-60' : '' }}">
                        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $n->read_at ? 'bg-zinc-300 dark:bg-zinc-600' : $tone }}"></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $n->data['title'] }}</span>
                            <span class="block text-xs text-zinc-500">{{ $n->data['body'] }}</span>
                            <span class="mt-0.5 block text-[11px] text-zinc-400">{{ $n->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                @empty
                    <div class="px-4 py-8 text-center text-sm text-zinc-500">
                        <flux:icon.bell-slash class="mx-auto mb-2 size-6 text-zinc-300" />
                        Nothing yet. You will see finished scans and updates here.
                    </div>
                @endforelse
            </div>
            <div class="border-t border-zinc-100 px-4 py-2.5 text-xs dark:border-zinc-700">
                <template x-if="perm === 'default'"><button x-on:click="ask()" class="font-medium text-indigo-600 hover:underline">Turn on desktop notifications</button></template>
                <template x-if="perm === 'granted'"><span class="text-zinc-400">Desktop notifications are on</span></template>
                <template x-if="perm === 'denied'"><span class="text-zinc-400">Desktop notifications are blocked in this browser’s settings</span></template>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
