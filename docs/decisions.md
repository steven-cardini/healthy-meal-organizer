# Decisions

| ID   | Decision | Reason / rejected alternatives |
|------|----------|--------------------------------|
| D-1  | MVP = repeating meal plan only. Then swap, recipes, shopping list; finally health balance. | Small first release. |
| D-2  | One household, one shared password, everyone may edit. | No user accounts needed. Rejected: individual logins, several households. |
| D-3  | Plain PHP + Pico.css, server-rendered, plain HTML forms, no build step. No JavaScript framework in the MVP. | Fits shared hosting and keeps the code simple. No MVP feature needs JavaScript. Alpine.js (CSP build) can be added later if a feature needs it. Rejected: htmx; PHP JSON API + Vue SPA (two layers, more JS). |
| D-4  | Reusable meal list. A slot holds a meal, a note, or nothing. | The meal list is the pool for later swaps (with prep time) and recipes. Rejected: notes as special meals; free text per slot (would need a migration later). |
| D-5  | A 14-day cycle anchored on `cycle_start_date` (a Monday) in `config.php`. | Robust across years. Rejected: even/odd ISO week number (breaks in years with 53 weeks). |
| D-6  | Online-only PWA. The service worker caches only static files and an offline notice. | Never shows outdated data. Rejected: read-only offline view. |
| D-7  | Document root = `public/`. `config.php` lives in the repo root. A root `.htaccess` with `Require all denied` is a safety net; `public/.htaccess` allows access again. | Only public files are reachable, by design. The safety net protects config, data and docs if the document root is ever set wrong. Rejected: repo root as docroot with `.htaccess` deny rules. |
| D-8  | Day and week views have previous/next navigation. | Lets users look ahead, e.g. at next week on Sunday evening. Little effort. |
| D-9  | UI name "Menüplan"; cycle weeks are called "Woche 1" / "Woche 2". The week view title shows both, e.g. "KW 45 · Woche 1"; the day view shows the cycle week. | Common Swiss term. The KW avoids confusing calendar and cycle weeks. |
| D-10 | Login lasts 180 days after last use. Throttling: 5 failures per IP per 15 minutes. | Phones and tablets should rarely need a new login, and the login is reachable from the public internet. |
| D-11 | Time zone Europe/Zurich (fixed), weeks start on Monday. | Single Swiss household. |
| D-12 | Concurrent edits: the last save wins. | Small household; locking is not worth the complexity. |
| D-13 | No email in the MVP (no PHPMailer). | No feature needs it. |
| D-14 | Plan editor: one form per slot with a choice *Gericht* / *Notiz* and one text field with suggestions. An empty field empties the slot. | Works without JavaScript; saving one slot keeps "last save wins" per slot. Rejected: a third choice "leer" (users could type a name and save it as empty by mistake); one form for all 28 slots (overwrites other people's edits). |
| D-15 | Renaming a meal to the name of another meal is rejected with an error. | Simple. Rejected: merging the two meals. |
| D-16 | Meal names are unique via a lowercase `name_key` column. | SQLite `NOCASE` only folds ASCII, so "Älplermagronen" and "älplermagronen" would both be accepted. |
| D-17 | Schema version in `PRAGMA user_version`. | Later phases can migrate the production database instead of recreating it. |
| D-18 | Meal tags (FR-13) are one `NOT NULL` column per tag on `meal`, with a `CHECK` on the allowed values. The table stays `meal`. | The database enforces "exactly one value per tag". Rejected: a generic tag table (`type`, `value`), which cannot enforce this; renaming to `recipe` (in the glossary, a recipe is ingredients and steps). |
| D-19 | A meal can only be created with all tags. A new name in the plan editor redirects to the new meal form; after saving, the slot is filled. | Mandatory tags. Rejected: creating meals without tags; an error message with a link (one more click). |
| D-20 | Prep time is a category (`quick`, `medium`, `slow`), not minutes. | Enough for planning and for finding quicker meals. Rejected: `prep_minutes`. |
| D-21 | One `leftovers` tag (`reheats_well`, `eat_fresh`). *Doppelte Menge kochen* / *Reste* are derived from the same meal in consecutive slots (FR-14). | "Cook more" depends on the plan, not on the meal. Rejected: separate values `double_batch` and `reheats_well` (almost always overlap); a leftover flag per slot. |
| D-22 | An error in the meal form shows the form again with the entered values (status 422), without a redirect. | The user would otherwise lose up to 7 choices. Rejected: Post/Redirect/Get with the values in the session. |
| D-23 | `meat` means all meat except poultry. `protein_source` has a value `none`, which is discouraged. | Common Swiss split "Fleisch / Geflügel / Fisch". Rejected: `beef_pork` (too narrow), `red_meat` (veal is not red meat). |
| D-24 | Tag values are lowercase snake_case codes with German UI labels. The meal form uses radio groups without a preselected value. | Consistent with `lunch` / `dinner`. All options are visible and need one tap; nothing is saved by accident. Rejected: drop-downs (options hidden), default values. |
| D-25 | The week view only shows hints you can act on (FR-14). The day view also shows protein source, prep time and kid suitability (FR-15); the meal list shows the same tags (`SUMMARY_TAGS`). Digestibility, shelf life, leftovers and vegetables are shown in the meal form only. | The week view stays short. On the day view there is room for the facts that matter when cooking today. Rejected: all tags everywhere. |
| D-26 | Hints are chips below the meal name, each with an icon and a colour per kind. Every tag value has one icon (emoji), used in the meal form, the meal list and the day view; hints use the icon of the tag value they come from. Icons are set in CSS by a class per value (`tag_class()`), never without text. | One visual language that is learned once. Icons alone are not self-explanatory, so the text is always shown. Rejected: grey text after the name; icon-only badges. |
| D-27 | The week view is a list of day cards (lunch and dinner stacked), with three columns on wider screens. | The previous table was wider than a phone screen. Rejected: a table with horizontal scrolling. |
| D-28 | Navigation: a tab bar at the bottom on phones, at the top on wider screens. Swiping left / right on the day and week view drags the content with the finger and, past a quarter of the screen width or with a quick flick, follows the next / previous link, with a slide transition (cross-document view transitions). No swipe while zoomed in. Small JavaScript in `app.js`, no framework (D-3). | Native app feel and thumb reach. Everything still works without JavaScript or view transition support (buttons, no animation). |
| D-29 | CSS and JS URLs carry the file's modification time (`asset_url()`). | Browsers otherwise keep using an outdated `app.css` after a deployment. Rejected: manual version numbers (easy to forget). |
| D-30 | No logout. | Users never log out on their own devices; all devices can be logged out on the server (see `README.md`). |

## Open points (before or during implementation)

- **Hosting:** set the web server document root to `<repo>/public` before the first deployment.
- **Hosting:** check that `REMOTE_ADDR` is the client IP and not the IP of the nginx proxy. Login throttling depends on it.
- **Hosting:** check once that `http://` redirects to `https://` and that `https://` loads without a redirect loop (nginx in front of Apache).
