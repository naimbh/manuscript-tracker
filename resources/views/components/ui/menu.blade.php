@props(['align' => 'right', 'label' => 'More actions'])
{{-- A light dropdown menu (Alpine only). Use x-ui.menu-item inside. --}}
<div {{ $attributes->class('relative') }} x-data="{ o: false }" x-on:click.outside="o = false" x-on:keydown.escape.window="o = false">
    @isset($trigger)
        <div x-on:click="o = ! o">{{ $trigger }}</div>
    @else
        <button type="button" x-on:click="o = ! o" :aria-expanded="o" aria-label="{{ $label }}" title="{{ $label }}"
                class="inline-flex size-8 items-center justify-center rounded-lg text-zinc-500 transition hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-zinc-800 dark:hover:text-zinc-200">
            <svg viewBox="0 0 20 20" fill="currentColor" class="size-4"><path d="M3 10a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0Zm5.5 0a1.5 1.5 0 1 1 3 0 1.5 1.5 0 0 1-3 0ZM15.5 8.5a1.5 1.5 0 1 0 0 3 1.5 1.5 0 0 0 0-3Z"/></svg>
        </button>
    @endisset
    <div x-show="o" x-cloak x-transition.opacity.duration.100ms x-on:click="o = false"
         class="absolute top-full z-40 mt-1 min-w-48 rounded-xl border border-zinc-200 bg-white p-1 text-sm shadow-lg shadow-zinc-900/10 {{ $align === 'left' ? 'left-0' : 'right-0' }} dark:border-zinc-700 dark:bg-zinc-900">
        {{ $slot }}
    </div>
</div>
