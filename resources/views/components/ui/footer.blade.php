<footer {{ $attributes->class('mt-8 flex flex-wrap items-center justify-between gap-x-4 gap-y-1 border-t border-zinc-200/60 pt-3 text-[11px] text-zinc-400 dark:border-zinc-800') }}>
    <span>© {{ now()->year }} @if (config('tracker.credit.name'))<span class="text-zinc-600 dark:text-zinc-300">{{ config('tracker.credit.name') }}</span> · @endif {{ config('app.name') }}</span>
    @if (config('tracker.credit.url'))
        <a href="{{ config('tracker.credit.url') }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1 text-zinc-500 transition hover:text-indigo-600">{{ str(config('tracker.credit.url'))->after('://') }}<flux:icon.arrow-up-right variant="micro" class="size-3" /></a>
    @endif
</footer>
