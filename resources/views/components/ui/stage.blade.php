@props(['stage'])
@php
    $dot = ['idea' => 'bg-zinc-300', 'drafting' => 'bg-zinc-400', 'ready' => 'bg-violet-500', 'submitted' => 'bg-indigo-500', 'under_review' => 'bg-sky-500',
        'revision' => 'bg-amber-500', 'accepted' => 'bg-emerald-500', 'published' => 'bg-emerald-600', 'shelved' => 'bg-zinc-300'][$stage] ?? 'bg-zinc-300';
@endphp
<span {{ $attributes->class('inline-flex items-center gap-1.5 text-xs text-zinc-500 dark:text-zinc-400') }}>
    <span class="size-1.5 rounded-full {{ $dot }}"></span>{{ \App\Models\Paper::STAGES[$stage] ?? $stage }}
</span>
