# Architecture

Requirements: see [requirements.md](requirements.md). Decisions and their reasons: see [decisions.md](decisions.md).

## Overview

Server-rendered PHP app without framework.

```
Browser / PWA ──HTTPS──▶ Apache (docroot = public/) ──▶ public/index.php ──▶ src/ ──▶ SQLite (data/db.sqlite)
```

## Stack

| Layer   | Choice                                                                                          |
|---------|-------------------------------------------------------------------------------------------------|
| Backend | Plain PHP 8.5 (`declare(strict_types=1)`), no framework, no Composer                            |
| Data    | SQLite via PDO. `data/db.sqlite` is created from `db/schema.sql` on the first request           |
| UI      | Server-rendered HTML templates, plain forms, Pico.css v2 (jade theme, CDN, version-pinned, SRI). No JavaScript except the service worker registration |
| PWA     | Web app manifest and a small service worker                                                     |
| Hosting | Apache with `mod_rewrite`, HTTPS                                                                |

## Directory layout

```
.htaccess             Safety net: denies everything (in case the docroot is set to the repo root)
public/               Document root (the only web-accessible folder)
  index.php           Front controller: routing, session, CSRF, security headers
  .htaccess           Allows access; HTTPS redirect (fallback); rewrites everything except existing files to index.php
  manifest.webmanifest
  sw.js               Service worker (at the root so its scope is /)
  offline.html
  assets/             app.css, app.js (service worker registration), icons/
src/                  PHP code: app.php (helpers, config, DB, session), calendar.php, repository.php, pages.php,
                      test_data.php (test plan, loaded into an empty DB if config.php has load_test_data = true)
templates/            HTML templates
db/schema.sql         Database schema (SSOT)
data/                 db.sqlite, sessions/ (gitignored, created at runtime; location set by data_dir in config.php)
tests/smoke.php       Smoke tests
router.php            Router for the PHP built-in dev server
config.example.php    Config layout (SSOT); copy to config.php (gitignored)
```

## Routes

| Method   | Path            | Purpose                                         |
|----------|-----------------|-------------------------------------------------|
| GET      | `/`             | Day view; optional `?date=YYYY-MM-DD`           |
| GET      | `/week`         | Week view; optional `?date=` (any day of the week) |
| GET      | `/plan`         | Plan editor                                     |
| POST     | `/plan/slot`    | Save one slot                                   |
| GET      | `/meals`        | Meal list                                       |
| POST     | `/meals/rename` | Rename a meal                                   |
| POST     | `/meals/delete` | Delete an unused meal                           |
| GET/POST | `/login`        | Login form / login                              |
| POST     | `/logout`       | Logout                                          |

Every route except `/login` requires a session. Every POST checks the CSRF token and redirects afterwards (Post/Redirect/Get). Messages after a POST ("Gespeichert.", errors) are shown once, next to the edited slot or meal.

## Plan editor

Each slot is a form with a choice *Gericht* / *Notiz* and one text field (suggestions from the meal list via `<datalist>`). An empty text field empties the slot. A meal name not yet in the meal list adds a new meal.

## Data model

Conceptual only. The SSOT is `db/schema.sql`.

- **meal**: `id`, `name` (max. 100 characters), `name_key` (unique; lowercase of `name` via `mb_strtolower`, because SQLite `NOCASE` ignores umlauts)
- **plan_slot**: `cycle_day` (1–14), `meal_time` (`lunch` | `dinner`), `meal_id` (FK → meal, `ON DELETE RESTRICT`), `note` (max. 100 characters)
  - Primary key (`cycle_day`, `meal_time`). Exactly one of `meal_id` / `note` is set. No row means the slot is empty.
- **login_failure**: `ip`, `failed_at` (for login throttling)

Meal names and notes are trimmed, and runs of whitespace are collapsed to one space. The meal list is sorted with accented letters treated like their base letter (`ä` like `a`).

Schema version: `PRAGMA user_version` (currently 1). The schema is applied when the version is 0. Later phases add migrations from one version to the next, so the production database never has to be deleted.

## Cycle calculation

`cycle_start_date` (a Monday, set in `config.php`) is cycle day 1.

```
cycle_day  = floor_mod(days_between(cycle_start_date, date), 14) + 1
cycle_week = cycle_day <= 7 ? 1 : 2
```

Because the start date is a Monday, every calendar week maps to exactly one cycle week. Dates before the start date also work (`floor_mod`). "Today" is taken in `Europe/Zurich`; day arithmetic uses plain calendar dates, so DST changes have no effect.

## Authentication

- One shared password. Its hash (`password_hash`) is stored in `config.php`.
- After a successful login: `session_regenerate_id(true)` and a new CSRF token. The session cookie is `Secure` (except on the PHP dev server), `HttpOnly`, `SameSite=Lax`, and lasts 180 days. PHP does not renew it by itself, so the app sends it again on each request.
- Session files are stored in `data/sessions/` with a matching `gc_maxlifetime` and its own garbage collection (`gc_probability`), so the host's session cleanup cannot end sessions early.
- Throttling: after 5 failed logins from one IP (`REMOTE_ADDR`) within 15 minutes, further attempts are rejected until the window has passed. A successful login clears the IP's failures.
- To log out all devices: change the password hash and delete `data/sessions/*`.

## Security headers

PHP sets these on every page:

- `Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' https://cdn.jsdelivr.net; img-src 'self' data:; base-uri 'self'; form-action 'self'; frame-ancestors 'none'`
- `X-Content-Type-Options: nosniff`
- `Referrer-Policy: same-origin`
- `Strict-Transport-Security: max-age=31536000`

No inline scripts or styles. PHP errors are logged, never displayed (except on the dev server).

## PWA

- Manifest: name "Menüplan", `lang: de-CH`, `start_url: /`, `display: standalone`, icons 192 px and 512 px (plus a maskable one). Also a 180 px `apple-touch-icon` for iOS. Icon source: `public/assets/icons/icon.svg` (plate with leaf inside two circular arrows: healthy food, repeating plan).
- Service worker: precaches `offline.html`, `app.css` and the SVG icon under a versioned cache name. All requests go to the network; the cache is used only when the network fails (pages then show `offline.html`). App pages are never cached.
- On iOS, an installed app has its own cookie store, so users log in once inside the installed app.

## Extension points

Not part of the MVP. Recorded so that the MVP design leaves room for them.

- **Swap (phase 2):** a table with one-time replacements keyed by (`date`, `meal_time`), holding a `meal_id` or a `note`. The effective entry for a date is the swap if one exists, otherwise the plan slot.
- **Prep time (phase 2):** a column on `meal`, used to suggest quicker meals when swapping.
- **Recipes, shopping list (phase 2):** recipes with ingredients, linked to `meal`.
- **Health balance (phase 3):** food group classification per meal or recipe.
