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

## Open points (before or during implementation)

- **Hosting:** set the web server document root to `<repo>/public` before the first deployment.
- **Hosting:** check that `REMOTE_ADDR` is the client IP and not the IP of the nginx proxy. Login throttling depends on it.
- **Hosting:** check once that `http://` redirects to `https://` and that `https://` loads without a redirect loop (nginx in front of Apache).
