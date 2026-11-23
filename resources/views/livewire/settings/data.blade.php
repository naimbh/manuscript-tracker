<?php

use App\Support\Backup;
use App\Support\VenueResearcher;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Title('Backup & calendar')] class extends Component {
    use WithFileUploads;

    public $backup;

    public function import(): void
    {
        $this->validate(['backup' => 'required|file|mimetypes:application/json,text/plain,application/zip,application/x-zip-compressed|max:512000']);
        try {
            $n = Backup::importFile(auth()->user(), $this->backup->getRealPath());
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->addError('backup', $e->getMessage());
            return;
        }
        $this->reset('backup');
        Flux::toast(variant: 'success', text: Backup::summary($n));
    }

    public function resetToken(): void
    {
        auth()->user()->forceFill(['calendar_token' => null])->save();
        Flux::toast(text: 'Calendar link changed. Update it in your calendar app.');
    }

    public function with(): array
    {
        return ['feed' => route('calendar.feed', auth()->user()->calendarToken()), 'ai' => VenueResearcher::status()];
    }
}; ?>

<section class="page-in w-full">
    @include('partials.settings-heading')
    <x-settings.layout heading="Backup & calendar" subheading="Your data stays in your own database. Export it, bring it back, or show your deadlines in any calendar app.">
        <div class="space-y-8">
            <div class="space-y-3">
                <flux:heading>Calendar feed</flux:heading>
                <flux:text>Subscribe to this link in Google Calendar, Apple Calendar or Outlook. It lists your deadlines, revision due dates and expected decisions, with a reminder one day before.</flux:text>
                <div x-data="{ c: false }" class="flex gap-2">
                    <flux:input readonly value="{{ $feed }}" class="font-mono text-xs" />
                    <flux:button icon="clipboard" x-on:click="navigator.clipboard.writeText('{{ $feed }}'); c = true; setTimeout(() => c = false, 1500)"><span x-text="c ? 'Copied' : 'Copy'"></span></flux:button>
                </div>
                <div class="flex gap-2"><flux:button size="sm" variant="ghost" icon="arrow-down-tray" :href="$feed">Download .ics</flux:button><flux:button size="sm" variant="ghost" wire:click="resetToken" wire:confirm="Make a new link? The old one stops working.">New link</flux:button></div>
            </div>
            <flux:separator />
            <div class="space-y-3">
                <flux:heading>Backup</flux:heading>
                <flux:text>Everything in your account: papers, submissions, journals and conferences, authors, checklists, deadlines, publications, calls for papers, your profile and site settings. API keys and passwords are never included.</flux:text>
                <div class="flex flex-wrap gap-2">
                    <flux:button icon="arrow-down-tray" :href="route('data.export.zip')">Download backup (.zip with files)</flux:button>
                    <flux:button icon="arrow-down-tray" variant="ghost" :href="route('data.export')">Download data only (.json)</flux:button>
                </div>
                <flux:text size="sm">The .zip also holds your uploaded files. You can import either file here or in the desktop app.</flux:text>
            </div>
            <form wire:submit="import" class="space-y-3">
                <flux:heading>Import</flux:heading>
                <flux:text>Choose a .zip or .json backup. Its records are added to this account, and nothing existing is deleted. Calls for papers you already have are skipped, and only empty profile fields are filled.</flux:text>
                <input type="file" wire:model="backup" accept=".json,.zip,application/json,application/zip" class="block text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-zinc-100 file:px-3 file:py-2 file:text-sm file:font-medium dark:file:bg-zinc-800" />
                @error('backup')<p class="text-sm text-rose-600">{{ $message }}</p>@enderror
                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">Import backup</flux:button>
            </form>
            <flux:separator />
            <div class="space-y-1">
                <flux:heading>AI updates</flux:heading>
                <flux:text>Using: {{ $ai }}. Change it under <a href="{{ route('settings.ai') }}" wire:navigate class="text-indigo-600 hover:underline">Settings → AI</a>.</flux:text>
            </div>
        </div>
    </x-settings.layout>
</section>
