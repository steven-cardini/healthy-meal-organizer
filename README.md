# Menüplan

Web app (PWA) for one household that shows what is cooked today and this week, based on a meal plan that repeats every 14 days. See [docs/requirements.md](docs/requirements.md).

Requirements: PHP 8.5 with `pdo_sqlite` and `mbstring`; Apache with `mod_rewrite` (production).

## Setup

1. Copy `config.example.php` to `config.php` and fill in:
   - `password_hash`: `php -r 'echo password_hash("YOUR-PASSWORD", PASSWORD_DEFAULT), PHP_EOL;'`
   - `cycle_start_date`: a Monday on which "Woche 1" starts.
   - `data_dir`: keep the default unless the data should live elsewhere (never inside `public/`).
2. The database `data/db.sqlite` is created from `db/schema.sql` on the first request.

## Local development

```sh
php -S localhost:8000 -t public router.php
```

Open <http://localhost:8000>. The session cookie is only marked `Secure` outside the PHP dev server. Safari may still refuse cookies on `http://localhost`; use Chrome or Firefox locally.

Test data (a full 14-day plan, into an empty database only):

```sh
rm -f data/db.sqlite && php tests/load_test_data.php
```

Tests (use a temporary data folder and start their own dev server):

```sh
php tests/smoke.php
```

## Deployment

1. Set the web server's document root to the `public/` folder. The root `.htaccess` denies everything as a safety net in case the document root points at the repository root.
2. Upload `config.php` next to `config.example.php`, then deploy the code.
3. Make sure `data/` (or the configured `data_dir`) is writable by PHP, survives deployments, and is backed up.
4. Check once: `http://` redirects to `https://`, and `https://` pages load without a redirect loop.

To log out all devices: change `password_hash` and delete `data/sessions/*`.

Changing a file listed in `PRECACHE` in `public/sw.js`: bump `CACHE` there.
