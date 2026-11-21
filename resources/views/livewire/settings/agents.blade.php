<?php

use Flux\Flux;
use Illuminate\Support\Str;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Agents')] class extends Component {
    public ?string $plain = null;

    public function generate(): void
    {
        $token = 'mt_'.Str::random(40);
        auth()->user()->forceFill(['mcp_token' => hash('sha256', $token)])->save();
        $this->plain = $token;
        Flux::toast(variant: 'success', text: 'New agent token created. Copy it now; it is shown only once.');
    }

    public function revoke(): void
    {
        auth()->user()->forceFill(['mcp_token' => null])->save();
        $this->plain = null;
        Flux::toast(text: 'Agent token revoked.');
    }

    public function with(): array
    {
        $php = config('services.ai.php_bin') ?: PHP_BINARY;
        $local = json_encode(['mcpServers' => ['manuscript-tracker' => ['command' => $php, 'args' => [base_path('artisan'), 'mcp:start', 'tracker']]]], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        return [
            'hasToken' => filled(auth()->user()->mcp_token),
            'url' => url('/mcp'),
            'local' => $local,
            'cli' => 'claude mcp add manuscript-tracker -- '.escapeshellarg($php).' '.escapeshellarg(base_path('artisan')).' mcp:start tracker',
        ];
    }
}; ?>

<section class="page-in w-full">
    @include('partials.settings-heading')
    <x-settings.layout heading="Agents (MCP)" subheading="Let AI assistants such as Claude Desktop, Claude Code or Cursor read your papers and record submissions, decisions and deadlines.">
        <div class="space-y-8" x-data="{ copied: null, copy(t, k) { navigator.clipboard.writeText(t); this.copied = k; setTimeout(() => this.copied = null, 1500) } }">
            <div class="rounded-xl border border-zinc-200 p-4 text-sm dark:border-zinc-700">
                <p class="font-medium">What agents can do</p>
                <p class="mt-1 text-zinc-500">List and read papers, record a submission, record an editor’s decision, list and add deadlines, search the venue library, list calls for papers and list published work with citations.</p>
            </div>

            <div class="space-y-3">
                <flux:heading>On this computer</flux:heading>
                <flux:text>Add this server to your assistant’s MCP settings. For Claude Desktop, open Settings → Developer → Edit Config. It acts for {{ config('tracker.mcp_user') ?: 'the first account' }}; set TRACKER_MCP_USER in .env to choose another.</flux:text>
                <div class="relative">
                    <pre class="overflow-x-auto rounded-xl bg-zinc-950 p-4 font-mono text-[12px] leading-relaxed text-zinc-200">{{ $local }}</pre>
                    <button type="button" x-on:click="copy(@js($local), 'local')" class="absolute top-2 right-2 rounded-md bg-white/10 px-2 py-1 text-[11px] text-white hover:bg-white/20" x-text="copied === 'local' ? 'Copied' : 'Copy'"></button>
                </div>
                <flux:text>Or, with Claude Code:</flux:text>
                <div class="relative">
                    <pre class="overflow-x-auto rounded-xl bg-zinc-950 p-4 font-mono text-[12px] text-zinc-200">{{ $cli }}</pre>
                    <button type="button" x-on:click="copy(@js($cli), 'cli')" class="absolute top-2 right-2 rounded-md bg-white/10 px-2 py-1 text-[11px] text-white hover:bg-white/20" x-text="copied === 'cli' ? 'Copied' : 'Copy'"></button>
                </div>
            </div>

            <flux:separator />

            <div class="space-y-3">
                <flux:heading>Over the web</flux:heading>
                <flux:text>For assistants that connect to a URL. Send the token as <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">Authorization: Bearer …</code> to <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">{{ $url }}</code>.</flux:text>
                @if ($plain)
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-3 dark:border-emerald-500/20 dark:bg-emerald-500/10">
                        <p class="text-xs font-medium text-emerald-800 dark:text-emerald-300">Your new token. Copy it now; it will not be shown again.</p>
                        <div class="mt-2 flex gap-2">
                            <flux:input readonly value="{{ $plain }}" class="font-mono text-xs" />
                            <flux:button x-on:click="copy(@js($plain), 'tok')"><span x-text="copied === 'tok' ? 'Copied' : 'Copy'"></span></flux:button>
                        </div>
                    </div>
                @endif
                <div class="flex items-center gap-2">
                    @if ($hasToken)
                        <flux:button wire:click="generate" icon="key" wire:confirm="Make a new token? The old one stops working.">Replace token</flux:button>
                    @else
                        <flux:button wire:click="generate" variant="primary" icon="key">Create token</flux:button>
                    @endif
                    @if ($hasToken)<flux:button variant="ghost" class="!text-rose-600" wire:click="revoke" wire:confirm="Revoke the agent token?">Revoke</flux:button>@endif
                    <span class="text-xs text-zinc-500">{{ $hasToken ? 'A token is active.' : 'No token yet.' }}</span>
                </div>
            </div>
        </div>
    </x-settings.layout>
</section>
