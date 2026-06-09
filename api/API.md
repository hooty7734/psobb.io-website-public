# psobb.io API Reference

Backend HTTP API for the psobb.io website. Every endpoint is a standalone PHP
file under `/api/` and responds with `Content-Type: application/json` (a few
return binary/image data — noted where relevant).

- [Conventions](#conventions)
- [Authentication & sessions](#authentication--sessions)
- [Discord bot API (`bot_api.php`)](#discord-bot-api-botapiphp)
- [Account & auth endpoints](#account--auth-endpoints)
- [Discord linking](#discord-linking)
- [Characters, bank & game actions](#characters-bank--game-actions)
- [Missions & community events](#missions--community-events)
- [Daily logins & streaks](#daily-logins--streaks)
- [Looking-for-group (LFG)](#looking-for-group-lfg)
- [Mods](#mods)
- [Info & leaderboards](#info--leaderboards)
- [Notification preferences](#notification-preferences)
- [Admin endpoints](#admin-endpoints)
- [Automation ingest endpoints](#automation-ingest-endpoints)
- [NewServ game-server interface](#newserv-game-server-interface)
- [Database schema](#database-schema)
- [Cron daemons](#cron-daemons)

---

## Conventions

**Base path:** `https://psobb.io/api/`

**Auth types** (see [Authentication](#authentication--sessions)):

| Tag | Meaning |
|-----|---------|
| 🔓 Public | No auth |
| 🔑 Session | Requires a logged-in web session (`$_SESSION['user']`) |
| 🛡️ Session + CSRF | Logged-in **and** a valid `csrf_token` (state-changing `POST`) |
| 👑 Admin | Logged-in admin user |
| 🤖 Bearer | `Authorization: Bearer <token>` (bot/automation) |

**Request bodies.** State-changing endpoints take the CSRF token as a `csrf_token`
**form field** (`application/x-www-form-urlencoded` / `multipart`), while the
remaining payload is typically a **JSON body** read from `php://input`. Read
endpoints take query-string parameters.

**Error envelope.** Errors are JSON. Most endpoints use one of:

```json
{ "error": "Human-readable message" }
{ "success": false, "error": "Human-readable message" }
```

Common status codes: `200` (incl. handled errors with `{"error":…}`), `403`
(bad/missing CSRF or unauthorized), `500` (server fault — `bot_api.php` returns a
structured `{"error":"PHP fatal"|"PHP exception", "message", "file", "line"}`).

---

## Authentication & sessions

- **Login** validates credentials against NewServ's account list, not SQLite, then
  stores `$_SESSION['user'] = ['username', 'account_id', …]`.
- Sessions persist in the SQLite `sessions` table (`SQLiteSessionHandler` in
  `config.php`), 30-day `HttpOnly` cookie.
- A 32-byte `csrf_token` is generated per session; clients echo it back on every
  mutation. `verify_csrf_token()` returns `403` on mismatch.
- **Bot/automation** auth is separate: a Bearer token matched against either the
  `BOT_API_SECRET` env value or a bcrypt hash in the `bot_tokens` table
  (revocable, optional expiry).

---

## Discord bot API (`bot_api.php`)

Single endpoint, dispatched by `?action=`. **Auth: 🤖 Bearer** for all actions.

```
GET/POST /api/bot_api.php?action=<action>&<params>
Authorization: Bearer <BOT_API_SECRET or bot_tokens token>
```

If unauthorized → `403 {"error":"Unauthorized"}`.
Fatals/exceptions are serialized (see [error envelope](#conventions)) so the bot
never receives an opaque empty `500`.

### `action=link` — link a website account to a Discord ID
`POST` · form/body params:

| Param | Required | Description |
|-------|----------|-------------|
| `username` | yes | Website username |
| `discord_id` | yes | Discord user ID to attach |

Response:
```json
{ "success": true }                                  // linked
{ "error": "User not found or already linked" }      // no row updated
{ "error": "Missing data" }                          // missing param
```

### `action=get_player` — full player profile
`GET` · params: `discord_id` (required).

Looks up the `users` row by `discord_id`. If none → `{"error":"Not linked"}`.
Otherwise resolves the Blue Burst username, overlays **live** client data from
NewServ `/y/clients` (when online) on top of **save-file** data parsed from all
20 `player_<user>_<slot>.psochar` slots, and merges website stats.

Response (abridged):
```json
{
  "website_username": "player1",
  "account_id": 1234,
  "language": "en",
  "is_online": true,
  "account": { "guild_card": 1234, "is_shared_bank_enabled": false },
  "shared_bank": { "meseta": 0, "item_count": 0 },
  "characters": [
    {
      "slot": 0, "exists": true,
      "name": "Hunter", "class": "HUmar", "level": 142,
      "section_id": "Viridia", "experience": 80100000,
      "play_time_hours": 51.2, "is_online": true,
      "stats": { "ATP": 700, "DFP": 350, "MST": 50, "ATA": 200,
                 "EVP": 400, "LCK": 100, "HP": 1200, "Meseta": 999999 },
      "mats": { "HP": 0, "TP": 0, "Power": 250, "Mind": 0,
                "Evade": 0, "Def": 0, "Luck": 0 },
      "inventory": [ { "name": "Red Saber +9", "equipped": true, "attrs": [...] } ],
      "bank_meseta": 12345,
      "quest_progress": { "Normal": { "Forest": true, … }, "Hard": { … }, … }
    },
    { "slot": 1, "exists": false }
  ],
  "website_stats": {
    "total_login_days": 37,
    "missions": [ { "title": "...", "goal_type": "LEVEL", "goal_target": 50,
                    "status": "in_progress", "friendly_objective": "Reach Level 50" } ]
  }
}
```

Empty slots are returned as `{ "slot": N, "exists": false }`; a slot whose file
cannot be parsed adds `"error": "parse_failed"`.

### `action=get_online_players` — linked players currently online
`GET` · no params.

Fetches NewServ `/y/clients`, joins against linked `users.discord_id`, and
returns only online clients that belong to a linked account. Returns `[]` when
NewServ is unreachable or nobody linked is online.

```json
[ { "account_id": 1234, "discord_id": "335974112046350341", "name": "Hunter" } ]
```

This drives the bot's role-sync "Path A" (mirror in-game class/level/Section ID
into Discord roles).

### `action=get_linked_players` — all linked accounts
`GET` · no params.

Returns every `users` row that has a non-empty `discord_id`, regardless of
online status. Drives the bot's admin `!sync all` command so it can force-sync
everyone who linked on the website — not just players seen online.

```json
[ { "account_id": 1234, "discord_id": "335974112046350341", "username": "player1" } ]
```

### `action=get_lfg` — recent Looking-For-Group posts
`GET` · optional param: `since_id` (only return posts with `id` greater than this).

Returns LFG posts from the **last 2 hours**, newest-id last, with the same
enrichment as the website's `lfg_requests.php` GET (bounty title/reward join,
`game_mode` parsed from the `E`/`B`/`C` name prefix) **plus** the poster's
`discord_id`. Unlike the website endpoint it is **not** session-gated and text is
returned **raw** (not HTML-escaped) for Discord. `latest_id` is the current max
`id` so a poller can seed its cursor without replaying a backlog. Drives the
bot's LFG announcer (poll with `since_id` = last announced id).

```json
{
  "success": true,
  "latest_id": 482,
  "listings": [
    {
      "id": 482, "account_id": 1234, "discord_id": "335974112046350341",
      "character_name": "RedRanger", "class": "RAcast", "level": 142,
      "section_id": "Redria", "game_id": 7, "game_name": "Ruins Run",
      "game_mode": "Normal", "looking_for": "HU,FO", "description": "need DPS",
      "bounty_id": 9, "bounty_title": "Hardcore Mentor",
      "bounty_reward": "PsychoWand x1", "created_at": "2026-06-07 10:08:05"
    }
  ]
}
```

### `action=get_lfg` companion — `action=get_parties` — live multiplayer instances
`GET` · no params.

Joins NewServ `/y/lobbies` (entries where `IsGame`) → each lobby's `ClientIDs`
→ `/y/clients`, and resolves each player's linked `discord_id` from `users`.
Returns every active multiplayer game instance with its full roster, so the bot
can create per-party private voice channels and @mention the members. Returns
`{"success":false,"parties":[]}` if the game server is unreachable.

```json
{
  "success": true,
  "parties": [
    {
      "game_id": 7, "name": "Ruins Run", "mode": "Normal",
      "episode": "Ep1", "difficulty": "Ultimate", "section_id": "Redria",
      "max_clients": 4, "has_password": false,
      "players": [
        { "account_id": 1234, "discord_id": "335974112046350341",
          "character_name": "RedRanger", "level": 142, "class": "RAcast",
          "section_id": "Redria" },
        { "account_id": 5678, "discord_id": null,
          "character_name": "Guest", "level": 88, "class": "HUcast",
          "section_id": "Viridia" }
      ]
    }
  ]
}
```

### `action=get_events` — active community events
`GET` · no params. Returns active rows from `community_events` with rendered
objective/reward text.

```json
[ { "id": 1, "title": "...", "goalType": "...", "targetAmount": 1000,
    "currentProgress": 240, "friendly_objective": "...", "friendly_reward": "...",
    "status": "active" } ]
```

---

## Account & auth endpoints

| Endpoint | Method | Auth | Params | Purpose |
|----------|--------|------|--------|---------|
| `login.php` | POST | 🔓 | JSON `username`, `password` | Validate vs NewServ accounts, start session, self-heal `users` row |
| `register.php` | POST | 🔓 | JSON `username`, `password`, `email` | Create website + NewServ account |
| `delete_account.php` | POST | 🛡️ | `csrf_token` | Delete the logged-in user's account |
| `change_password.php` | POST | 🛡️ | `csrf_token`, password fields | Change password (synced to NewServ) |
| `forgot_password.php` | POST | 🔓 | email/username | Email a reset token |
| `reset_password.php` | POST | 🔓 | token, new password | Complete a password reset |
| `captcha.php` | GET | 🔓 | — | Render a CAPTCHA image into the session |

---

## Discord linking

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `discord_auth.php` | GET | 🔑 | Begin Discord OAuth2 (sets `$_SESSION['discord_state']`, redirects) |
| `discord_callback.php` | GET | 🔑 | OAuth2 callback; writes `users.discord_id` |
| `discord_unlink.php` | POST | 🔑 | Remove the Discord link |

---

## Characters, bank & game actions

| Endpoint | Method | Auth | Params | Purpose |
|----------|--------|------|--------|---------|
| `character_viewer.php` | GET | 🔑 | `slot` | Parse and return a character + bank from save files |
| `account_data.php` | GET | 🔑 | — | Account-level dashboard data |
| `download_character.php` | GET | 🔑 | — | Download character save data as a ZIP |
| `switch_character.php` | POST | 🛡️ | `csrf_token`, JSON `slot` | Set the active character slot |
| `change_section_id.php` | POST | 🛡️ | `csrf_token`, JSON `character_name`, `new_section_id` | Change a character's Section ID |
| `bank_swap.php` | POST | 🛡️ | `csrf_token` | Move items between inventory and bank |
| `mag_feed.php` | POST | 🛡️ | `csrf_token`, JSON `mag_item_id`, `feed_item_id` | Feed an item to a Mag |
| `mag_inventory.php` | GET | 🔑 | — | List Mags / feedable items |
| `reset_materials.php` | POST | 🛡️ | `csrf_token` | Recalibrate / reset stat materials |
| `send_chat_message.php` | POST | 🛡️ | `csrf_token`, JSON `character_name`, `message` | Send an in-game chat message (web → game) |
| `get_display_name.php` | GET | 🔑 | — | Get the leaderboard alias |
| `set_display_name.php` | POST | 🛡️ | `csrf_token` | Set the leaderboard alias |

These actions reach NewServ over its `/y/*` routes (often via `/y/shell-exec`).

---

## Missions & community events

| Endpoint | Method | Auth | Params | Purpose |
|----------|--------|------|--------|---------|
| `my_bounties.php` | GET | 🔑 | — | The user's active bounty missions + community events |
| `my_bounties_all.php` | GET | 🔑 | — | All of the user's bounties (dashboard portal) |
| `redeem_bounty.php` | POST | 🛡️ | `csrf_token`, JSON `player_mission_id` | Claim a completed bounty's reward |
| `abandon_bounty.php` | POST | 🛡️ | `csrf_token` | Abandon an active bounty |
| `bounty_check.php` | GET | 🔑 | — | Lightweight change-detection poll for the UI |
| `get_events.php` | GET | 🔓 | `status` | List community events (default active) |
| `redeem_community.php` | POST | 🛡️ | `csrf_token` | Claim a community-event reward |
| `manage_events.php` (root) | — | 👑 | — | Admin event management page |

---

## Daily logins & streaks

| Endpoint | Method | Auth | Params | Purpose |
|----------|--------|------|--------|---------|
| `claim_daily.php` | POST | 🛡️ | `csrf_token` | Claim the daily reward |
| `get_streak.php` | GET | 🔑 | — | Current login-streak status |
| `claim_streak.php` | POST | 🛡️ | `csrf_token`, JSON `milestone` | Claim a streak milestone reward |

---

## Looking-for-group (LFG)

| Endpoint | Method | Auth | Params | Purpose |
|----------|--------|------|--------|---------|
| `lfg_games.php` | GET | 🔑 | — | Unified registry of active games/lobbies |
| `lfg_requests.php` | POST | 🛡️ | `csrf_token` | Create/manage LFG requests |
| `lfg_join.php` | POST | 🛡️ | `csrf_token`, JSON `lobby_id` | Browser-to-game join warp |
| `lfg_leave.php` | POST | 🛡️ | `csrf_token` | Leave the current group |
| `get_lobby_feed.php` | GET | 🔑 | — | Live lobby feed |

---

## Mods

| Endpoint | Method | Auth | Params | Purpose |
|----------|--------|------|--------|---------|
| `get_mods.php` | GET | 🔓 | `category` | List approved mods in a category |
| `submit_mod.php` | POST | 🛡️ | `csrf_token`, `name`, `author`, `version`, `description`, `purpose`, `category`, file upload | Submit a mod for review |
| `rate_mod.php` | POST | 🛡️ | `csrf_token` | Rate a mod (1–5) |
| `get_unlocks.php` | GET | 🔑 | — | Unlockable content list |
| `claim_unlock.php` | POST | 🛡️ | `csrf_token` | Claim an unlock |

---

## Info & leaderboards

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `summary.php` | GET | 🔓 | Server summary (population, status) from NewServ `/y/summary` |
| `server.php` | GET | 🔓 | Server status |
| `get_drops.php` | GET | 🔓 | Drop-table data (consumed by the bot's drops cache) |
| `team_info.php` | GET | 🔑 | Team/guild info |
| `event_roster.php` | GET | 🔓 | Curated 6-month community boss-rush roster |
| `set_lang.php` | GET | 🔑 | Set the session UI language |

---

## Notification preferences

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `toggle_system_mail.php` | POST | 🛡️ | Toggle `users.receive_system_mail` |
| `toggle_discord_streak.php` | POST | 🛡️ | Toggle `users.receive_discord_streak_msg` |

---

## Admin endpoints

All require 👑 admin; mutations also require CSRF.

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `admin_get_accounts.php` | GET | List accounts |
| `admin_get_players.php` | GET | List players |
| `admin_get_claimed_characters.php` | GET | Claimed-character report |
| `admin_reset_claim.php` | POST | Reset a character claim |
| `admin_delete_account.php` | POST | Delete an account |
| `admin_change_*` / `change_password.php` | POST | Admin credential changes |
| `admin_bot_tokens.php` | GET/POST | Create / list / revoke `bot_tokens` for the bot API |
| `admin_exec.php` | POST | Run a shell/`/y/shell-exec` command (admin only) |
| `admin_test_mail.php` | POST | Send a test email |
| `promote_admin.php` (root) | — | Promote a user to admin |

---

## Automation ingest endpoints

| Endpoint | Method | Auth | Purpose |
|----------|--------|------|---------|
| `telemetry_ingest.php` | POST | 🤖 Bearer | Receive agent telemetry |
| `wiki_ingest.php` | POST | 🤖 Bearer | Ingest wiki/RAG content |

---

## NewServ game-server interface

The PHP layer talks to the NewServ emulator over HTTP at `$NEWSERV_API_URL`.
All calls are best-effort (`@file_get_contents`) and JSON is sanitized with
`iconv('UTF-8','UTF-8//IGNORE', …)` before decoding.

| Route | Used for |
|-------|----------|
| `/y/clients` | Live connected clients (name, account, character index, position, live stats/inventory) |
| `/y/accounts` | All accounts + BB licenses (credential validation, BB-username resolution) |
| `/y/account/` | Single-account operations |
| `/y/lobbies` | Active lobbies/games (LFG, boss tracking) |
| `/y/summary` | Server population/status summary |
| `/y/server` | Server status |
| `/y/config` | Server configuration |
| `/y/shell-exec` | Execute an in-game/server command (chat, warps, item ops, admin) |

Character save data is read directly from the NewServ filesystem
(`/opt/newserv/system/players/player_<user>_<slot>.psochar`, shared bank
`shared_bank_<user>.psobank`) and parsed by `bot_parse_psochar()` /
`character_viewer.php`.

---

## Database schema

SQLite at `db/website.db`. Base tables are created by `db/init_db.php`; several
endpoints self-migrate (add missing tables/columns) at runtime via `get_db()`.

| Table | Purpose |
|-------|---------|
| `users` | Website accounts: `username`, `email`, `account_id` (NewServ), `discord_id`, `language`, notification prefs |
| `sessions` | Server-side session storage (`id`, `data`, `last_accessed`) |
| `bot_tokens` | Hashed bot API tokens (`token_hash`, `revoked`, `expires_at`, `last_used_at`) |
| `daily_logins` | Unique play-days per account (streak source) |
| `daily_rewards` | Daily reward claim records |
| `streak_claims` | Claimed streak milestones per cycle |
| `missions` | Mission catalog (`goal_type`, `goal_target`, `reward_item_string`) |
| `player_missions` | Per-player mission progress/status |
| `community_events` | Server-wide events (`target_amount`, `current_progress`, `status`) |
| `community_event_participants` | Per-player community-event participation |
| `mods` | Submitted client mods (`status`: pending/approved) |
| `mod_ratings` | Per-account mod ratings (1–5) |
| `lfg_requests` | Looking-for-group requests |
| `password_resets` | Password-reset tokens |
| `rewards_claimed` | Per-character level-milestone reward claims |

---

## Cron daemons

Run out-of-band (not HTTP-routed); they read live NewServ state and write to
SQLite.

| Script | Role |
|--------|------|
| `cron_missions.php` | Generate & track per-player AI bounty missions |
| `cron_community.php` | Drive community events + AI milestones |
| `cron_boss_tracker.php` | Detect boss kills from `/y/clients` |
| `cron_streak_alert.php` | DM players (via `BOT_TOKEN`) before a login streak expires |

---

*Generated from a full read of the `api/` source. For the higher-level
narrative see [`../DEVELOPER_GUIDE.md`](../DEVELOPER_GUIDE.md); for the bot side
see [`../../psobb-discord-bot`](../../psobb-discord-bot).*
