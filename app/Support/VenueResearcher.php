<?php

namespace App\Support;

use Anthropic\Client;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Looks up a venue's latest call for papers on the web with Claude and the
 * server-side web search tool, and returns the fields it could confirm.
 */
class VenueResearcher
{
    private const SCHEMA = [
        'type' => 'object',
        'properties' => [
            'next_edition' => ['type' => ['string', 'null'], 'description' => 'Dates and city of the next edition, e.g. "10–14 May 2027, Pittsburgh". Null if unknown.'],
            'deadline_on' => ['type' => ['string', 'null'], 'description' => 'Next full-paper submission deadline as YYYY-MM-DD. Null if not announced.'],
            'deadline_label' => ['type' => ['string', 'null'], 'description' => 'Short label for that deadline, e.g. "Full papers" or "Abstracts".'],
            'deadline_expected' => ['type' => 'boolean', 'description' => 'True if the deadline is an estimate from last year’s cycle rather than an official date.'],
            'website_url' => ['type' => ['string', 'null'], 'description' => 'Official URL of the next edition or its call for papers.'],
            'acceptance_rate' => ['type' => ['string', 'null'], 'description' => 'Recent acceptance rate if published, e.g. "~25%".'],
            'rank' => ['type' => ['string', 'null'], 'description' => 'CORE rank or standing, e.g. "CORE A*".'],
            'quartile' => ['type' => ['string', 'null'], 'description' => 'Journals only: best SJR quartile, Q1 to Q4.'],
            'first_decision' => ['type' => ['string', 'null'], 'description' => 'Journals only: time to first decision, e.g. "28 days (median)".'],
            'fee' => ['type' => ['string', 'null'], 'description' => 'Journals only: APC or "No fee (subscription)".'],
            'word_limit' => ['type' => ['string', 'null'], 'description' => 'Word limit for research articles, e.g. "8,000 incl. references".'],
            'review_type' => ['type' => ['string', 'null'], 'enum' => ['double', 'single', 'open', 'none', null], 'description' => 'Peer review type.'],
            'proceedings' => ['type' => ['string', 'null'], 'description' => 'Conferences only: where the proceedings are published.'],
            'notes' => ['type' => ['string', 'null'], 'description' => 'One or two sentences on anything else worth knowing (other tracks, format changes).'],
            'sources' => ['type' => 'array', 'items' => ['type' => 'string'], 'description' => 'URLs the answer is based on.'],
        ],
        'required' => ['next_edition', 'deadline_on', 'deadline_label', 'deadline_expected', 'website_url', 'acceptance_rate', 'rank',
            'quartile', 'first_decision', 'fee', 'word_limit', 'review_type', 'proceedings', 'notes', 'sources'],
        'additionalProperties' => false,
    ];

    /** Providers people can choose in Settings → AI. */
    public const PROVIDERS = [
        'claude-code' => 'Claude Code',
        'api' => 'Anthropic API',
        'openai' => 'OpenAI',
        'gemini' => 'Google Gemini',
        'compatible' => 'OpenAI-compatible',
    ];

    public static function driver(): string
    {
        $d = config('services.ai.driver', 'claude-code');

        return isset(self::PROVIDERS[$d]) ? $d : 'claude-code';
    }

    public static function configured(): bool
    {
        return match (self::driver()) {
            'api' => filled(config('services.anthropic.key')),
            'openai' => filled(config('services.openai.key')),
            'gemini' => filled(config('services.gemini.key')),
            'compatible' => filled(config('services.compatible.base_url')) && filled(config('services.compatible.model')),
            default => (bool) self::claudeBinary(),
        };
    }

    public static function status(): string
    {
        $name = self::PROVIDERS[self::driver()];
        $model = match (self::driver()) {
            'api' => config('services.anthropic.model'),
            'openai' => config('services.openai.model'),
            'gemini' => config('services.gemini.model'),
            'compatible' => config('services.compatible.model'),
            default => config('services.ai.claude_model'),
        };
        if (! self::configured()) {
            return $name.': not set up yet';
        }

        return $name.($model ? " ({$model})" : '');
    }

    /** The claude binary: CLAUDE_CODE_BIN, then PATH, then the copy bundled with the Claude desktop app. */
    public static function claudeBinary(): ?string
    {
        $candidates = array_filter([config('services.ai.claude_bin')]);
        foreach (['/opt/homebrew/bin/claude', '/usr/local/bin/claude', ($_SERVER['HOME'] ?? getenv('HOME')).'/.local/bin/claude'] as $p) {
            $candidates[] = $p;
        }
        $home = $_SERVER['HOME'] ?? getenv('HOME') ?: '/Users/'.get_current_user();
        $bundled = glob($home.'/Library/Application Support/Claude/claude-code/*/*/claude.app/Contents/MacOS/claude') ?: [];
        usort($bundled, fn ($a, $b) => version_compare(basename(dirname($b, 4)), basename(dirname($a, 4))));
        foreach ([...$candidates, ...$bundled] as $c) {
            if ($c && is_file($c) && is_executable($c)) {
                return $c;
            }
        }

        return null;
    }

