<?php

use App\Models\Setting;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('General')] class extends Component {
    public string $name = '';
    public string $tagline = '';
    public string $description = '';
    public string $contact_email = '';
    public string $timezone = 'UTC';
    public bool $registration = true;
    public string $credit_name = '';
    public string $credit_url = '';

    public function mount(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        $this->name = (string) config('app.name');
        $this->tagline = (string) config('tracker.tagline');
        $this->description = (string) config('tracker.description');
        $this->contact_email = (string) config('tracker.contact_email');
        $this->timezone = (string) config('app.timezone');
        $this->registration = (bool) config('tracker.registration');
        $this->credit_name = (string) config('tracker.credit.name');
        $this->credit_url = (string) config('tracker.credit.url');
    }

    public function save(): void
    {
        abort_unless(auth()->user()->is_admin, 403);
        $d = $this->validate([
            'name' => 'required|string|max:60',
            'tagline' => 'nullable|string|max:80',
            'description' => 'nullable|string|max:300',
            'contact_email' => 'nullable|email|max:255',
            'timezone' => 'required|timezone:all',
            'registration' => 'boolean',
            'credit_name' => 'nullable|string|max:80',
            'credit_url' => 'nullable|url|max:255',
        ]);
        Setting::put('site.name', $d['name']);
        Setting::put('site.tagline', $d['tagline'] ?? '');
        Setting::put('site.description', $d['description'] ?? '');
        Setting::put('site.contact_email', $d['contact_email'] ?? '');
        Setting::put('site.timezone', $d['timezone']);
        Setting::put('site.registration', $d['registration'] ? '1' : '0');
        Setting::put('site.credit_name', $d['credit_name'] ?? '');
        Setting::put('site.credit_url', $d['credit_url'] ?? '');
        Flux::toast(variant: 'success', text: 'General settings saved.');
        $this->redirectRoute('settings.general', navigate: false);
    }

    public function with(): array
    {
        return ['zones' => \DateTimeZone::listIdentifiers(), 'now' => now()->format('H:i, j M Y')];
    }
}; ?>

<section class="page-in w-full">
    @include('partials.settings-heading')
    <x-settings.layout heading="General" subheading="Site-wide settings. Only admins see this page.">
        <form wire:submit="save" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" label="Site name" description:trailing="Shown in the sidebar, sign-in page, browser tab and calendar feed." />
                <flux:input wire:model="tagline" label="Tagline" placeholder="from draft to decision" description:trailing="The small line under the name. Leave empty to hide." />
            </div>
            <flux:textarea wire:model="description" label="Description" rows="2" description:trailing="Used by search engines and link previews." />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model="timezone" label="Timezone" searchable description:trailing="Dates, “today” and the scheduled jobs use this. Server time now: {{ $now }}">
                    @foreach ($zones as $z)<flux:select.option value="{{ $z }}">{{ str_replace('_', ' ', $z) }}</flux:select.option>@endforeach
                </flux:select>
                <flux:input wire:model="contact_email" type="email" label="Contact email" placeholder="you@university.edu" description:trailing="Optional. For people who use your site." />
            </div>

            <flux:separator />

            <flux:switch wire:model="registration" label="Allow new sign-ups" description="Turn off to keep the site private to existing accounts. The Sign up link and page disappear." />

            <flux:separator />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="credit_name" label="Footer credit" placeholder="Your name" description:trailing="Leave empty to hide." />
                <flux:input wire:model="credit_url" label="Footer link" placeholder="https://" />
            </div>

            <flux:button type="submit" variant="primary">Save</flux:button>
        </form>
    </x-settings.layout>
</section>
