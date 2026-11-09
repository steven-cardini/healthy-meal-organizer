# Agent Instructions

## 1. Documentation Index & SSOT Locations
Do not duplicate code or schemas. Read the dedicated single-source files in `docs/` and `db/` before implementing logic:

- **Project Context, Requirements, Architecture, Deployment and everything related:** See files in `/docs`
  - **Requirements:** `docs/requirements.md`
  - **Architecture (stack, layout, routes, data model, security headers, PWA):** `docs/architecture.md`
  - **Decisions:** `docs/decisions.md`
  - **Glossary (English code/docs ↔ German UI terms):** `docs/glossary.md`
  - **Private and security-relevant information:** Must only be added to `/docs/private`. This folder and everything inside must never be committed or published anywhere. For example, access when deployed to the webserver must explicitly be forbidden.
- **Database Schema & Constraints (SSOT):** Read `db/schema.sql`
- **Configuration Array Layout (SSOT):** Read `config.example.php`
  - **Productive configuration**: This info is in `config.php`. This file must be protected in the same way as `/docs/private` above.
- **Setup & Deployment (config.php values, database, .htaccess):** See `README.md`

## 2. Quick Start & Execution Commands
If `php` is not on the `PATH` (e.g., in agent shells), call it by full path: `/opt/homebrew/bin/php` (Homebrew on macOS).

- **Local Dev Server:** `php -S localhost:8000 -t public router.php`
- **Initialize / Reset SQLite Database:** delete `data/db.sqlite`; it is recreated from `db/schema.sql` on the next request
- **Load Test Data:** set `'load_test_data' => true` in `config.php` and reset the database (see `README.md`)
- **Automated Tests:** `php tests/smoke.php`
- **PHP Syntax Check:** `find . -name "*.php" -exec php -l {} \;`

## 3. Mandatory Policies for you as AI agent

- Whenever you present me a solution, prove that this solution actually works.
- Never commit or push any changes without me explicitly allowing you to do so.
- Do not make assumptions for important decisions. If you are not sure about such decisions, ask me for my input.
- Your priorities are correctness, security, best practices, runtime efficiency, code simplicity and "add only the bare minimum", in this order.
- General language rules:
  - Bare minimum: keep texts as short and concise as possible, while still adding all the information which is absolutely required.
  - Use meaningful, easy to understand names.
  - Be consistent throughout the project with naming
- When you implement something new, modify an existing implementation, or delete an existing modification, make sure the relevant parts of the documentation and specification files are up to date. Keep them as simple and short as possible, but such that they still contains the necessary information.
- Only add comments to implementation files (e.g. `.php` files) if it really helps understand something better. Default is to not add any comments.
  - If you add a comment to an implementation file, make it as short as possible. Add a reference to a documentation file (e.g. `.md` file) for more information, if this is helpful.
- If during code implementation or updating documentation you encounter something misleading or inconsistent, raise it in your answer to me.
- Once more: never be sure about your answers, implementations or updates, unless you have provided proof for its correctness

## 4. Mandatory Coding & Security Guardrails
When writing or editing code, strictly follow these non-negotiable rules:

1. **Strict Typing:** Every PHP file MUST start with `declare(strict_types=1);`.
2. **Database Queries:** NEVER concatenate variables into SQL strings. ALWAYS use PDO Prepared Statements (`$stmt->prepare()` / `$stmt->execute()`).
3. **XSS Prevention:** ALWAYS escape dynamic HTML outputs using `htmlspecialchars((string)$var, ENT_QUOTES, 'UTF-8')`.
4. **Web Root:** Only `public/` is web-accessible. Never put config, data, docs or PHP logic (other than `public/index.php`) into `public/`.
5. **Zero Build Steps:** Load Pico.css v2 from the CDN, version-pinned with SRI (see CSP in `docs/architecture.md`). No JavaScript framework (see D-3 in `docs/decisions.md`). Do NOT add Node.js, npm, Composer, or bundlers.
6. **No Hardcoded Secrets or Personal Data:** Load configuration dynamically from `config.php`. NEVER commit credentials, personal names, email addresses, domains, or hosting details. Put deployment details into `docs/private/private_deployment.md` (gitignored).
7. **CSRF:** Every POST form MUST include and verify the session CSRF token.
8. **Language:** All UI texts are German (Swiss spelling without "ß", informal "du"). Use the terms from `docs/glossary.md`.
9. **Mobile-First:** Every page MUST work on phones and larger screens.
10. **Service Worker Cache:** When a file listed in `PRECACHE` in `public/sw.js` changes (e.g. `app.css`), bump `CACHE` there in the same commit.