    public function research(Venue $venue): array
    {
        if (! self::configured()) {
            throw new RuntimeException('AI updates are not set up: '.self::status().'.');
        }
        @set_time_limit(360);
        $prompt = $this->prompt($venue);
        return $this->clean($this->ask($prompt, self::SCHEMA));
    }

    /** A tiny request to check that the chosen driver can reach Claude. Returns the model's reply. */
    public function ping(): string
    {
        if (! self::configured()) {
            throw new RuntimeException('Not set up: '.self::status().'.');
        }
        if (in_array(self::driver(), ['openai', 'gemini', 'compatible'], true)) {
            $d = $this->ask('Return JSON with the field "reply" set to the word ready.',
                ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']], 'required' => ['reply'], 'additionalProperties' => false], 90, false);

            return (string) ($d['reply'] ?? 'ok');
        }
        if (self::driver() === 'api') {
            $client = new Client(apiKey: config('services.anthropic.key'));
            $res = $client->messages->create(model: config('services.anthropic.model'), maxTokens: 64,
                messages: [['role' => 'user', 'content' => 'Reply with the single word: ready']]);
            foreach ($res->content as $b) {
                if ($b->type === 'text') {
                    return trim($b->text);
                }
            }

            return 'ok';
        }
        $data = $this->viaClaudeCode('Reply with the single word ready in the field "reply".',
            ['type' => 'object', 'properties' => ['reply' => ['type' => 'string']], 'required' => ['reply'], 'additionalProperties' => false], 90, false);

        return (string) ($data['reply'] ?? 'ok');
    }

    /** Ask Claude (CLI or API) a web-research question and get JSON matching the schema. */
    public function ask(string $prompt, array $schema, int $timeout = 300, bool $web = true): array
    {
        if (! self::configured()) {
            throw new RuntimeException('AI is not set up: '.self::status().'.');
        }
        @set_time_limit($timeout + 60);

        return match (self::driver()) {
            'api' => $this->viaApi($prompt, $schema),
            'openai' => $this->viaOpenAI($prompt, $schema, $timeout, $web),
            'gemini' => $this->viaGemini($prompt, $schema, $timeout, $web),
            'compatible' => $this->viaCompatible($prompt, $schema, $timeout),
            default => $this->viaClaudeCode($prompt, $schema, $timeout, $web),
        };
    }

    /** OpenAI Responses API with the built-in web search tool and a strict JSON schema. */
    private function viaOpenAI(string $prompt, array $schema, int $timeout, bool $web): array
    {
        $body = ['model' => config('services.openai.model'), 'input' => $prompt,
            'text' => ['format' => ['type' => 'json_schema', 'name' => 'result', 'schema' => $schema, 'strict' => true]]];
        if ($web) {
            $body['tools'] = [['type' => 'web_search']];
        }
        $send = fn (array $b) => Http::withToken(config('services.openai.key'))->timeout($timeout)->acceptJson()->post('https://api.openai.com/v1/responses', $b);
        $res = $send($body);
        if ($res->status() === 400 && str_contains((string) $res->json('error.message'), 'schema')) {
            // Some models reject strict schemas; fall back to asking for JSON in the prompt.
            unset($body['text']);
            $body['input'] = $prompt."\n\nAnswer with one JSON object only, matching this JSON Schema:\n".json_encode($schema);
            $res = $send($body);
        }
        if ($res->failed()) {
            throw new RuntimeException('OpenAI: '.Str::limit($res->json('error.message') ?? $res->body(), 240));
        }
        $text = collect($res->json('output', []))->where('type', 'message')
            ->flatMap(fn ($m) => $m['content'] ?? [])->where('type', 'output_text')->pluck('text')->implode('');

        return $this->json($text);
    }

    /** Gemini generateContent with Google Search grounding; the schema goes in the prompt. */
    private function viaGemini(string $prompt, array $schema, int $timeout, bool $web): array
    {
        $body = ['contents' => [['role' => 'user', 'parts' => [['text' => $prompt."\n\nAnswer with one JSON object only, no prose, matching this JSON Schema:\n".json_encode($schema)]]]]];
        if ($web) {
            $body['tools'] = [['google_search' => (object) []]];
        } else {
            $body['generationConfig'] = ['responseMimeType' => 'application/json'];
        }
        $model = config('services.gemini.model');
        $res = Http::withHeaders(['x-goog-api-key' => config('services.gemini.key')])->timeout($timeout)->acceptJson()
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent", $body);
        if ($res->failed()) {
            throw new RuntimeException('Gemini: '.Str::limit($res->json('error.message') ?? $res->body(), 240));
        }
        $text = collect($res->json('candidates.0.content.parts', []))->pluck('text')->implode('');

        return $this->json($text);
    }

    /** Any OpenAI-compatible chat endpoint (OpenRouter, Groq, Perplexity, Ollama, LM Studio…). */
    private function viaCompatible(string $prompt, array $schema, int $timeout): array
    {
        $url = rtrim(config('services.compatible.base_url'), '/').'/chat/completions';
        $req = fn (bool $fmt) => Http::withToken((string) config('services.compatible.key'))->timeout($timeout)->acceptJson()->post($url, array_filter([
            'model' => config('services.compatible.model'),
            'messages' => [['role' => 'user', 'content' => $prompt."\n\nAnswer with one JSON object only, matching this JSON Schema:\n".json_encode($schema)]],
            'response_format' => $fmt ? ['type' => 'json_object'] : null,
        ]));
        $res = $req(true);
        if ($res->status() === 400) {
            $res = $req(false);
        }
        if ($res->failed()) {
            throw new RuntimeException('Provider: '.Str::limit($res->json('error.message') ?? $res->body(), 240));
        }

        return $this->json((string) $res->json('choices.0.message.content'));
    }

    /** Pull the first JSON object out of a model's text reply. */
    private function json(string $text): array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', trim($text)));
        $data = json_decode($text, true);
        if (! is_array($data) && ($i = strpos($text, '{')) !== false && ($j = strrpos($text, '}')) !== false) {
            $data = json_decode(substr($text, $i, $j - $i + 1), true);
        }
        if (! is_array($data)) {
            throw new RuntimeException('The answer could not be read. Try again.');
        }

        return $data;
    }

