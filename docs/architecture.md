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
| Data    | SQLite via PDO. `data/db.sqlite` is created from `db/schema.sql` on the first request. SQL must work with the host's older SQLite: upserts (3.24) yes, `RETURNING` (3.35) no |
| UI      | Server-rendered HTML templates, plain forms, Pico.css v2 (jade theme, CDN, version-pinned, SRI) with warm colours overridden in `app.css`. JavaScript only for the service worker registration, swipe navigation (D-28), ingredient rows in the meal form (D-47) and ticks on the grocery list (D-49); every page works without it |
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
  assets/             app.css (theme, tag icons), app.js (service worker registration, swipe, slide direction, ingredient rows, grocery ticks), icons/
src/                  PHP code: app.php (helpers, config, DB, session), calendar.php, repository.php, pages.php,
                      test_data.php (test meals with ingredients and week blueprints, loaded into an empty DB if config.php has load_test_data = true)
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
| GET      | `/planning`     | *Planen*: the next week                         |
| GET      | `/grocery-list` | *Einkaufsliste* of a planned week; `?date=` (any day of the week) |
| POST     | `/planning/blueprint` | Plan the next week from the other week blueprint (`blueprint`) |
| GET/POST | `/planning/complete` | *Planung abschliessen*: confirmation / complete |
| GET      | `/week-blueprints` | Week blueprint editor                        |
| POST     | `/week-blueprints/slot` | Save one slot                           |
| GET      | `/meals`        | Meal list                                       |
| GET      | `/swap`         | *Anderes Gericht* / *Gericht planen* for `?date=&meal_time=`; optional filters `?prep_time=&protein_source=`. The `/swap` routes plan when the date is in the next week and it is not planned yet, otherwise they swap |
| POST     | `/swap/exchange`| Exchange the meals of two slots (`date`, `meal_time`, `partner_date`, `partner_meal_time`) |
| POST     | `/swap/move`    | Move the meal of the partner slot to this slot without a meal (same fields) |
| POST     | `/swap/meal`    | Put a meal from the meal list into the slot of a date |
| GET/POST | `/swap/skip`    | *Auslassen* form / skip with an optional note |
| POST     | `/swap/reset`   | *Zurücksetzen*                                  |
| GET/POST | `/meals/new`    | New meal form / create; optional `?name=`, and the slot to fill after saving: `?blueprint=&day=&meal_time=` (week blueprint) or `?date=&meal_time=` (planning, swap) |
| GET/POST | `/meals/edit`   | Edit form (`?id=`) / save name, tags, ingredients and recipe link |
| POST     | `/meals/delete` | Delete an unused meal                           |
| GET/POST | `/login`        | Login form / login                              |

Every route except `/login` requires a session. Every POST checks the CSRF token and redirects afterwards (Post/Redirect/Get). Messages after a POST ("Gespeichert.", errors) are shown once, next to the edited slot or meal (swaps: at the top of the day view; planning: at the top of the *Planen* page). Exception: an error in the meal form shows the form again with the entered values and status 422 (D-22).

## Week blueprint editor

Each slot is a form with a choice *Gericht* / *Notiz* and one text field (suggestions from the meal list via `<datalist>`). An empty text field empties the slot. A meal name not yet in the meal list redirects to `/meals/new` with the name and the slot; saving that form creates the meal, fills the slot and returns to the week blueprint editor.

## Planning and swap

What is cooked on a date (`load_entries()`): the `date_slot` row with `swap` = 1 if there is one, otherwise the row with `swap` = 0. Without a row: the slot of the week's week blueprint (`load_week()`). The day and week view, the *Planen* page and the hints use these entries.

- Planning (`swap` = 0): slots of the next week while it is not planned (`week.planned` = 0). Swapping (`swap` = 1): slots that are not past (`is_past()`) of the current week, and of the next week once it is planned (`is_swappable()`). Other slots redirect with a message.
- An exchange or a move writes two rows, each pointing to the other slot (`partner_date`, `partner_meal_time`). In a move, the target row has `moved` = 1 and the source row has no meal. Any later change of a slot overwrites its row and clears its partner. Marks (*getauscht*, *verschoben*, …) are shown for `swap` = 1, and for `swap` = 0 while the week is not planned (D-43).
- Planning forms send `planning=1`. If the week was planned in the meantime (e.g. by another person), the change is rejected with a message instead of becoming a swap.
- *Verschoben auf …* names the target only while the target row still points back (D-38).
- *Zurücksetzen* deletes the rows of `reset_slots()` of the same `swap` value: the slot, then repeatedly every slot in the same week that points to a deleted slot or that a deleted slot points to, except past slots (FR-19).
- Choosing the other week blueprint while planning deletes the week's rows (`swap` = 0) and stores the week blueprint in `week` (D-40).
- *Planung abschliessen* (`complete_week()`) copies every week blueprint slot without a row into `date_slot` (`swap` = 0), empty slots too, and sets `week.planned` = 1. Every logged-in request first completes the current week if it is not planned (`plan_current_week()`, FR-25). Exception: while `week` is empty, it only stores the current week as a planned *Woche 1* without rows, so the first week of use keeps following the week blueprints.
- Writes that read first (e.g. *Zurücksetzen*, *Planung abschliessen*) run in `BEGIN IMMEDIATE` transactions (`in_transaction()`).
- Freshness: `fresh_until()` (`src/calendar.php`), warnings in `move_warning()` and `unused_warning()` (`src/app.php`), hint in `slot_hints()`.

