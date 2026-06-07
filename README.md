# psobb.io

The community website and web-services layer for **psobb.io**, a Phantasy Star
Online: Blue Burst (PSOBB) private server. The site lets players manage their
account, inspect their characters/bank, claim daily and streak rewards, run
AI-generated "bounty" missions, browse and submit client mods, find groups
(LFG), and link their Discord — all backed by a live [NewServ](https://github.com/fuzziqersoftware/newserv)
game-server instance and consumed by the companion
[`psobb-discord-bot`](../psobb-discord-bot).

> **Note on the repo layout:** the deployable site lives in the nested
> `psobb.io-website-public/` directory of this checkout. Paths in this document
> are relative to that directory.

---

## Architecture

```
                       ┌────────────────────────┐
   Browser  ◀────────▶ │  Frontend PHP pages     │   index.php, login.php,
   (vanilla JS)        │  + vanilla JS / CSS     │   stats.php, mods.php, …
                       └───────────┬────────────┘
                                   │ fetch() (session cookie + CSRF)
                       ┌───────────▼────────────┐
                       │  PHP API  (/api/*.php)  │   one file ≈ one endpoint
                       └─────┬───────────┬───────┘
              SQLite (db.php)│           │ HTTP REST
                       ┌─────▼─────┐ ┌───▼──────────────┐
                       │ website.db│ │ NewServ game srv │  /y/clients, /y/accounts,
                       │ (SQLite)  │ │ (C++ emulator)   │  /y/lobbies, /y/shell-exec …
                       └───────────┘ └──────────────────┘
                                   ▲
   Discord bot ────────────────────┘  Bearer-token API (api/bot_api.php)
   (psobb-discord-bot)

   Cron daemons (cron_*.php) + Google Gemini  →  generate missions / community
   events, track boss kills, send streak-expiry DMs.
```

| Layer | Tech | Notes |
|-------|------|-------|
| Frontend | HTML, vanilla JS, CSS (glassmorphism) | Server-rendered PHP pages in the project root |
| Backend | Vanilla PHP 8 (no framework) | Endpoints in `/api/`; one file per endpoint |
| Database | SQLite — `db/website.db` | Accounts, missions, streaks, mods, LFG, sessions, Discord links |
| Game server | **NewServ** (C++ PSOBB emulator) | Reached over HTTP at `NEWSERV_API_URL` (`/y/*` routes). The game's account list is the source of truth for credentials |
| AI | Google Gemini | Generates lore-friendly missions / community events (cron jobs) |
| Discord | `psobb-discord-bot` (Node.js) | Talks to `api/bot_api.php` with a Bearer token |

### Source of truth

Player **credentials live in NewServ**, not in SQLite. `login.php` validates the
username/password against NewServ's `/y/accounts`, then mirrors a minimal record
into the SQLite `users` table (creating it on first login — "self-healing"). The
SQLite row stores website-specific data: `discord_id`, language, mission
progress, streak/daily state, notification prefs.

---

## Authentication model

There are two completely separate auth schemes:

1. **Web users — session + CSRF.**
   - `login.php` populates `$_SESSION['user'] = ['username', 'account_id', …]`.
   - Sessions are stored in SQLite via a custom `SQLiteSessionHandler`
     (`config.php`), 30-day lifetime, `HttpOnly` cookie.
   - Every state-changing endpoint is `POST` and requires a CSRF token
     (`$_SESSION['csrf_token']`, validated by `verify_csrf_token()`), sent as the
     `csrf_token` POST field. Other fields are usually a JSON body on
     `php://input`.

2. **Bot / automation — Bearer token.** (`api/bot_api.php`, `telemetry_ingest.php`,
   `wiki_ingest.php`)
   - `Authorization: Bearer <token>`.
   - Verified two ways: a legacy shared secret (`BOT_API_SECRET`) **or** a
     bcrypt-hashed, revocable, expiring token from the `bot_tokens` table
     (managed via `admin_bot_tokens.php`).

Admin-only endpoints additionally check that the logged-in user is an
administrator before acting.

See [`api/API.md`](api/API.md) for the full endpoint reference.

---

## Configuration

All secrets come from a root `.env` file (one directory above the site root —
`__DIR__/../.env`), parsed by `loadEnv()` in `config.php`.

| Variable | Purpose |
|----------|---------|
| `NEWSERV_API_URL` | Base URL of the NewServ REST API (default `http://127.0.0.1:8443`) |
| `NEWSERV_COMMAND_PREFIX` | In-game command prefix, e.g. `$` |
| `BOT_API_SECRET` | Shared secret for the Discord bot API (legacy tier) |
| `BOT_TOKEN` | Discord bot token (used by `cron_streak_alert.php` to DM users) |
| `GEMINI_API_KEY` | Google Gemini key for AI mission/event generation |
| `DISCORD_CLIENT_ID` / `DISCORD_CLIENT_SECRET` / `DISCORD_REDIRECT_URI` | Discord OAuth2 |
| `BREVO_API_KEY` / `SMTP_FROM` | Transactional email (Brevo) |

---

## Directory layout

```
psobb.io-website-public/
├── *.php                 Frontend pages (index, login, stats, mods, missions, …)
├── api/                  Backend API endpoints (see api/API.md)
│   ├── bot_api.php        Discord-bot API (Bearer auth)
│   ├── config.php         Env loading, sessions, CSRF
│   ├── db.php             SQLite connection + auto-migration (get_db())
│   ├── functions.php      Shared helpers (mission text, item rendering, NewServ calls)
│   ├── lang.php           Localization strings + __()
│   ├── cron_*.php         Background daemons (missions, community, boss tracker, streak alerts)
│   ├── character_viewer.php  Save-file parser (.psochar/.psobank) for the dashboard
│   └── …
├── db/
│   ├── init_db.php        Base schema bootstrap → db/website.db
│   ├── migrate.php        Migration runner
│   └── website.db         SQLite database (not in VCS)
├── admin/                Admin dashboard pages
├── css/  js/  img/       Static assets
├── decryption/           PSO data-format / wiki decryption tooling
├── quest-editor/         Browser-based quest editor
├── DEVELOPER_GUIDE.md    High-level architecture narrative
└── README.md             This file
```

---

## Local development

The site targets **PHP 8** with the `sqlite3`, `mbstring`, `iconv`, `openssl`,
and `json` extensions.

1. Create the database schema:
   ```bash
   php db/init_db.php          # creates db/website.db
   ```
2. Create a `.env` one level above the site root with at least
   `NEWSERV_API_URL` and `BOT_API_SECRET` (see table above).
3. Serve the site:
   ```bash
   php -S localhost:8000       # or point Apache/nginx at the site root
   ```

> Without a reachable NewServ instance, endpoints that read live game state
> (`/y/clients`, `/y/accounts`, …) degrade gracefully — calls are wrapped in
> `@file_get_contents` and fall back to empty/offline data rather than erroring.

### Linting

```bash
php -l api/bot_api.php       # syntax-check any endpoint before deploying
```

---

## The Discord bot integration

`api/bot_api.php` is the single endpoint the [`psobb-discord-bot`](../psobb-discord-bot)
calls (with `?action=…`). It exposes:

| Action | Method | Purpose |
|--------|--------|---------|
| `link` | POST | Link a website username to a Discord ID |
| `get_player` | GET | Full player profile: all 20 character slots (live + save-file), bank, missions |
| `get_online_players` | GET | Currently-online clients whose accounts are linked to a Discord ID (drives role sync) |
| `get_events` | GET | Active community events |

The bot uses `get_online_players` + `get_player` to mirror in-game class /
level / Section ID into Discord roles. See [`api/API.md`](api/API.md#discord-bot-api-botapiphp)
for request/response details.

---

## Background jobs (cron)

| Script | Role |
|--------|------|
| `api/cron_missions.php` | Generates and tracks per-player AI "bounty" missions |
| `api/cron_community.php` | Drives server-wide community events + AI milestones |
| `api/cron_boss_tracker.php` | Watches live clients for boss kills |
| `api/cron_streak_alert.php` | DMs players (via the bot token) before a login streak expires |

These read live state from NewServ (`/y/clients`, `/y/lobbies`) and persist
progress to SQLite.
