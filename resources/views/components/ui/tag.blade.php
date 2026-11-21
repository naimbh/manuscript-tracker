@props(['tag', 'href' => null])
@php
    $palette = ['bg-indigo-100 text-indigo-700 dark:bg-indigo-500/15 dark:text-indigo-300', 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-500/15 dark:text-fuchsia-300',
        'bg-teal-100 text-teal-700 dark:bg-teal-500/15 dark:text-teal-300', 'bg-orange-100 text-orange-700 dark:bg-orange-500/15 dark:text-orange-300',
        'bg-sky-100 text-sky-700 dark:bg-sky-500/15 dark:text-sky-300', 'bg-lime-100 text-lime-800 dark:bg-lime-500/15 dark:text-lime-300'];
    $cls = strtolower($tag) === 'thesis' ? 'bg-zinc-900 text-white dark:bg-white dark:text-zinc-900' : $palette[crc32(strtolower($tag)) % count($palette)];
@endphp
<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold '.$cls) }}>
    @if (strtolower($tag) === 'thesis')<flux:icon.academic-cap variant="micro" class="size-3" />@endif{{ $tag }}
</span>
