<?php

use App\Models\Setting;
use App\Support\VenueResearcher;
use Flux\Flux;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('AI')] class extends Component {
    public string $driver = 'claude-code';
    public array $models = [];
    public array $secrets = ['claude-code' => '', 'api' => '', 'openai' => '', 'gemini' => '', 'compatible' => ''];
    public string $claudeBin = '';
    public string $baseUrl = '';
    public ?string $test = null;
    public bool $testOk = false;

    /** Which secret each provider needs, its model setting, and help text. */
    private const PROVIDERS = [
        'claude-code' => ['secret' => 'ai.claude_token', 'model' => 'ai.claude_model', 'cfg' => 'services.ai.claude_model',
            'about' => 'Uses your Claude subscription through the Claude Code CLI on this machine. Web search is built in.'],
        'api' => ['secret' => 'anthropic.key', 'model' => 'anthropic.model', 'cfg' => 'services.anthropic.model',
            'about' => 'Anthropic API key from console.anthropic.com, billed per use. Uses Claude’s web search tool.'],
        'openai' => ['secret' => 'openai.key', 'model' => 'openai.model', 'cfg' => 'services.openai.model',
            'about' => 'OpenAI API key from platform.openai.com. Uses the Responses API with web search.'],
        'gemini' => ['secret' => 'gemini.key', 'model' => 'gemini.model', 'cfg' => 'services.gemini.model',
            'about' => 'Google AI Studio key from aistudio.google.com. Uses Google Search grounding.'],
        'compatible' => ['secret' => 'compatible.key', 'model' => 'compatible.model', 'cfg' => 'services.compatible.model',
            'about' => 'Any OpenAI-compatible endpoint: OpenRouter, Groq, Perplexity, Together, Ollama or LM Studio. Web search depends on the model (for example OpenRouter’s “:online” models or Perplexity Sonar).'],
    ];

    public function mount(): void
    {
        $this->driver = VenueResearcher::driver();
        foreach (self::PROVIDERS as $k => $p) {
            $this->models[$k] = (string) config($p['cfg']);
        }
        $this->claudeBin = (string) (Setting::find('ai.claude_bin')?->plain() ?? '');
        $this->baseUrl = (string) config('services.compatible.base_url');
    }

    public function save(): void
    {
        $this->validate([
            'driver' => 'required|in:'.implode(',', array_keys(self::PROVIDERS)),
            'models.*' => 'nullable|string|max:120',
            'secrets.*' => ['nullable', 'string', 'max:400', 'regex:/^[A-Za-z0-9_\-.:]+$/'],
            'claudeBin' => 'nullable|string|max:500',
            'baseUrl' => 'nullable|url|max:300',
        ], ['secrets.*.regex' => 'Paste the key as one line, with no spaces or quotes.']);

        Setting::put('ai.driver', $this->driver);
        foreach (self::PROVIDERS as $k => $p) {
            Setting::put($p['model'], trim($this->models[$k] ?? '') ?: null);
        }
        foreach ($this->secrets as $provider => $value) {
            if (filled($value) && isset(self::PROVIDERS[$provider])) {
                Setting::put(self::PROVIDERS[$provider]['secret'], trim($value));
            }
        }
        Setting::put('ai.claude_bin', trim($this->claudeBin) ?: null);
        Setting::put('compatible.base_url', trim($this->baseUrl) ?: null);
        $this->secrets = array_map(fn () => '', $this->secrets);
        Setting::applyToConfig();
        $this->test = null;
        Flux::toast(variant: 'success', text: 'AI settings saved.');
    }

    public function forget(string $key): void
    {
        abort_unless(in_array($key, Setting::SECRET, true), 422);
        Setting::put($key, null);
        Flux::toast(text: 'Removed. A value in .env, if any, is used again.');
    }

    public function testConnection(): void
    {
        Setting::applyToConfig();
        try {
            $reply = app(VenueResearcher::class)->ping();
            $this->testOk = true;
            $this->test = 'Connected to '.VenueResearcher::status().'. Reply: “'.str($reply)->limit(40).'”';
        } catch (\Throwable $e) {
            $this->testOk = false;
            $this->test = $e->getMessage();
        }
    }

    public function with(): array
    {
        $saved = [];
        foreach (Setting::SECRET as $k) {
            $v = Setting::find($k)?->plain();
            $saved[$k] = $v ? str_repeat('•', 8).substr($v, -4) : null;
        }

        return ['status' => VenueResearcher::status(), 'ready' => VenueResearcher::configured(), 'bin' => VenueResearcher::claudeBinary(),
            'saved' => $saved, 'providers' => self::PROVIDERS, 'names' => VenueResearcher::PROVIDERS];
    }
}; ?>

