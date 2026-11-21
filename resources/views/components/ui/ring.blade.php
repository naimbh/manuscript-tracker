@props(['pct' => 0, 'size' => 56, 'tone' => 'indigo', 'label' => null, 'sub' => null])
@php
    $r = 22; $c = 2 * M_PI * $r;
    $colors = ['indigo' => '#6366f1', 'red' => '#ef4444', 'amber' => '#f59e0b', 'green' => '#10b981', 'zinc' => '#a1a1aa'];
@endphp
<div {{ $attributes->class('relative shrink-0') }} style="width: {{ $size }}px; height: {{ $size }}px"
     x-data="{ p: 0 }" x-init="requestAnimationFrame(() => p = {{ (int) $pct }})">
    <svg viewBox="0 0 52 52" class="size-full -rotate-90">
        <circle cx="26" cy="26" r="{{ $r }}" fill="none" stroke-width="5" class="ring-track" />
        <circle cx="26" cy="26" r="{{ $r }}" fill="none" stroke-width="5" stroke-linecap="round" class="ring-fill"
                stroke="{{ $colors[$tone] ?? $colors['indigo'] }}" stroke-dasharray="{{ $c }}"
                :stroke-dashoffset="{{ $c }} * (1 - p / 100)" stroke-dashoffset="{{ $c }}" />
    </svg>
    <div class="absolute inset-0 flex flex-col items-center justify-center leading-none">
        <span class="num text-[13px] font-semibold">{{ $label ?? $pct.'%' }}</span>
        @if ($sub)<span class="mt-0.5 text-[9px] uppercase tracking-wide text-zinc-400">{{ $sub }}</span>@endif
    </div>
</div>
