@props(['user', 'size' => 'size-9'])
@php
    $initials = $user->initials();
    $photo = $user->avatarUrl();
@endphp
{{-- The photo when the user has one, initials otherwise; Settings → Profile updates every avatar at once (avatar-updated). --}}
<span x-data="{ photo: @js($photo) }" x-on:avatar-updated.window="photo = $event.detail.url" {{ $attributes->class("relative inline-flex shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-indigo-500 via-violet-500 to-fuchsia-500 p-[2px] shadow-sm shadow-indigo-500/30 $size") }}>
    <img x-show="photo" x-bind:src="photo" @if ($photo) src="{{ $photo }}" @else style="display: none" @endif alt="" draggable="false" class="size-full rounded-full object-cover ring-2 ring-white/70 dark:ring-zinc-900/70" />
    <span x-show="! photo" @if ($photo) style="display: none" @endif class="flex size-full items-center justify-center rounded-full bg-gradient-to-br from-indigo-600 to-fuchsia-600 text-[11px] font-semibold tracking-wide text-white ring-2 ring-white/70 dark:ring-zinc-900/70">{{ $initials }}</span>
    <span class="absolute -right-0.5 -bottom-0.5 size-2.5 rounded-full bg-emerald-500 ring-2 ring-white dark:ring-zinc-900"></span>
</span>
