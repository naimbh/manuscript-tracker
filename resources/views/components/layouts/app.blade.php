<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="flex min-h-screen flex-col !p-4 sm:!p-6 lg:!p-8">
        <div class="flex-1">{{ $slot }}</div>
        <x-ui.footer />
    </flux:main>
</x-layouts.app.sidebar>
