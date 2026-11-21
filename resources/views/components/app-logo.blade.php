@php
    $name = trim(config('app.name'));
    $first = str_contains($name, ' ') ? \Illuminate\Support\Str::beforeLast($name, ' ') : $name;
    $last = str_contains($name, ' ') ? \Illuminate\Support\Str::afterLast($name, ' ') : '';
@endphp
<x-app-logo-icon class="size-8 shrink-0 drop-shadow-sm" />
<div class="ml-1 grid flex-1 text-left leading-tight">
    <span class="truncate text-sm font-semibold tracking-tight">{{ $first }}@if ($last)<span class="bg-gradient-to-r from-indigo-600 to-fuchsia-600 bg-clip-text text-transparent dark:from-indigo-400 dark:to-fuchsia-400">{{ $last }}</span>@endif</span>
    @if (config('tracker.tagline'))<span class="truncate text-[10px] text-zinc-400">{{ config('tracker.tagline') }}</span>@endif
</div>
