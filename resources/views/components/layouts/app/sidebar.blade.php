<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="soft-bg min-h-screen text-zinc-800 dark:text-zinc-200"
          x-data
          x-on:keydown.window="
            if ($event.metaKey || $event.ctrlKey || $event.altKey) return;
            const t = $event.target.tagName;
            if (['INPUT','TEXTAREA','SELECT'].includes(t) || $event.target.isContentEditable) return;
            if ($event.key === 'n') { $event.preventDefault(); $flux.modal('new-paper').show(); }
            if ($event.key === '/') { const s = document.querySelector('[data-search]'); if (s) { $event.preventDefault(); s.focus(); } }
          ">
        <flux:sidebar sticky stashable class="border-r border-zinc-200/70 bg-white/60 backdrop-blur dark:border-zinc-800 dark:bg-zinc-900/60">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <a href="{{ route('dashboard') }}" class="mr-5 flex items-center space-x-2" wire:navigate>
                <x-app-logo />
            </a>

            <button type="button" x-on:click="$flux.modal('new-paper').show()"
                    class="group relative flex h-10 w-full items-center gap-2.5 overflow-hidden rounded-xl bg-gradient-to-r from-indigo-600 to-violet-600 pr-2 pl-2 text-sm font-medium text-white shadow-md shadow-indigo-600/25 transition duration-200 hover:shadow-lg hover:shadow-indigo-600/30">
                <span class="pointer-events-none absolute inset-0 bg-gradient-to-r from-white/0 via-white/15 to-white/0 opacity-0 transition duration-500 group-hover:opacity-100"></span>
                <span class="relative flex size-6 shrink-0 items-center justify-center rounded-lg bg-white/20 transition"><flux:icon.plus variant="micro" class="size-4" /></span>
                <span class="relative flex-1 text-left leading-none">New paper</span>
                <kbd class="relative flex h-5 min-w-5 items-center justify-center rounded-md bg-white/15 px-1.5 font-sans text-[10px] leading-none font-semibold text-white/90 ring-1 ring-white/25">N</kbd>
            </button>

            <flux:navlist variant="outline">
                <flux:navlist.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>Desk</flux:navlist.item>
                <flux:navlist.item icon="document-text" :href="route('papers.index')" :current="request()->routeIs('papers.*')" wire:navigate>Papers</flux:navlist.item>
                <flux:navlist.item icon="trophy" :href="route('publications')" :current="request()->routeIs('publications')" wire:navigate>Published</flux:navlist.item>
                <flux:navlist.item icon="building-library" :href="route('venues')" :current="request()->routeIs('venues')" wire:navigate>Venues</flux:navlist.item>
                @php $newOpps = auth()->user()->opportunities()->where('status', 'new')->where('created_at', '>=', now()->startOfWeek())->count(); @endphp
                <flux:navlist.item icon="sparkles" :href="route('opportunities')" :current="request()->routeIs('opportunities')" :badge="$newOpps ?: null" wire:navigate>Opportunities</flux:navlist.item>
                <flux:navlist.item icon="calendar-days" :href="route('deadlines')" :current="request()->routeIs('deadlines')" wire:navigate>Deadlines</flux:navlist.item>
            </flux:navlist>

            <flux:spacer />

            <livewire:notification-bell />

            <flux:navlist variant="outline">
                <flux:navlist.item icon="arrow-down-tray" :href="route('settings.data')" :current="request()->routeIs('settings.data')" wire:navigate>Backup &amp; calendar</flux:navlist.item>
            </flux:navlist>

            <flux:dropdown position="bottom" align="start">
                <button type="button" class="flex w-full items-center gap-3 rounded-xl p-1.5 text-left transition hover:bg-zinc-800/5 dark:hover:bg-white/5">
                    <x-ui.avatar :user="auth()->user()" />
                    <span class="min-w-0 flex-1 leading-tight">
                        <span class="block truncate text-sm font-semibold text-zinc-800 dark:text-zinc-100">{{ auth()->user()->name }}</span>
                        <span class="block truncate text-[11px] text-zinc-500">{{ auth()->user()->email }}</span>
                    </span>
                    <flux:icon.chevrons-up-down variant="micro" class="size-4 shrink-0 text-zinc-400" />
                </button>

                <flux:menu class="w-[220px]">
                    <div class="flex items-center gap-3 px-2 py-2">
                        <x-ui.avatar :user="auth()->user()" size="size-10" />
                        <div class="min-w-0 leading-tight"><div class="truncate text-sm font-semibold">{{ auth()->user()->name }}</div><div class="truncate text-xs text-zinc-500">{{ auth()->user()->email }}</div></div>
                    </div>
                    <flux:menu.separator />
                    <flux:menu.item :href="route('settings.profile')" icon="cog" wire:navigate>Settings</flux:menu.item>
                    <flux:menu.item :href="route('settings.research')" icon="academic-cap" wire:navigate>Research profile</flux:menu.item>
                    <flux:menu.item :href="route('settings.ai')" icon="sparkles" wire:navigate>AI settings</flux:menu.item>
                    <flux:menu.item :href="route('settings.appearance')" icon="sun" wire:navigate>Appearance</flux:menu.item>
                    <flux:menu.separator />
                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Log out</flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:sidebar>

        <flux:header class="border-b border-zinc-200/80 lg:hidden dark:border-zinc-800">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />
            <a href="{{ route('dashboard') }}" class="ml-2 flex items-center gap-2" wire:navigate><x-app-logo /></a>
            <flux:spacer />
            <flux:modal.trigger name="new-paper">
                <flux:button size="sm" variant="primary" icon="plus" aria-label="New paper" />
            </flux:modal.trigger>
        </flux:header>

        {{ $slot }}

        <livewire:new-paper />

        @persist('toast')
            <flux:toast position="bottom end" />
        @endpersist

        @fluxScripts
    </body>
</html>
