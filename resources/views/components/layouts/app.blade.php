<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="flex min-h-[calc(100dvh-3.5rem)] flex-col !px-4 !pt-4 !pb-3 sm:!p-6 lg:min-h-dvh lg:!p-8">
        <div class="flex-1">{{ $slot }}</div>
        <x-ui.footer />
    </flux:main>
</x-layouts.app.sidebar>
