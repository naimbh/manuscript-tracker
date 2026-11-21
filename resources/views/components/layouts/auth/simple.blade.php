<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="soft-bg min-h-screen antialiased text-zinc-800 dark:text-zinc-200">
        <div class="flex min-h-svh">
            {{-- Brand panel (large screens) --}}
            <aside class="relative hidden w-[46%] max-w-2xl flex-col justify-between overflow-hidden bg-gradient-to-br from-indigo-600 via-violet-600 to-fuchsia-600 p-12 text-white lg:flex">
                <div class="pointer-events-none absolute -top-24 -right-24 size-96 rounded-full bg-white/10 blur-3xl"></div>
                <div class="pointer-events-none absolute -bottom-32 -left-20 size-96 rounded-full bg-fuchsia-300/20 blur-3xl"></div>
                <div class="hero-grid pointer-events-none absolute inset-0 text-white opacity-60"></div>

                <a href="{{ route('home') }}" class="relative flex items-center gap-3" wire:navigate>
                    <span class="rounded-2xl bg-white/20 p-1 ring-1 ring-white/40 shadow-lg shadow-indigo-900/30"><x-app-logo-icon class="size-10" /></span>
                    <span class="leading-tight">
                        <span class="block text-lg font-semibold tracking-tight">Manuscript Tracker</span>
                        <span class="block text-xs text-white/70">from draft to decision</span>
                    </span>
                </a>

                <div class="relative">
                    <h2 class="max-w-md text-4xl leading-tight font-semibold tracking-tight text-balance">Every paper, every venue, every decision, in one place.</h2>
                    <ul class="mt-8 space-y-4 text-sm text-white/85">
                        @foreach ([
                            ['clock', 'Review clocks that tell you when a decision is overdue'],
                            ['building-library', 'A venue library kept current with AI and real sources'],
                            ['sparkles', 'Daily calls for papers that fit your research'],
                            ['chart-bar', 'Your citations and h-index from Google Scholar'],
                        ] as [$icon, $text])
                            <li class="flex items-center gap-3">
                                <span class="flex size-8 shrink-0 items-center justify-center rounded-lg bg-white/15 ring-1 ring-white/20"><flux:icon :name="$icon" variant="mini" class="size-4" /></span>
                                {{ $text }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Mini journey graphic --}}
                <div class="relative rounded-2xl bg-white/10 p-4 ring-1 ring-white/20 backdrop-blur">
                    <div class="flex items-center justify-between text-[11px] text-white/70"><span>Journey</span><span>3 venues</span></div>
                    <div class="mt-3 flex items-center">
                        @foreach ([['x-mark', 'bg-rose-400', 'Conference'], ['x-mark', 'bg-rose-400', 'Journal A'], ['clock', 'bg-sky-400', 'Journal B']] as $i => [$ic, $bg, $label])
                            @if ($i > 0)<span class="mx-2 h-px flex-1 bg-white/30"></span>@endif
                            <span class="flex flex-col items-center gap-1.5">
                                <span class="flex size-8 items-center justify-center rounded-full {{ $bg }} text-white ring-4 ring-white/15 {{ $i === 2 ? 'shadow-lg shadow-sky-500/40' : '' }}"><flux:icon :name="$ic" variant="micro" class="size-4" /></span>
                                <span class="text-[10px] text-white/80">{{ $label }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>
            </aside>

            {{-- Form --}}
            <main class="flex flex-1 flex-col items-center justify-center px-5 py-10 sm:px-10">
                <div class="w-full max-w-sm">
                    <a href="{{ route('home') }}" class="mb-8 flex flex-col items-center gap-3 lg:hidden" wire:navigate>
                        <x-app-logo-icon class="size-14 drop-shadow-md" />
                        <span class="text-center leading-tight">
                            <span class="block text-xl font-semibold tracking-tight">Manuscript<span class="bg-gradient-to-r from-indigo-600 to-fuchsia-600 bg-clip-text text-transparent">Tracker</span></span>
                            <span class="mt-0.5 block text-xs text-zinc-500">from draft to decision</span>
                        </span>
                    </a>
                    <div class="rounded-2xl border border-zinc-200/80 bg-white p-6 shadow-xl shadow-indigo-900/5 sm:p-8 dark:border-zinc-800 dark:bg-zinc-900">
                        <div class="flex flex-col gap-6">
                            {{ $slot }}
                        </div>
                    </div>
                    <p class="mt-6 text-center text-[11px] text-zinc-400">
                        © {{ now()->year }} @if (config('tracker.credit.name')){{ config('tracker.credit.name') }} · @endif Manuscript Tracker
                    </p>
                </div>
            </main>
        </div>
        @fluxScripts
    </body>
</html>
