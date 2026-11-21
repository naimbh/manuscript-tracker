<div class="mx-auto flex w-full max-w-6xl items-start max-md:flex-col">
    <div class="w-full pb-4 md:mr-10 md:w-[220px]" x-data x-init="$nextTick(() => $el.querySelector('[data-current]')?.scrollIntoView({ inline: 'center', block: 'nearest' }))">
        <flux:navlist class="max-md:-mx-4 max-md:flex max-md:w-[calc(100%+2rem)] max-md:flex-row max-md:gap-1 max-md:overflow-x-auto max-md:px-4 max-md:[&_[data-flux-navlist-item]]:shrink-0 max-md:[&_[data-flux-navlist-item]]:w-auto">
            @if (auth()->user()?->is_admin)<flux:navlist.item href="{{ route('settings.general') }}" wire:navigate>General</flux:navlist.item>@endif
            <flux:navlist.item href="{{ route('settings.profile') }}" wire:navigate>Profile</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.password') }}" wire:navigate>Password</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.appearance') }}" wire:navigate>Appearance</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.research') }}" wire:navigate>Research profile</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.ai') }}" wire:navigate>AI</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.agents') }}" wire:navigate>Agents (MCP)</flux:navlist.item>
            <flux:navlist.item href="{{ route('settings.data') }}" wire:navigate>Backup &amp; calendar</flux:navlist.item>
        </flux:navlist>
    </div>

    <flux:separator class="md:hidden" />

    <div class="flex-1 self-stretch max-md:pt-6">
        <flux:heading>{{ $heading ?? '' }}</flux:heading>
        <flux:subheading>{{ $subheading ?? '' }}</flux:subheading>

        <div class="mt-5 w-full max-w-2xl">
            {{ $slot }}
        </div>
    </div>
</div>
