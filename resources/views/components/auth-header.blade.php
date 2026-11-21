@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-1.5 text-center">
    <h1 class="text-xl font-semibold tracking-tight text-zinc-900 dark:text-zinc-100">{{ $title }}</h1>
    <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $description }}</p>
</div>
