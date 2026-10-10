# FACT

**FACT (Public Evidence Verification Engine)** helps readers inspect factual claims in public online content. It extracts available text, identifies checkable claims, researches supporting and contradicting sources, and publishes a shareable report with the evidence and limitations visible.

FACT evaluates claims and evidence. It does not label or judge the person who published the content. A missing source is not treated as proof that a claim is false.

**Live application:** [dr.ales.work](https://dr.ales.work)

## What it does

- Accepts public HTTP(S) content URLs and, where available, retrieves article text or social video metadata and transcripts.
- Supports YouTube, Instagram, Facebook, TikTok and X through the optional Supadata integration. Provider access and transcript availability depend on the platform, video and provider plan; FACT reports retrieval limits instead of inventing content.
- Uses an OpenAI Responses API adapter with web search to research extracted claims and retain visible source links.
- Produces a public report with claim classifications, evidence relationships, a deterministic evidence score and retrieval limitations.
- Tracks analysis stages through a database-backed queue and API polling. Reports use unguessable public identifiers and expire according to the configured retention period.
- Provides English, Turkish, Azerbaijani, Russian, Spanish and Arabic interfaces, including right-to-left Arabic layout.
- Starts with a seedable official-source registry for Türkiye and Azerbaijan, alongside globally configured institutions and independent publishers. The registry is intended to be extended and is not exhaustive.

## How an analysis works

1. The request URL is normalized and checked against the public URL policy. Private and reserved network addresses, unsupported schemes, unsafe ports and untrusted redirects are blocked. Connections are pinned to validated DNS results.
2. FACT extracts the text available from the page or configured transcript provider. HTML is reduced to text; retrieved material is treated as untrusted input. If a platform blocks retrieval or has no accessible transcript, the analysis records that limitation.
3. The configured analysis provider selects a bounded set of verifiable claims and researches them using web search.
4. Search citations are checked against the returned source list and configured domain policy. The deterministic scorer evaluates distinct publishers and evidence relationships; the score is an index, not a probability.
5. The result and its sources are saved and exposed through a shareable report URL.

Search results and AI-generated explanations are not proof by themselves. FACT does not independently verify video frames, audio, the identity of a speaker, or the authenticity of a media file. Platform availability, transcript access, search coverage and source registries all impose limits on a report.

## Technology

- PHP 8.3+ and Laravel 13
- Blade, Vite, Tailwind CSS 4 and a small browser script
- SQLite, MySQL or PostgreSQL through Laravel's database layer
- Database queue and Laravel scheduler; Redis is optional
- OpenAI Responses API and web search (optional real-analysis provider)
- Supadata (optional social metadata and transcript provider)

## Requirements

- PHP 8.3 or newer with `curl`, `dom`, `mbstring`, `openssl`, `pdo_sqlite` (or the PDO driver for your database); `intl` is recommended for internationalized domains
- Composer 2
- Node.js 20.19+ or 22.12+ and npm
- SQLite for a local setup, or a configured MySQL/PostgreSQL database
- A persistent queue worker and a scheduler invocation every minute for production

## Local setup

Clone the repository, then install dependencies and prepare the environment:

```sh
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
npm ci
npm run build
```

On Windows PowerShell, replace the `cp` command with:

```powershell
Copy-Item .env.example .env
```

Start the application and its queue worker in separate terminals:

```sh
php artisan serve
```

```sh
php artisan queue:work database --sleep=3 --tries=1 --timeout=240
```

Open the local URL printed by `artisan serve`. The default analysis and content drivers are `mock`; this mode is a clearly marked local preview and does not make paid AI or search requests.

## Optional live providers

Keep provider keys in the untracked `.env` file or a production secret manager. Never commit them or paste them into source code.

### OpenAI analysis and web research

```dotenv
FACT_ANALYSIS_DRIVER=openai
FACT_CONTENT_DRIVER=safe
OPENAI_API_KEY=your-key
OPENAI_MODEL=gpt-6-luna
OPENAI_BASE_URL=https://api.openai.com/v1
OPENAI_TIMEOUT=90
```

Real analysis can incur API charges. The model is configurable; confirm availability for your API account before use.

### Social video metadata and transcripts

```dotenv
SUPADATA_API_KEY=your-key
```

The integration uses Supadata for supported social URLs. Transcript retrieval is not guaranteed for every video. YouTube's official captions API does not generally allow downloading captions from videos you do not own; availability depends on the provider and the video's accessible captions. Without a transcript, FACT does not substitute a description and claim it analyzed the spoken content.

## Configuration reference

Application defaults and limits are defined in `.env.example` and `config/fact.php`.

| Variable | Purpose |
| --- | --- |
| `FACT_ANALYSIS_DRIVER` | `mock` for local preview or `openai` for real analysis |
| `FACT_CONTENT_DRIVER` | `mock` for preview or `safe` for guarded public-page retrieval |
| `OPENAI_API_KEY` | Server-side OpenAI API key; never expose it to the browser |
| `OPENAI_MODEL` | Responses API model, default `gpt-6-luna` |
| `SUPADATA_API_KEY` | Optional server-side social metadata/transcript provider key |
| `FACT_MAX_INPUT_CHARS` | Maximum extracted text length sent for analysis |
| `FACT_MAX_CLAIMS` | Maximum claims selected per analysis |
| `FACT_MAX_SOURCES` | Maximum sources retained per analysis |
| `FACT_RETENTION_DAYS` | Public report retention period |
| `FACT_TRUSTED_PROXIES` | Explicit trusted reverse-proxy IP addresses; do not set to `*` on a public backend |
| `DB_QUEUE_RETRY_AFTER` | Queue reservation timeout; keep longer than the worker job timeout |

Use the standard Laravel `DB_*`, `QUEUE_CONNECTION`, `CACHE_STORE` and `SESSION_*` settings for your deployment. Secure cookies should be enabled when serving over HTTPS.

## HTTP endpoints

All analysis endpoints return JSON except the report route.

| Method | Path | Description |
| --- | --- | --- |
| `POST` | `/api/analyses` | Validate a URL and queue an analysis. Accepts `url`, `locale`, and optional `refresh`, `include_perspectives`, and user-supplied `transcript` fields. |
| `GET` | `/api/analyses/{publicId}` | Read persisted status, stage and completed result for polling. |
| `GET` | `/r/{publicId}` | Render the public, shareable report. |

Creation and status endpoints are rate limited. Browser submissions are protected by Laravel CSRF middleware.

## Production deployment

1. Use a supported PHP 8.3+ runtime, required PHP extensions and a persistent database. Run migrations as part of a controlled deployment.
2. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, database credentials, API keys and secure session settings through the host's secret manager or a protected environment file outside the public web root.
3. Build frontend assets with `npm ci && npm run build`. Point the web server document root at Laravel's `public` directory; do not expose the application root or `.env`.
4. Run a supervised worker, for example `php artisan queue:work database --sleep=3 --tries=1 --timeout=240`, with `DB_QUEUE_RETRY_AFTER` longer than the job timeout.
5. Invoke `php artisan schedule:run` every minute. Back up the database and configure retention appropriate for your deployment.
6. Use SFTP or FTPS for file transfers. Rotate any credential previously sent over plain FTP.
7. Apply outbound network restrictions to the retrieval worker in addition to the application's URL and DNS checks.

The application has been deployed at [dr.ales.work](https://dr.ales.work). Hosting configuration and secrets are not included in this repository.

## Tests and checks

The PHPUnit suite covers URL and SSRF boundaries, redirects, content extraction, scoring, localization, rate limits, queue workflows, shareable reports, escaping and provider fakes. External providers should be faked in tests; do not use paid API calls in the test suite.

```sh
php artisan test
vendor/bin/pint --test
npm run build
```

## Security

Please do not report vulnerabilities in public issues. See [SECURITY.md](SECURITY.md) for the private reporting process. Never include API keys, `.env` files, FTP credentials, database snapshots or production logs in a report.

## License

FACT is licensed under the [MIT License](LICENSE). Copyright (c) 2026 alesmcgregor.