## Meal form

Name and one radio group per tag (FR-13), without a preselected value; every group is `required`. The tags and their UI labels are defined in `MEAL_TAGS` (`src/app.php`), which must match the `CHECK` constraints in `db/schema.sql` (checked by the smoke tests).

Optional recipe link (`type="url"`, `http`/`https` only, D-50).

Ingredients: one row per ingredient with a name (suggestions from all ingredient names via `<datalist>`) and a category (`INGREDIENT_CATEGORIES` in `src/app.php`, must match the `CHECK` in `db/schema.sql`). Existing ingredients show their category; a new row's category is empty. On save: rows with an empty name are dropped, names entered twice count once; an empty category takes the category of the known name, and is an error (422, D-22) for a new name; a chosen category and the spelling of the name are stored for the name, i.e. for all meals. *+ Zutat* clones an empty row from a `<template>` (D-47). Entering a known name selects its category (the suggestions carry it in `data-category`).

## Grocery list

`/grocery-list` takes the entries (`load_entries()`) of a planned week, skips slots without a meal, and groups the ingredients of their meals by `name_key`: categories in the order of `INGREDIENT_CATEGORIES`, names sorted like the meal list. Below each ingredient, its meals with the number of slots if more than one (*2×*). Meals without ingredients are listed below (*Ohne Zutaten*). A week not planned redirects to the *Planen* page.

Each ingredient has a checkbox; `app.js` stores the ticked `name_key`s in `sessionStorage` under the week's first date (D-49).

## Data model

Conceptual only. The SSOT is `db/schema.sql`.

- **meal**: `id`, `name` (max. 100 characters), `name_key` (unique; lowercase of `name` via `mb_strtolower`, because SQLite `NOCASE` ignores umlauts), and one `NOT NULL` column per tag (`protein_source`, `heaviness`, `prep_time`, `child_factor`, `shelf_life`, `leftovers`, `vegetable_level`), each with a `CHECK` on its allowed values (FR-13), `recipe_url` (optional, `http(s)://`, max. 2000 characters)
- **blueprint_slot**: `blueprint` (1 | 2), `day` (1–7, 1 = Saturday), `meal_time` (`lunch` | `dinner`), `meal_id` (FK → meal, `ON DELETE RESTRICT`), `note` (max. 100 characters)
  - Primary key (`blueprint`, `day`, `meal_time`). Exactly one of `meal_id` / `note` is set. No row means the slot is empty.
- **week**: `start_date` (a Saturday, primary key), `blueprint` (1 | 2), `planned` (0/1). A row exists once a week is planned, or once the other week blueprint was chosen while planning it.
- **date_slot**: `date` (`YYYY-MM-DD`), `meal_time`, `swap` (0: planned week or planning, 1: swap), `meal_id` (FK → meal, `ON DELETE RESTRICT`), `note`, `partner_date`, `partner_meal_time`, `moved` (0/1)
  - Primary key (`date`, `meal_time`, `swap`). At most one of `meal_id` / `note`; neither means empty or skipped, or moved away if there is a partner. A partner (exchange, move) excludes a note. `moved`: the meal was moved here; requires a meal and a partner.
- **ingredient**: `id`, `name` (max. 100 characters), `name_key` (unique, as for `meal`), `category` (`CHECK` on the codes of FR-28). Deleted when no meal uses it any more.
- **meal_ingredient**: `meal_id` (FK → meal, `ON DELETE CASCADE`), `ingredient_id` (FK → ingredient, `ON DELETE RESTRICT`), `position` (order in the meal form). Primary key (`meal_id`, `ingredient_id`).
- **login_failure**: `ip`, `failed_at` (for login throttling)

Meal names, ingredient names and notes are trimmed, and runs of whitespace are collapsed to one space. The meal list and the grocery list are sorted with accented letters treated like their base letter (`ä` like `a`).

Schema version: `PRAGMA user_version` (currently 1; the meal tags, `date_slot`, `week` and the ingredients were added to version 1 while production held only test data, so databases created earlier must be deleted once). The schema is applied when the version is 0. Later phases add migrations from one version to the next, so the production database never has to be deleted.

## Weeks and week blueprints

A week starts on the `WEEK_START` weekday (Saturday, `src/calendar.php`) on or before a date (`week_start()`) and ends on Friday.

```
blueprint = week.blueprint if the week has a row, otherwise the other week blueprint than the week before
            (counted from the nearest earlier week with a row, or the nearest later one if there is none)
day       = days_between(week_start, date) + 1      # 1 = Saturday
```

"Today" is taken in `Europe/Zurich`; day arithmetic uses plain calendar dates, so DST changes have no effect.

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

Not implemented yet. Recorded so that the design leaves room for them.

- **Ingredient quantities (later):** a column on `meal_ingredient`.
- **Health balance (phase 6):** based on the meal tags (FR-13) and the entries of past dates (planned weeks and kept swaps).
- **More week blueprints (later):** the alternation and the `CHECK`s on `blueprint_slot.blueprint` and `week.blueprint` assume two.
