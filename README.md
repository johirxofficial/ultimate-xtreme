# Ultimate Xtreme — Restream Console

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![PHP 8.0+](https://img.shields.io/badge/PHP-8.0%2B-777bb4.svg)](https://www.php.net/)
[![SQLite](https://img.shields.io/badge/Database-SQLite-003b57.svg)](https://www.sqlite.org/)

A modern, dashboard-driven HLS restream console for Xtreme Codes providers.
Manage providers, browse channel libraries, publish M3U playlists and choose
**Relay** or **Direct** delivery — all from the browser.

**Version 4.0** · Updated **12 November 2026** · PHP 8.0+ · SQLite · MIT licensed · no Composer or build step.

> **Repository:** <https://github.com/johirxofficial/ultimate-xtreme>
>
> The public repository contains the supported v4 console only. Runtime data,
> provider credentials and the legacy standalone scripts are intentionally
> excluded.

---

## Contents

- [Why Ultimate Xtreme](#why-ultimate-xtreme)
- [Highlights](#highlights)
- [Roles](#roles)
- [Requirements](#requirements)
- [Installation](#installation)
- [Console map](#console-map)
- [Public endpoints](#public-endpoints)
- [Server fields](#server-fields)
- [Settings, cache & backups](#settings-cache--backups)
- [Project structure](#project-structure)
- [Development](#development)
- [Security](#security)
- [Upgrading from 3.x](#upgrading-from-3x)
- [Reset / forgot password](#reset--forgot-password)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [Code of conduct](#code-of-conduct)
- [License](#license)
- [Changelog](#changelog)

---

## Why Ultimate Xtreme

Provider panels expose credentials and stream addresses that are awkward to
share with viewers. Ultimate Xtreme puts a clean console in front of them:

- Keep provider accounts in one place and manage them without editing files.
- Hide upstream addresses behind **Relay**, or keep traffic light with
  **Direct**.
- Publish a single M3U URL that can be refreshed as providers change.
- Give managers the day-to-day tools they need without exposing settings, team
  management or backups.
- Fail honestly: a broken stream returns a real HTTP error instead of silently
  switching to an unrelated fallback.

The application is deliberately small: plain PHP, one SQLite database, no
Composer packages, no JavaScript framework and no asset pipeline.

---

## Highlights

**Provider management**
- Add, edit, duplicate, delete and pause providers without touching a file.
- Per-server delivery mode: **Relay** (hide the provider address) or **Direct**
  (provider URLs).
- Per-server M3U publishing switch, User-Agent override and notes.
- One-click connection test with account status, expiry and connection usage.

**Channel library**
- Browse live channels through `player_api.php`, with search, category filter
  and pagination.
- Channel logos, stream IDs and ready-to-copy signed stream URLs.
- Server-side cache with a configurable TTL and a manual refresh button.

**Playlists**
- Public M3U generator: `/playlist/{server}.m3u`, ready for IPTV apps.
- Every channel in the generated playlist points at this installation's signed
  `/live` endpoint, so relay/direct rules and on/off state always apply.
- Master playlists, variant playlists, encryption keys and init segments are
  rewritten automatically.

**Console**
- Flat, light-only responsive UI with keyboard-friendly dialogs and command
  palette (`Ctrl`/`Cmd` + `K`).
- **Multiple accounts** with two roles: **Administrator** and **Manager**.
- Account page for changing your own password.
- Searchable, filterable activity log with an actor column.
- JSON backup export/import, cache management and runtime health checks.
- First-run administrator setup; passwords stored with `password_hash`; forms
  protected by CSRF tokens.
- Real HTTP errors with `X-Stream-Status` for public stream failures.

---

## Roles

| Capability | Administrator | Manager |
| --- | :---: | :---: |
| Dashboard | Yes | Yes |
| Servers, channels and playlists | Yes | Yes |
| Activity log | Yes | Yes |
| Clear activity history | Yes | No |
| Team / account management | Yes | No |
| Settings, backups and cache controls | Yes | No |
| Change own password | Yes | Yes |

The first-run screen creates the first **Administrator**. Additional
administrators and managers can be added from **Team**. At least one active
administrator must always remain.

---

## Requirements

- PHP **8.0+** with `curl`, `json`, `mbstring`, `openssl` and `pdo_sqlite`
  (all standard in XAMPP).
- Apache with `mod_rewrite` and `AllowOverride All` for friendly URLs.
- Write access to `data/` — the app creates the SQLite database and cache there.

Without pretty URLs the app still works through query strings:
`index.php?server={server}&id={channel}` and `playlist.php?server={server}`.

---

## Installation

1. Copy the project folder into your web root, e.g.
   `C:\xampp\htdocs\ultimatextreme`.
2. Confirm Apache is running and `mod_rewrite` is enabled.
3. Make sure `data/` is writable by PHP.
4. Open `http://localhost/ultimatextreme/` — you are redirected to the console.
5. Create the first administrator account, then add a provider from **Servers**.

A fresh database starts with **no providers**. Nothing is seeded from an old
`config.php`, and no credentials are shipped in the repository.

### Local test server (optional)

```bash
php -S 127.0.0.1:8899 -t .
```

Pretty URLs need Apache (`.htaccess`). With the built-in server, use the `.php`
paths: `/admin/login.php`, `/admin/servers.php`,
`/index.php?server=…&id=…`.

---

## Console map

| Page | Purpose |
| --- | --- |
| `/admin/` | Overview: stats, server state, health checks, endpoints, recent activity |
| `/admin/servers` | Provider CRUD, duplicate, on/off switches, connection tests, M3U links |
| `/admin/channels` | Channel browser, search/category filters, signed stream links |
| `/admin/activity` | Paginated, searchable audit trail with actor and event filters |
| `/admin/about` | Version, release date, product overview, roles and runtime details |
| `/admin/users` | Administrator-only team management (roles, resets, disable, delete) |
| `/admin/account` | Your profile and password |
| `/admin/settings` | Administrator-only branding, delivery defaults, backups, cache and health |

Friendly aliases: `/dashboard`, `/login`, `/logout`. Legacy `dashboard.php`,
`login.php` and `logout.php` redirects remain for old bookmarks.

---

## Public endpoints

| Purpose | URL |
| --- | --- |
| Channel playlist | `/live/{server}/{channel}.m3u8` |
| Nested playlist | `/pl/{server}/{payload}.m3u8` |
| Segment / key / init | `/stream/{server}/{payload}.{ext}` |
| Generated M3U | `/playlist/{server}.m3u` |
| Legacy playlist | `/re-index.php?server={server}&id={channel}` |

`{server}` is the slug from the dashboard; `{channel}` is the provider stream
id. Nested payloads are HMAC-signed, so `/stream` and `/pl` cannot be used as an
open relay.

### Delivery modes

- **Relay** — playlists, segments, keys and init segments are fetched and
  delivered by this host. Viewers only see this server's address.
- **Direct** — playlists are rewritten with absolute upstream URLs, so players
  connect directly to the provider. Very light on bandwidth, but the provider
  address is visible to viewers.

Internal database values remain `proxy` and `rewrite` for backwards
compatibility; the console always displays **Relay** and **Direct**.

### Errors — no fallback

There is no offline fallback playlist and no global fallback URL. If a server is
paused, a channel is missing or the provider fails, the public endpoint returns
a real HTTP error (`400`, `404`, `503` or `502`) with a short plain-text message
and `X-Stream-Status: error`. Players show the failure instead of silently
switching to an unrelated stream. Detailed provider errors remain visible in the
console's connection tests and channel pages.

---

## Server fields

| Field | Notes |
| --- | --- |
| Display name | Dashboard label. Defaults to a capitalised slug. |
| Slug | URL identifier: `a-z`, `0-9`, `-`, `_`, 2–32 chars, unique. |
| Host | Provider base URL, e.g. `http://panel.example.com:8080`. |
| Username / password | Provider line credentials. |
| Delivery mode | `Relay` or `Direct`. |
| Active | On/off switch; pauses every stream for that server. |
| Publish M3U | Enables `/playlist/{server}.m3u`. |
| User-Agent | Optional override for upstream requests. |
| Note | Free text, max 500 characters. |

---

## Settings, cache & backups

- **Channel cache TTL** — how long provider channel libraries stay cached
  (60–86400 seconds). **Settings → Clear channel cache** forces the next
  channel page to re-fetch.
- **Export** — downloads providers and general settings as JSON. The export
  includes provider credentials in plain text: store it securely. Password
  hashes, team accounts and the app secret are **not** exported.
- **Import** — replaces all providers and general settings. Invalid or
  duplicate entries are skipped; the import runs in a transaction, so a failure
  rolls back to the previous data.
- **Password** — use **My account** to verify the current password and set a new
  one.

---

## Project structure

```text
.
├── admin/                  authenticated console pages
│   ├── partials/           shared header, footer and navigation
│   ├── about.php
│   ├── account.php
│   ├── activity.php
│   ├── channels.php
│   ├── index.php
│   ├── login.php
│   ├── logout.php
│   ├── servers.php
│   ├── settings.php
│   └── users.php
├── assets/
│   ├── app.css             design tokens and console styles
│   └── app.js              dialogs, command palette, copy helpers
├── data/                   runtime data (contents ignored by Git)
│   ├── .gitkeep
│   └── .htaccess           denies direct web access
├── includes/
│   ├── .htaccess
│   ├── auth.php            sessions, roles and account helpers
│   ├── db.php              SQLite schema, migrations and queries
│   ├── functions.php       routing, signing, streams and UI helpers
│   └── icons.php           inline SVG icon set
├── .editorconfig           shared editor defaults
├── .gitattributes          consistent line endings
├── .gitignore
├── .htaccess               front controller and access rules
├── CODE_OF_CONDUCT.md
├── CONTRIBUTING.md
├── LICENSE
├── README.md
├── SECURITY.md
├── config.php              application bootstrap and version metadata
├── index.php               public stream / playlist router
├── playlist.php            public M3U generator
├── re-index.php            legacy playlist alias
├── dashboard.php           legacy redirect
├── login.php               legacy redirect
└── logout.php              logout handler
```

Runtime files such as `data/ultimatextreme.sqlite`, SQLite WAL/SHM files,
`data/cache/` and backups are intentionally ignored and must never be committed.

---

## Development

### Running locally

```bash
php -S 127.0.0.1:8899 -t .
```

Open `http://127.0.0.1:8899/admin/login.php` and create the first
administrator. For Apache, enable `mod_rewrite` and `AllowOverride All` so the
friendly routes work.

### Coding guidelines

- Target **PHP 8.0+** and keep the app compatible with SQLite.
- Match the surrounding style: four-space indentation, descriptive names, early
  returns and small functions.
- Use strict comparisons (`===`, `!==`) and prepared statements for every
  query.
- Escape output with the existing `e()` helper. Never build HTML from raw
  request data.
- Keep CSRF protection on every state-changing form.
- Keep role checks on every authenticated page and action. At least one active
  administrator must remain.
- Reuse the existing helpers, layout partials and CSS tokens rather than adding
  a framework or dependency.
- Keep the UI light-only and responsive.
- Do not weaken HTTP error behaviour or add silent stream fallbacks.
- Never commit provider credentials, the SQLite database, cache, backups, logs
  or local configuration. Use placeholders such as `panel.example.com` in
  examples.

### Syntax and quality checks

```powershell
# PHP syntax
Get-ChildItem -Recurse -Filter *.php |
  ForEach-Object { php -l $_.FullName }

# JavaScript syntax
node --check assets/app.js
```

### Manual test checklist

- First-run setup and sign-in
- Provider create, edit, duplicate, pause and delete
- Connection test and channel loading
- Relay and Direct delivery
- Generated M3U playlist and public stream endpoints
- Administrator and Manager permissions
- Account password change and Team management
- Activity log, backup export/import and cache controls
- Real HTTP errors for a paused server, unknown channel or upstream failure

---

## Security

- SQLite in WAL mode with a 5-second busy timeout.
- Passwords hashed with `password_hash` (bcrypt/Argon2 depending on PHP).
- Session cookie is `HttpOnly` + `SameSite=Lax`; HTTPS adds `Secure`.
- Every admin form uses CSRF tokens.
- Role checks run on every authenticated request, so disabling or demoting an
  account takes effect immediately.
- `includes/`, `admin/partials/`, `.git`, the SQLite database and the runtime
  cache are blocked from direct web access on Apache.
- The public stream endpoints never start a PHP session.

> Provider credentials in SQLite are **not encrypted at rest**. Protect the
> `data/` folder, backups and exports accordingly.

### Reporting a vulnerability

Please do not open a public issue. See [SECURITY.md](SECURITY.md) for the private
reporting process and deployment hardening checklist.

---

## Upgrading from 3.x

The first request migrates the old single-admin settings into the `users` table:

- If `admin_hash` existed, it becomes an **Administrator** account using
  `admin_user` (or `admin`) as the username. The old password keeps working.
- The retired `fallback_url`, `admin_hash` and `admin_user` settings are removed,
  and the unused `servers.fallback_url` column is dropped automatically.
- Existing servers, settings, channel cache and activity are preserved.
- Old activity entries show as `system` because they predate the actor column.

If the database had no password hash, the first-run setup screen appears.

---

## Reset / forgot password

If at least one administrator can still sign in, use **Team** to reset a
password. If every administrator is locked out, stop Apache and remove the
database files:

```text
data/ultimatextreme.sqlite
data/ultimatextreme.sqlite-wal
data/ultimatextreme.sqlite-shm
```

The next visit starts first-run setup again with an empty provider list. Deleting
only the database does **not** delete the cache; clear `data/cache/` if needed.

---

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Friendly URLs return 404 | `mod_rewrite` enabled and `AllowOverride All` for the folder. |
| First-run screen never appears | The `users` table already has accounts. Sign in or reset the DB. |
| “Could not load channels” | Provider host/credentials, `player_api.php` reachability, and the connection test. |
| Playlist returns 404 | Server paused, **Publish M3U** disabled, or unknown slug. |
| Streams return an error | Server paused, provider offline, expired line, invalid/expired payload, or blocked upstream. Check the HTTP status and the console logs. |
| Changes not visible | Clear the channel cache; force-refresh the player playlist. |
| Manager cannot open Settings/Team | Those pages are administrator-only. |
| Data folder is not writable | Grant PHP write access to `data/`; the app creates the database there. |

---

## Contributing

Contributions are welcome. Please read [CONTRIBUTING.md](CONTRIBUTING.md) for the
development setup, coding standards, testing checklist and pull-request
process.

In short:

1. Open an issue for a larger change before writing code.
2. Fork the repository and work on a focused topic branch.
3. Keep the existing PHP/SQLite architecture and light-only UI.
4. Run the PHP and JavaScript syntax checks and test the affected flow.
5. Never commit credentials or runtime data.
6. Open a pull request with a clear description and screenshots for UI changes.

By participating, you agree to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

---

## Code of conduct

This project follows the [Contributor Covenant](CODE_OF_CONDUCT.md). Report
unacceptable behaviour privately to **johirxofficial@gmail.com**.

---

## License

Released under the [MIT License](LICENSE).

Copyright © 2026 johirxofficial.

---

## Changelog

### 4.0

- Removed the global/per-server fallback URL system. Public failures now return
  real HTTP errors.
- Removed dark mode and the theme toggle. Flat, light-only UI with a
  popup-style sign-in card.
- Removed the sidebar logo mark; the console title remains.
- Added administrator/manager accounts, Team management and a self-service
  account page.
- Added an About page with version, release date and runtime information.
- Renamed the visible delivery modes to Relay and Direct; database values stay
  compatible.
- Added actor tracking and searchable/event-filtered activity history.
- Added server duplication, password strength feedback and expanded role-aware
  navigation.
- Backups now omit team accounts; settings and providers remain portable.
- File naming and routes for `admin/users` and `admin/account` are standardised.
- Removed legacy provider seeding. Fresh installations start with an empty
  provider list.

### 3.0

- New multi-page console, command palette, responsive layout.
- Channel browser, public M3U generator, activity log.
- JSON backup/import, cache management, system health.
- Per-server M3U publishing switch, clean routes, signed stream endpoints
  retained.
