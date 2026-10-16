# Review Responder

Review Responder helps a local business keep up with its Google reviews. It fetches new reviews for the Google Business Profile locations you choose to monitor, drafts a reply to each one overnight, and leaves the drafts in a morning queue where you edit, approve and post them. Nothing is posted until you approve it.

**Live demo:** not deployed yet.

## Status

This is an early build. Working so far:

- Sign-in with Google (OAuth 2.0 with the Business Profile scope), or a "Try the demo" sign-in that creates a private, throwaway account
- Database schema for users, Google accounts, locations, reply guidelines, reviews, drafts and API usage logs
- Per-user access control on locations, reviews and drafts, plus an admin role
- Locations page: refresh locations from Google, switch monitoring on or off, and set each location's timezone
- Nightly review sync: each monitored location is checked at about 2am in its own timezone, new reviews are stored, and a reply draft with sentiment and themes is generated for each one
- Demo accounts get three sample bakery locations with sample reviews and drafts straight away, labelled "Sample data"
- Account deletion and an admin health page

The review queue (edit, approve and post, dismiss, regenerate) and the morning summary email are being built next.

## Stack

| Part | Technology |
|---|---|
| `api/` | Laravel 13 (PHP 8.4), Laravel Sanctum bearer tokens, MySQL 8, database queue and cache |
| `web/` | React 19, Vite, Tailwind CSS 4, React Router, axios — plain JavaScript/JSX |

## Local setup

You need PHP 8.3+ (with `pdo_mysql`, `mbstring`, `openssl`), Composer, MySQL 8 and Node 20+.

### 1. Database

As a MySQL user that can create databases and users:

```sql
CREATE DATABASE review_responder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE review_responder_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'review_responder'@'127.0.0.1' IDENTIFIED BY 'choose-a-password';
GRANT ALL PRIVILEGES ON review_responder.* TO 'review_responder'@'127.0.0.1';
GRANT ALL PRIVILEGES ON review_responder_test.* TO 'review_responder'@'127.0.0.1';
```

### 2. API

```bash
cd api
composer install
cp .env.example .env        # then set DB_PASSWORD
php artisan key:generate
php artisan migrate
php artisan serve --port=8472
```

To give an account the admin role (there are no built-in admin credentials):

```bash
php artisan user:promote owner@example.com
```

### 3. Web app

```bash
cd web
npm install
cp .env.example .env.local
npm run dev                 # http://localhost:5472
```

### Tests and checks

```bash
cd api && php artisan test && ./vendor/bin/pint --test
cd web && npm run lint && npm run build
```

## Environment variables

### `api/.env`

| Variable | Purpose |
|---|---|
| `DB_*` | MySQL connection |
| `FRONTEND_URL` | Origin(s) of the web app, comma-separated. Used for CORS; the first one receives the Google sign-in redirect |
| `SANCTUM_EXPIRATION` | API token lifetime in minutes (default 7 days) |
| `GOOGLE_DRIVER` | `demo` (labelled sample locations and reviews) or `real` (Google Business Profile APIs) |
| `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI` | Google OAuth client. Google sign-in is offered only when all three are set. The redirect URI is this API's `/api/auth/google/callback` and must be registered in the Google Cloud console |
| `DEMO_LOGIN_ENABLED` | Show "Try the demo" (default `true`) |
| `LLM_BASE_URL`, `LLM_API_KEY`, `LLM_MODEL` | Any OpenAI-compatible chat completions endpoint for drafting replies (defaults to OpenRouter and a free model). Without a key, drafts come from fixed star-based templates and are labelled as template drafts |
| `CRON_SECRET` | Shared secret for `POST /api/cron/run` (see below). Empty disables the endpoint |
| `MAIL_*` | Outgoing mail for the morning summary. `MAIL_MAILER=log` writes emails to the log instead of sending them |

The Business Profile scope (`business.manage`) is a restricted Google scope: until Google approves your project, only test users you add in the Google Cloud console can sign in.

### `web/.env.local`

| Variable | Purpose |
|---|---|
| `VITE_API_URL` | Base URL of the API, including `/api` |

## Scheduling

Review syncing runs on the Laravel scheduler and the database queue:

```bash
php artisan schedule:work      # or a cron entry: * * * * * php artisan schedule:run
php artisan queue:work
```

`reviews:sync` runs hourly. Each run queues only monitored locations where the local time is between 02:00 and 02:59 and that have not been synced that local day. A sync fetches reviews from the last 24 hours, or back to the start of the last synced day if nights were missed (at most 30 days), skips reviews already answered on Google, and queues one draft per new review. For a manual run: `php artisan reviews:sync --force` (all monitored locations) or `--location=ID`.

On hosts without cron, the `Review sync` GitHub Actions workflow (`.github/workflows/sync-cron.yml`) calls `POST /api/cron/run` hourly with `Authorization: Bearer $CRON_SECRET`, which runs `reviews:sync` and then works the queue until it is empty. Set the repository variable `API_URL` and secret `CRON_SECRET` to enable it.

## Security

Google refresh and access tokens are encrypted at rest and never sent to the browser. Sign-in finishes with a single-use code bound to the browser tab that started it, so no token ever appears in a URL. See [SECURITY.md](SECURITY.md) to report a vulnerability.

## License

Free for personal, educational and other noncommercial use under the [PolyForm Noncommercial License 1.0.0](LICENSE).

## Commercial use

Using Review Responder for commercial purposes requires a paid commercial license. Contact fazalabbas2002@gmail.com.
