<?php

use App\Models\Paper;
use Flux\Flux;
use Livewire\Volt\Component;

new class extends Component {
    public string $title = '';
    public string $short_title = '';
    public string $stage = 'drafting';
    public string $method = '';

    public function create(): void
    {
        $data = $this->validate([
            'title' => 'required|string|max:255',
            'short_title' => 'nullable|string|max:80',
            'stage' => 'required|in:'.implode(',', array_keys(Paper::STAGES)),
            'method' => 'nullable|string|max:255',
        ]);

        $paper = auth()->user()->papers()->create($data);
        $this->reset();
        Flux::modal('new-paper')->close();
        Flux::toast(variant: 'success', text: 'Paper added.');
        $this->redirectRoute('papers.index', ['paper' => $paper], navigate: true);
    }
}; ?>

<flux:modal name="new-paper" class="w-full max-w-lg">
    <form wire:submit="create" class="space-y-5">
        <div>
            <flux:heading size="lg">New paper</flux:heading>
            <flux:text class="mt-1">Only the title is needed. Everything else can come later.</flux:text>
        </div>

        <flux:input wire:model="title" label="Title" placeholder="Working title of your paper" autofocus />
        <div class="grid gap-4 sm:grid-cols-2">
            <flux:input wire:model="short_title" label="Short name" placeholder="e.g. Thesis ch. 3" />
            <flux:select wire:model="stage" label="Stage">
                @foreach (Paper::STAGES as $k => $v)
                    <flux:select.option value="{{ $k }}">{{ $v }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <flux:input wire:model="method" label="Method" placeholder="e.g. 30 interviews, survey, case study" />

        <div class="flex justify-end gap-2">
            <flux:modal.close><flux:button variant="ghost">Cancel</flux:button></flux:modal.close>
            <flux:button type="submit" variant="primary">Add paper</flux:button>
        </div>
    </form>
</flux:modal>