    private function prompt(Venue $venue): string
    {
        $today = now()->toDateString();
        $known = collect([
            'Field' => $venue->field,
            'Publisher' => $venue->publisher,
            'Next edition' => $venue->next_edition,
            'Deadline' => $venue->deadline_on?->toDateString(),
            'Website' => $venue->website_url,
        ])->filter()->map(fn ($v, $k) => "{$k}: {$v}")->implode("\n");

        $task = $venue->kind === 'journal'
            ? 'This is a journal. Find, from the publisher’s journal pages: its SJR (Scimago) best quartile, the median or average time to first decision, '
              .'the acceptance rate, the article processing charge or whether publishing is free, the peer review type and the word limit for research articles. '
              .'Leave the conference fields (next edition, deadline, proceedings) null.'
            : 'This is a conference. Find the next edition that has not happened yet: its dates and location, and its next submission deadline for full papers. '
              .'Also find where its proceedings are published, its CORE rank and a recent acceptance rate. '
              .'If the official date is not out yet, estimate it from last year’s cycle and mark it as expected. Leave journal-only fields null.';

        return "Today is {$today}. Look up the latest official information about the academic venue \"{$venue->name}\".\n"
            .($known ? "What is known so far:\n{$known}\n" : '')
            ."{$task}\nPrefer the venue’s own website, call for papers or publisher page. Do not invent anything: use null for what you cannot confirm, and list the URLs you used.";
    }

    /** Headless Claude Code with its WebSearch and WebFetch tools. */
    private function viaClaudeCode(string $prompt, array $schema, int $timeout, bool $web = true): array
    {
        $dir = storage_path('app/ai');
        is_dir($dir) || mkdir($dir, 0755, true);

        $env = ['HOME' => $_SERVER['HOME'] ?? getenv('HOME') ?: '/Users/'.get_current_user(), 'PATH' => '/usr/bin:/bin:/usr/sbin:/sbin:/opt/homebrew/bin'];
        if ($token = config('services.ai.claude_token')) {
            $env['CLAUDE_CODE_OAUTH_TOKEN'] = $token;
        }
        if (config('services.anthropic.key')) {
            $env['ANTHROPIC_API_KEY'] = config('services.anthropic.key');
        }

        $result = Process::path($dir)->env($env)->timeout($timeout)->run([
            self::claudeBinary(), '-p', $prompt,
            '--output-format', 'json',
            '--json-schema', json_encode($schema),
            '--tools', $web ? 'WebSearch,WebFetch' : '',
            ...($web ? ['--allowedTools', 'WebSearch', 'WebFetch'] : []),
            '--model', config('services.ai.claude_model'),
            '--no-session-persistence',
        ]);

        $out = json_decode($result->output(), true);
        if (! is_array($out)) {
            throw new RuntimeException('Claude Code did not answer: '.Str::limit(trim($result->errorOutput() ?: $result->output()), 200));
        }
        if (! empty($out['is_error'])) {
            $msg = (string) ($out['result'] ?? 'unknown error');
            if (str_contains($msg, 'authenticate') || str_contains($msg, 'login')) {
                throw new RuntimeException('Claude Code is not signed in for the app. In Terminal run “claude setup-token”, then paste the token into CLAUDE_CODE_OAUTH_TOKEN in the app’s .env file.');
            }
            throw new RuntimeException('Claude Code: '.Str::limit($msg, 240));
        }
        $data = $out['structured_output'] ?? json_decode(trim((string) ($out['result'] ?? '')), true);
        if (! is_array($data)) {
            throw new RuntimeException('The answer could not be read. Try again.');
        }

        return $data;
    }

