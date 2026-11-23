# Installation and setup

How to install, configure and deploy the self-hosted web version of Manuscript Tracker. For the product overview, see the [README](../README.md). For tests and front-end builds, see [Development](DEVELOPMENT.md).

Prefer not to run a server? The free [desktop app](https://github.com/naimbh/manuscript-tracker-desktop) for macOS, Windows and Linux needs no installation steps beyond the download.

## Tech stack

- [Laravel 12](https://laravel.com) with [Livewire](https://livewire.laravel.com), [Volt](https://livewire.laravel.com/docs/volt) and [Flux UI](https://fluxui.dev)
- [Tailwind CSS 4](https://tailwindcss.com) and [Alpine.js](https://alpinejs.dev)
- MySQL (SQLite also works)
- Your choice of AI for the research features: Claude (Claude Code CLI or Anthropic API), OpenAI, Google Gemini, or any OpenAI-compatible provider
- [Laravel MCP](https://laravel.com/docs/mcp) for the agent tools

## Getting started

Requirements: PHP 8.2+, Composer, Node.js 20+ and MySQL 8 (or SQLite). [Laravel Herd](https://herd.laravel.com) is the quickest way to get all of them on macOS or Windows.

```bash
git clone https://github.com/naimbh/manuscript-tracker.git
cd manuscript-tracker
composer install
npm install && npm run build
cp .env.example .env
php artisan key:generate
```

Create a database, set `DB_CONNECTION`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`, then:

```bash
php artisan migrate --seed
php artisan storage:link
```

Open the app (for example `http://manuscripttracker.test` with Herd, or run `php artisan serve`) and sign in with the first account, **admin@gmail.com / 123456** unless you set `TRACKER_ADMIN_EMAIL` and `TRACKER_ADMIN_PASSWORD` in `.env` before seeding. Change the email and password under **Settings → Profile** straight away. Other people can register from the login page; each account starts with the starter list of journals and conferences and keeps its own papers.

Then open **Settings → Research profile** to add your Google Scholar link and research interests, and **Settings → AI** to choose an AI provider.

### Try it with demo data

```bash
php artisan db:seed --class=DemoSeeder
```

Then sign in as **demo@example.com / password**.

### Seeding your own papers

To seed your own papers without committing them, put a seeder in `database/seeders/Private/` (git ignores that folder). `DatabaseSeeder` runs `Private\MyPapersSeeder` and `Private\MyPublicationsSeeder` if they exist.

## AI features

The calls for papers scan, journal and conference updates, and citation updates need an AI model that can search the web. Choose one under **Settings → AI**, paste its key, and press **Test connection**:

| Provider | What you need | Web search |
|---|---|---|
| Claude Code | A Claude subscription and [Claude Code](https://docs.claude.com/en/docs/claude-code). Run `claude setup-token` and paste the token. | Built in |
| Anthropic API | An API key from the [Claude Console](https://console.anthropic.com) | Claude web search tool |
| OpenAI | An API key from [platform.openai.com](https://platform.openai.com) | Responses API web search |
| Google Gemini | A key from [Google AI Studio](https://aistudio.google.com) | Google Search grounding |
| OpenAI-compatible | A base URL, model and (if needed) key for OpenRouter, Groq, Perplexity, Together, Ollama or LM Studio | Depends on the model (for example OpenRouter `:online` models or Perplexity Sonar) |

Keys are stored encrypted with your app key. You can also set them in `.env` (see `.env.example`); values saved in Settings take priority.

The AI is asked to use official pages, to return `null` rather than guess, and to list its sources, which the app shows next to each journal and conference. Check important dates on the official site before you rely on them.

## Agent tools (MCP)

Manuscript Tracker includes a [Model Context Protocol](https://modelcontextprotocol.io) server, so AI assistants can work with your tracker. Tools: `list-papers`, `get-paper`, `add-paper`, `update-paper`, `record-submission`, `update-submission`, `record-decision`, `attach-file` (local server only), `list-deadlines`, `add-deadline`, `search-venues`, `list-opportunities` and `list-publications`.

**On the same computer** (Claude Desktop, Claude Code, Cursor): add a server that runs

```bash
php /path/to/manuscript-tracker/artisan mcp:start tracker
```

Settings → Agents shows the exact config to copy. The local server acts for the account in `TRACKER_MCP_USER`, or the first account.

**Over the web**: create an agent token under **Settings → Agents** and connect your client to `https://your-domain/mcp` with the header `Authorization: Bearer <token>`.

## Deploying to shared hosting (cPanel)

1. Clone the repo with cPanel → Git Version Control, outside `public_html`.
2. Run `composer install --no-dev --optimize-autoloader`, copy `.env.example` to `.env`, set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` and the database, then `php artisan key:generate --force` and `php artisan migrate --force`.
3. The built front end (`public/build`) is committed, so the server does not need Node.js. If you change the CSS or JavaScript, run `git config core.hooksPath .githooks` once; the pre-commit hook then rebuilds and commits `public/build` for you.
4. Point the domain's document root at `public/`. If you cannot, the root `.htaccess` sends every request into `public/`.
5. Add a cron job: `* * * * * cd /path/to/manuscript-tracker && php artisan schedule:run >> /dev/null 2>&1`.

## Schedules

| Job | When |
|---|---|
| Calls for papers scan | Every day at 6:00 (`TRACKER_SCAN_TIME`) |
| Journal and conference updates (watched ones) | Mondays at 7:00 (`TRACKER_VENUES_DAY`, `TRACKER_VENUES_TIME`) |
| Citation updates from Google Scholar | Wednesdays at 7:00 (`TRACKER_CITATIONS_DAY`, `TRACKER_CITATIONS_TIME`) |

Run the scheduler with `php artisan schedule:work`, or add the usual cron entry on a server:

```cron
* * * * * cd /path/to/manuscript-tracker && php artisan schedule:run >> /dev/null 2>&1
```

## Configuration

The first account is the site admin. Under **Settings → General** the admin sets the site name, tagline, description, timezone, contact email, footer credit, and whether new people can sign up (turn this off for a private site).

Everything specific to a person or a deployment is set in `.env` or in Settings, not in the code: the first account (`TRACKER_ADMIN_*`), schedules (`TRACKER_*_DAY`, `TRACKER_*_TIME`), the AI provider and keys, the MCP account (`TRACKER_MCP_USER`), and the footer credit (`TRACKER_CREDIT_NAME`, `TRACKER_CREDIT_URL`; leave empty to hide). Each user sets their own Scholar profile and interests.
