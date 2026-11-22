@props(['href' => null])
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800') }}>{{ $slot }}</a>
@else
    <button type="button" {{ $attributes->class('flex w-full items-center gap-2 rounded-lg px-2.5 py-1.5 text-left text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-800') }}>{{ $slot }}</button>
@endif