<section class="page-in w-full">
    @include('partials.settings-heading')
    <x-settings.layout heading="AI" subheading="Choose the AI that runs scans, venue updates and citation updates. Keys are stored encrypted.">
        <form wire:submit="save" class="space-y-7">
            <div class="flex items-center gap-3 rounded-xl border p-3 text-sm {{ $ready ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-300' : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-300' }}">
                <flux:icon :name="$ready ? 'check-circle' : 'exclamation-triangle'" variant="mini" />
                <span class="flex-1">{{ $status }}</span>
                <flux:button size="sm" wire:click="testConnection" wire:loading.attr="disabled" wire:target="testConnection">
                    <span wire:loading.remove wire:target="testConnection">Test connection</span><span wire:loading wire:target="testConnection">Testing…</span>
                </flux:button>
            </div>
            @if ($test)<p class="-mt-4 text-sm {{ $testOk ? 'text-emerald-600' : 'text-rose-600' }}">{{ $test }}</p>@endif

            <flux:radio.group wire:model.live="driver" label="Provider" variant="cards" class="grid! grid-cols-1 gap-3 sm:grid-cols-2">
                @foreach ($names as $k => $label)
                    <flux:radio value="{{ $k }}" label="{{ $label }}" />
                @endforeach
            </flux:radio.group>

            @php $p = $providers[$driver]; $secret = $p['secret']; @endphp
            <div class="space-y-5 rounded-2xl border border-zinc-200 p-5 dark:border-zinc-700" wire:key="prov-{{ $driver }}">
                <p class="text-sm text-zinc-600 dark:text-zinc-400">{{ $p['about'] }}</p>

                @if ($driver === 'compatible')
                    <flux:input wire:model="baseUrl" label="Base URL" placeholder="https://openrouter.ai/api/v1" description:trailing="Examples: https://openrouter.ai/api/v1 · https://api.groq.com/openai/v1 · https://api.perplexity.ai · http://localhost:11434/v1 (Ollama)" />
                @endif

                <flux:field>
                    <flux:label>{{ $driver === 'claude-code' ? 'Sign-in token' : 'API key' }}{{ $driver === 'compatible' ? ' (if the provider needs one)' : '' }}</flux:label>
                    @if ($driver === 'claude-code')<flux:description>Run <code class="rounded bg-zinc-100 px-1 dark:bg-zinc-800">claude setup-token</code> in Terminal and paste the token.</flux:description>@endif
                    <flux:input wire:model="secrets.{{ $driver }}" type="password" autocomplete="off" viewable
                                :placeholder="$saved[$secret] ? 'Saved: '.$saved[$secret].' (leave empty to keep)' : 'Paste here'" />
                    <flux:error name="secrets.{{ $driver }}" />
                    @if ($saved[$secret])<button type="button" wire:click="forget('{{ $secret }}')" class="justify-self-start text-xs text-zinc-500 hover:text-rose-600">Remove saved key</button>@endif
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2">
                    @if ($driver === 'claude-code')
                        <flux:select wire:model="models.claude-code" label="Model">
                            <flux:select.option value="opus">Opus (best quality)</flux:select.option>
                            <flux:select.option value="sonnet">Sonnet (faster)</flux:select.option>
                            <flux:select.option value="haiku">Haiku (lightest)</flux:select.option>
                        </flux:select>
                        <flux:input wire:model="claudeBin" label="CLI path (optional)" :placeholder="$bin ? 'Found automatically' : '/usr/local/bin/claude'" />
                    @else
                        <flux:input wire:model="models.{{ $driver }}" label="Model" :placeholder="['api' => 'claude-opus-5-5', 'openai' => 'gpt-5', 'gemini' => 'gemini-2.5-flash', 'compatible' => 'perplexity/sonar or openai/gpt-4o:online'][$driver]" description:trailing="Use the model name exactly as the provider lists it." />
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-3">
                <flux:button type="submit" variant="primary">Save</flux:button>
                <span class="text-xs text-zinc-500">Saved settings override the .env file.</span>
            </div>
        </form>
    </x-settings.layout>
</section>
