@props(['tag', 'href' => null])
@php
    $palette = ['text-indigo-700 ring-1 ring-inset ring-indigo-200 dark:text-indigo-300 dark:ring-indigo-500/30', 'text-fuchsia-700 ring-1 ring-inset ring-fuchsia-200 dark:text-fuchsia-300 dark:ring-fuchsia-500/30',
        'text-teal-700 ring-1 ring-inset ring-teal-200 dark:text-teal-300 dark:ring-teal-500/30', 'text-orange-700 ring-1 ring-inset ring-orange-200 dark:text-orange-300 dark:ring-orange-500/30',
        'text-sky-700 ring-1 ring-inset ring-sky-200 dark:text-sky-300 dark:ring-sky-500/30', 'text-lime-800 ring-1 ring-inset ring-lime-200 dark:text-lime-300 dark:ring-lime-500/30'];
    $cls = strtolower($tag) === 'thesis' ? 'text-zinc-700 ring-1 ring-inset ring-zinc-300 dark:text-zinc-200 dark:ring-zinc-600' : $palette[crc32(strtolower($tag)) % count($palette)];
@endphp
<span {{ $attributes->class('inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10.5px] font-semibold '.$cls) }}>
    @if (strtolower($tag) === 'thesis')<flux:icon.academic-cap variant="micro" class="size-3" />@endif{{ $tag }}
</span>
