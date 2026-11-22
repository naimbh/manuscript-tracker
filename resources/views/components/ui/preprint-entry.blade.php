@props(['paper'])
{{-- A preprint shown in a paper's submission history. --}}
<div class="relative pb-5 pl-7 sm:pb-6 sm:pl-8">
    <span class="absolute top-1.5 left-0 flex size-6 items-center justify-center rounded-full bg-gradient-to-br from-teal-500 to-cyan-500 text-white ring-4 ring-[#f7f8fc] dark:ring-zinc-950">
        <flux:icon.document-arrow-up variant="micro" class="size-3.5" />
    </span>
    <div class="panel p-4 ring-1 ring-teal-100 dark:ring-teal-500/20">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <h3 class="font-semibold">{{ str($paper->preprint_server ?: 'Preprint')->before(' (') }}</h3>
                    <span class="rounded-full bg-teal-50 px-2 py-0.5 text-xs font-medium text-teal-700 ring-1 ring-teal-200 ring-inset dark:bg-teal-500/10 dark:text-teal-300 dark:ring-teal-500/20">Preprint</span>
                </div>
                <p class="mt-0.5 text-sm text-zinc-500">
                    {{ $paper->preprint_on ? 'Posted '.$paper->preprint_on->format('j M Y') : 'Posting date not set' }}
                    @if (str($paper->preprint_server)->contains('(')) · {{ str($paper->preprint_server)->after('(')->beforeLast(')') }}@endif
                </p>
            </div>
            <div class="flex items-center gap-1">
                @if ($paper->preprint_url)<flux:button size="sm" icon-trailing="arrow-up-right" :href="$paper->preprint_url" target="_blank">Open preprint</flux:button>@endif
                <flux:button size="sm" variant="ghost" icon="pencil-square" wire:click="editPaper" title="Edit preprint details" />
            </div>
        </div>
        @unless ($paper->preprint_url)<p class="mt-2 text-xs text-zinc-400">Add the public link in Edit once the preprint is announced.</p>@endunless
    </div>
</div>
