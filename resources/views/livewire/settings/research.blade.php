<?php

use App\Support\OpportunityScanner;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Research profile')] class extends Component {
    public string $scholar_url = '';
    public string $interests = '';

    public function mount(): void
    {
        $u = auth()->user();
        $this->scholar_url = (string) $u->scholar_url;
        $this->interests = (string) $u->interests;
    }

    public function save(): void
    {
        $d = $this->validate([
            'scholar_url' => ['nullable', 'url', 'max:255', 'regex:#^https://scholar\.google\.[a-z.]+/citations\?#'],
            'interests' => 'nullable|string|max:1000',
        ], ['scholar_url.regex' => 'Use your profile link, which starts with https://scholar.google.com/citations?user=…']);
        $u = auth()->user();
        if (($d['scholar_url'] ?: null) !== $u->scholar_url) {
            $u->scholar_stats = null;
            $u->scholar_checked_at = null;
        }
        $u->forceFill(['scholar_url' => $d['scholar_url'] ?: null, 'interests' => $d['interests'] ?: null])->save();
        Flux::toast(variant: 'success', text: 'Research profile saved.');
    }

    public function with(): array
    {
        return ['suggested' => OpportunityScanner::interestsFor(auth()->user()->replicate()->forceFill(['interests' => null]))];
    }
}; ?>

<section class="page-in w-full">
    @include('partials.settings-heading')
    <x-settings.layout heading="Research profile" subheading="Used by the Published page and the daily calls for papers scan.">
        <form wire:submit="save" class="space-y-6">
            <flux:field>
                <flux:label>Google Scholar profile</flux:label>
                <flux:description>Open your profile on Google Scholar and copy the address. Citation counts, the h-index and the per-year chart are read from it.</flux:description>
                <flux:input wire:model="scholar_url" placeholder="https://scholar.google.com/citations?user=…" />
                <flux:error name="scholar_url" />
            </flux:field>
            <flux:field>
                <flux:label>Research interests</flux:label>
                <flux:description>Topics separated by commas. The calls for papers scan uses them to find calls that fit. Leave empty to use your papers’ keywords: {{ $suggested }}.</flux:description>
                <flux:textarea wire:model="interests" rows="3" placeholder="platform labor, CSCW, ICT4D" />
            </flux:field>
            <flux:button type="submit" variant="primary">Save</flux:button>
        </form>
    </x-settings.layout>
</section>
