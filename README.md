# PSOBB.io Website

The web frontend and API backend for a [Phantasy Star Online Blue Burst](https://en.wikipedia.org/wiki/Phantasy_Star_Online:_Blue_Burst) private server, powered by the [NewServ](https://github.com/fuzziqersoftware/newserv) emulator.

Powers the private server [psobb.io](https://psobb.io)

## Features

- **Real-Time Server Dashboard** — Live player counts, active games (with private/public indicators), and server statistics
- **AI Bounty Board** — Google Gemini-generated lore-rich missions that track progress in real-time via the game server API
- **Community Events** — Server-wide boss kill goals with milestone announcements and tiered rewards
- **Milestone Rewards** — Level-up reward system with procedurally generated items
- **Login Streaks & Daily Drops** — Daily login tracking with escalating rewards
- **Remote MAG Feeder** — Feed your MAG from the website while in-game (causes client disconnects, but will be fixed one day.)
- **Mod Manager** — Browse, upload, rate, and download game mods
- **Account Management** — Registration, password reset, Section ID changes, bank swaps, Discord linking
- **Admin Dashboard** — Server console, player management, mission/event management, live telemetry
- **Bilingual** — Full English and Japanese localization

## Prerequisites

| Requirement | Version | Notes |
|---|---|---|
| PHP | 8.0+ | See required modules below |
| SQLite3 | 3.x | Ships with PHP; no separate install needed |
| Apache or Nginx | Any | Apache needs `mod_rewrite` enabled |
| NewServ | Latest | The PSOBB game server — must be running locally |
| Gemini API Key | — | Optional; required only for AI mission generation |
| Discord OAuth App | — | Optional; required only for Discord account linking |

### Required PHP Modules

| Module | Package (Debian/Ubuntu) | Used For |
|---|---|---|
| `sqlite3` | `php-sqlite3` | Database (all user data, missions, streaks, mods) |
| `curl` | `php-curl` | Brevo email API, external HTTP requests |
| `json` | `php-json` | API request/response encoding (usually built-in) |
| `mbstring` | `php-mbstring` | UTF-16LE encoding for NewServ in-game mail packets |
| `gd` | `php-gd` | CAPTCHA image generation on the registration page |
| `session` | *(built-in)* | Secure session management and CSRF tokens |
| `fileinfo` | `php-fileinfo` | Mod file upload validation (usually built-in) |

**Install all required modules on Debian/Ubuntu:**

```bash
sudo apt update
sudo apt install php php-sqlite3 php-curl php-json php-mbstring php-gd php-fileinfo
sudo systemctl restart apache2
```

> **Note:** `php-json`, `php-fileinfo`, and `session` are typically bundled with PHP 8.0+ and may not need separate installation. The `apt install` command above is safe to run regardless — it will skip already-installed packages.

## Quick Start

### 1. Clone the repository

```bash
git clone https://github.com/your-username/psobb-website-public.git /var/www/html/psobb-website
cd /var/www/html/psobb-website
```

### 2. Run the setup script

```bash
sudo chmod +x setup_permissions.sh
sudo ./setup_permissions.sh
```

This will:
- Create required directories (`db/`, `uploads/mods/`)
- Copy `.env.example` → `.env` (if not already present)
- Set correct file ownership and permissions for `www-data`
- Initialize the SQLite database with all required tables
- Install 3 cron jobs for the mission engine, community events, and streak alerts

### 3. Configure your environment

Edit `.env` with your actual values:

```bash
sudo nano .env
```

| Variable | Required | Description |
|---|---|---|
| `NEWSERV_API_URL` | ✅ | Base URL to your NewServ HTTP API (e.g. `http://127.0.0.1:8443`) |
| `NEWSERV_COMMAND_PREFIX` | ✅ | Command prefix used in-game (e.g. `$`) |
| `DISCORD_CLIENT_ID` | Optional | Discord OAuth2 Client ID |
| `DISCORD_CLIENT_SECRET` | Optional | Discord OAuth2 Client Secret |
| `DISCORD_REDIRECT_URI` | Optional | Your callback URL (e.g. `https://yourdomain.com/api/discord_callback.php`) |
| `GEMINI_API_KEY` | Optional | Google AI API key for AI bounty generation |
| `BOT_API_SECRET` | Optional | Shared secret for Discord bot ↔ website communication |
| `BOT_TOKEN` | Optional | Discord bot token for DM streak alerts |
| `BREVO_API_KEY` | Optional | Brevo API key for transactional email; leave empty to use `mail()` |
| `SMTP_FROM` | Optional | Sender address for outgoing emails |

### 4. Configure your web server

**Apache** — Point your `DocumentRoot` to the project directory and ensure `mod_rewrite` is enabled:

```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

The included `.htaccess` files handle URL rewriting and protect the `db/` directory.

**Nginx** — Configure a location block equivalent. Ensure `db/` is not web-accessible.

### 5. Create your first admin

Before you can access the Admin Dashboard, you need to promote a registered user to ROOT administrator. This is done via the `promote_admin.php` CLI tool.

**Prerequisites:**
- The user must have already **registered an account** through the website
- **NewServ must be running** — the script sends a command to the game server API

**Usage:**

```bash
php promote_admin.php <username>
```

**Example:**

```bash
$ php promote_admin.php myusername
Creating/Promoting user 'myusername' to Administrator...
Found Account ID: 00000001 (Hex)
Executing: update-account 00000001 flags=ROOT
Server Response: Account updated.
SUCCESS: User 'myusername' is now a ROOT Administrator.
```

**What it does:**
1. Looks up the username in the local SQLite database (case-insensitive)
2. Retrieves their NewServ Account ID
3. Sends `update-account <hex_id> flags=ROOT` to NewServ's shell-exec API
4. The user's game server account is promoted to ROOT, which grants access to the Admin Dashboard (`/admin/dashboard.php`) on their next login

> **Note:** This script can only be run from the command line — it will refuse to execute if accessed via a web browser. The username lookup is case-insensitive, so `MyUser` and `myuser` will both match.

## Project Structure

```
├── api/                    # Backend PHP endpoints (JSON APIs + cron jobs)
│   ├── config.php          # Environment loading, session management, CSRF
│   ├── db.php              # Database provider with auto-migrations
│   ├── functions.php       # Shared utilities (item drops, mail, etc.)
│   ├── cron_missions.php   # AI bounty engine (runs every minute)
│   ├── cron_community.php  # Community event tracker (runs hourly)
│   ├── cron_streak_alert.php  # Discord DM alerts (runs daily at 11 PM)
│   └── ...                 # 30+ API endpoints
├── admin/                  # Admin dashboard and management tools
├── css/                    # Stylesheets and custom PSO fonts
├── js/                     # Frontend logic (main.js, unlocks.js, magfeeder.js)
├── db/                     # SQLite database and schema initializer
├── img/                    # Static assets (logos, section ID icons)
├── includes/               # Shared PHP templates (header, footer)
├── .env.example            # Environment template
├── setup_permissions.sh    # Automated server setup
├── promote_admin.php       # CLI admin promotion tool
└── DEVELOPER_GUIDE.md      # Detailed architecture documentation
```

## Cron Jobs

The setup script installs the following cron jobs under the `www-data` user:

| Schedule | Script | Purpose |
|---|---|---|
| Every minute | `cron_missions.php` | Polls online players, tracks mission progress, generates AI bounties |
| Every hour | `cron_community.php` | Updates community event progress and sends milestone announcements |
| Daily at 11 PM | `cron_streak_alert.php` | Sends Discord DMs to players whose login streaks are about to expire |

Verify installation with: `sudo -u www-data crontab -l`

## Integration with NewServ

This website communicates with the PSOBB game server via the NewServ HTTP API. Set `NEWSERV_API_URL` in your `.env` to point to your NewServ instance. The API is used for:

- **Authentication** — Player credentials are validated directly against the game server
- **Live Telemetry** — Player counts, levels, inventories, and game lobbies
- **Item Delivery** — Milestone rewards and bounty redemptions are dropped in-game via shell commands
- **Account Management** — Password changes, Section ID updates, and admin promotions

## Security

- All state-changing API endpoints enforce **CSRF token validation**
- Sessions use **HTTP-only cookies** with a dedicated save path
- Sensitive configuration is loaded from `.env` (never committed to git)
- The `db/` directory is protected from web access via `.htaccess`
- Admin pages require authenticated sessions with `is_admin` flag
- The bot API authenticates via `Authorization` header only

## Contributing

Feel free to open issues or submit pull requests. See [DEVELOPER_GUIDE.md](DEVELOPER_GUIDE.md) for the full architectural deep-dive.

## License

This project is provided as-is for the PSOBB community.