    /** The Anthropic API with the server-side web search tool. */
    private function viaApi(string $prompt, array $schema): array
    {
        $client = new Client(apiKey: config('services.anthropic.key'));
        $messages = [['role' => 'user', 'content' => $prompt.' Answer only with the JSON object.']];

        $response = null;
        for ($turn = 0; $turn < 4; $turn++) {
            $response = $client->beta->messages->create(
                model: config('services.anthropic.model'),
                maxTokens: 16000,
                messages: $messages,
                tools: [['type' => 'web_search_20260209', 'name' => 'web_search', 'max_uses' => 10]],
                outputConfig: [
                    'effort' => 'low',
                    'format' => ['type' => 'json_schema', 'schema' => $schema],
                ],
                fallbacks: 'default',
                betas: ['server-side-fallback-2026-07-01'],
            );
            if ($response->stopReason !== 'pause_turn') {
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $response->content];
        }

        if ($response->stopReason === 'refusal') {
            throw new RuntimeException('The model declined this lookup.');
        }
        $text = '';
        foreach ($response->content as $block) {
            if ($block->type === 'text') {
                $text .= $block->text;
            }
        }
        $data = json_decode(trim($text), true);
        if (! is_array($data)) {
            throw new RuntimeException('The answer could not be read. Try again.');
        }

        return $data;
    }

    /** Validate the model's answer before it touches the database. */
    private function clean(array $d): array
    {
        $str = fn ($v, $max = 255) => is_string($v) && trim($v) !== '' ? mb_substr(trim($v), 0, $max) : null;
        $url = fn ($v) => is_string($v) && preg_match('#^https?://#i', $v) ? mb_substr($v, 0, 255) : null;
        $date = null;
        if (is_string($d['deadline_on'] ?? null) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d['deadline_on'])) {
            try {
                $date = Carbon::createFromFormat('Y-m-d', $d['deadline_on'])->toDateString();
            } catch (\Throwable) {
            }
        }

        return [
            'next_edition' => $str($d['next_edition'] ?? null),
            'deadline_on' => $date,
            'deadline_label' => $str($d['deadline_label'] ?? null, 80),
            'deadline_expected' => (bool) ($d['deadline_expected'] ?? false),
            'website_url' => $url($d['website_url'] ?? null),
            'acceptance_rate' => $str($d['acceptance_rate'] ?? null, 90),
            'rank' => $str($d['rank'] ?? null, 80),
            'quartile' => is_string($d['quartile'] ?? null) && preg_match('/^Q[1-4]$/', strtoupper(trim($d['quartile']))) ? strtoupper(trim($d['quartile'])) : null,
            'first_decision' => $str($d['first_decision'] ?? null, 80),
            'fee' => $str($d['fee'] ?? null, 120),
            'word_limit' => $str($d['word_limit'] ?? null, 120),
            'review_type' => in_array($d['review_type'] ?? null, ['double', 'single', 'open', 'none'], true) ? $d['review_type'] : null,
            'proceedings' => $str($d['proceedings'] ?? null, 160),
            'notes' => $str($d['notes'] ?? null, 600),
            'sources' => collect($d['sources'] ?? [])->map($url)->filter()->unique()->take(6)->values()->all(),
        ];
    }

    /** Apply a lookup to the venue, keeping existing values where the lookup found nothing. */
    public function apply(Venue $venue, array $found): Venue
    {
        $updates = collect($found)->except(['notes', 'sources'])->filter(fn ($v) => $v !== null)->all();
        $updates['deadline_expected'] = $found['deadline_expected'];
        if ($found['notes']) {
            $updates['notes'] = $found['notes'];
        }
        $venue->fill($updates);
        $venue->sources = $found['sources'];
        $venue->checked_at = now();
        $venue->save();

        return $venue;
    }
}
