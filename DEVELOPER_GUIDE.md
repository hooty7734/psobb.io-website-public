# Phantasy Star Online Blue Burst (PSOBB.io) Developer Guide

Welcome to the **PSOBB.io Web Application Ecosystem**! This document serves as the master architectural guide for the project. The codebase bridges the gap between a modern PHP web application and the underlying game server running the `NewServ` emulator.

## 1. High-Level Architecture Overview

The web application and game server ecosystem interact seamlessly, allowing real-time, bi-directional manipulation of live player data, inventories, and mission progress.

### 1.1 Core Components
* **Frontend Web App:** Standard HTML, CSS (featuring modern glassmorphism UI), and Vanilla JavaScript.
* **Backend Layer:** Vanilla PHP endpoints located securely in the `/api/` directory.
* **Database:** SQLite (`db/website.db`). Used for tracking user accounts, bounty mission data, daily task streaks, and Discord associations.
* **Game Server (`NewServ`):** A custom C++ based PSOBB Emulator running locally on the ecosystem server. The PHP backend interfaces directly with it using HTTP REST commands exposed by `NewServ`'s simulation engine.
* **AI Engine:** Google Gemini, generating dynamic lore-friendly missions.

### 1.2 Environment Configuration
Configurations are strictly managed via a root `.env` file containing:
* `GEMINI_API_KEY`: Google AI credentials for mission generation.
* `NEWSERV_API_URL`: Internal URL addressing the local game server API (usually `http://127.0.0.1:xxx`).
* `NEWSERV_COMMAND_PREFIX`: Configurable command symbol (e.g. `*` or `$`) used natively in-game.
* Discord OAuth Client IDs and Secrets.

---

## 2. Authentication & Account Lifecycle

### 2.1 Cross-Domain Session Hardening
The site utilizes unified cookie origins to prevent session desync. All cross-domain HTTP traffic (`www.psobb.io`) is forced via an `.htaccess` 301 redirect onto the naked domain (`psobb.io`). Frontend calls utilize `credentials: 'same-origin'` to reliably transmit session tokens to the `/api/` endpoints.

### 2.2 Account Creation and Game Synchronization
1. Player registers via `register.php`.
2. A new record is created in the SQLite `users` table.
3. Concurrently, a remote call is fired to the `NewServ` server creating identical login credentials natively on the PSOBB backend.
4. Passwords are encrypted on the website and synced with the game logic so players can launch directly via the client.

### 2.3 Discord OAuth Integration
The Discord integration (`discord_auth.php`, `discord_callback.php`) maps an administrative Discord ID to the web account. This grants backend privileges, limits Dashboard access, and enables the Discord Bot (`psobb-discord-bot`) to broadcast administrative notifications securely.

---

## 3. The Pioneer 2 Web Ecosystem (Core Features)

### 3.1 The Gemini-Driven Bounty Board (Missions Engine)
The Hunters Guild bounty board revolves around an autonomous, scaling AI mission system driven by Google Gemini.

#### A. Generation & Prompting (`api/cron_missions.php`)
Every minute, a local Cron Job executes `cron_missions.php`.
* **Detection:** The script polls `NewServ` (via `/y/clients`) to detect all currently online users.
* **Prompt Engineering:** It checks if a player lacks an active daily bounty. If true, it extracts the player's Level and Class, injecting them into a strict prompt.
* **Synthesis:** Google Gemini generates a lore-friendly JSON quest including a `title`, `description`, and a categorical `goal_type` (e.g., `ITEM`, `BOSS_ARENA`, `LEVEL`).

#### B. The Item Target Override Logic
To maintain game economy balance, Gemini cannot unilaterally decide item rewards. When Gemini specifies the `ITEM` type, the PHP backend hijacks the `goal_target`:
* **Level 40+ Players:** A chase-rare is randomly selected from the `reward_tables.php` loot tables based on the player's class and level tier.
* **Level < 40 Players:** The backend generates an intrinsically untekked common weapon based on player class utilizing the `get_common_reward_item()` function returning `? Special BaseWeapon` strings (e.g., `"? Charge Saber"`).

#### C. In-Game Tracker Evaluation
On subsequent cron ticks, the same script evaluates the mission state by cross-referencing live state loops with the SQLite database.
* To clear it, the engine parses the `goal_target`.
* For native strings (`? Charge Saber`), it strips the `? ` and the designated special ability. It simply loops through the player's `Description` metadata searching for a substring match (`saber`). If verified, the system flips the status to `ready_to_redeem`.

#### D. Obfuscation & Final Server Dispatch (`missions.php` & `redeem_bounty.php`)
1. **Frontend Disguise:** To prevent spoiling untekked weapons natively on the dashboard, `missions.php` utilizes the `renderRewardString` hook to evaluate the `? ` and securely render it as `???? Double Saber`.
2. **Redemption Post:** When claimed, `api/redeem_bounty.php` issues a shell execution wrapper to the `NewServ` API invoking the GM item drop logic across the wire: `on <accId> cc *item ? Demon's Raygun 10/0/45/0`.

### 3.2 Game Server Hook Translation (`newserv/src/ItemNameIndex.cc`)
The backend natively intercepts the literal command structure via its C++ source hook `ItemNameIndex.cc`.
* It detects the `?` string, immediately setting the `is_unidentified = true` (`0x80`) bit field on the memory block.
* It slices the special string (`Demon's`) translating it into Special Ability hex values.
* The physical drop is dispatched to the client. Because the `0x80` unidentified bit is flipped, the player's client forces the rendering to a standard `???? Raygun` drop box. The core stat variables (`10/0/45/0`) sit entirely hidden until the player visits the Tekker inside the main lobby suite.

---

## 4. Extended Drop & Game Systems

### 4.1 Global Streaks & Daily Grinds
* **Daily Drops (`claim_daily.php`):** Validates a rolling 24-hour timestamp check. Grants users a guaranteed daily roulette lootbox utilizing the standard tables logic.
* **Streaks (`claim_streak.php`):** Cumulative loyalty points. Tracks log-in behavior, dynamically increasing minimum reward quality metrics.
* **Reward Tables (`reward_tables.php`):** The master statistical index map dictating all drop rates. Procedurally attaches % stats, slots, and base armors.

### 4.2 Web Remote Mag Feeding (`mag_feed.php` / `mag_inventory.php`)
A real-time remote-feeder that links to the player's memory allocation space.
* Scans the `InventoryItems` index looking natively for objects coded as `0x02` (Mags).
* Determines feeding timers and available stat pools based on current growth patterns and player-defined item deletions (e.g., consuming specific mates/fluids directly subtracts the Hex item string from the player's live active backpack array natively injecting the stat variables into the Mag).

---

## 5. Administration Control Panel

### 5.1 Remote Control Dashboard (`admin/dashboard.php`)
Authorized administrators operate the ecosystem via this unified panel.
* **Live Server Status:** Checks connectivity and latency to the `NewServ` binary instance.
* **Command Executions (`admin_exec.php`):** Bypasses all standard restrictions to forcibly parse REST commands. Directly interacts with GM Chat Hooks (i.e. Global server-wide `announce-mail` warnings, force-starting holiday events, and performing instant database resets for testing schemas).
