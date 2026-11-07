# Requirements

Terms: see [glossary.md](glossary.md). Decisions: see [decisions.md](decisions.md).

## Vision

A web app (installable as PWA) for one household that shows what is cooked today and this week, based on a meal plan that repeats every 14 days.

## Users

One household. All members share one password and have the same rights (view and edit).

## Roadmap

| Phase   | Scope                                                                                     |
|---------|-------------------------------------------------------------------------------------------|
| 1 (MVP) | Repeating meal plan, day view, week view, plan editor, meal list, login, PWA              |
| 2       | Swap a planned meal on a specific date (e.g. for a quicker one), prep time, recipes, shopping list |
| 3       | Health balance based on scientific recommendations (e.g. Swiss food pyramid)              |

Phases 2 and 3 are not specified yet. The MVP must not block them (see [architecture.md](architecture.md#extension-points)).

## Functional requirements (MVP)

### Meal plan

- **FR-1** The meal plan has 14 days: cycle week 1 and cycle week 2, Monday to Sunday each. Each day has two meal times, lunch and dinner (28 slots).
- **FR-2** A slot contains either a meal from the meal list, a note (e.g. "Eltern kochen"), or nothing.
- **FR-3** The plan repeats every 14 days. The cycle start date (a Monday, first day of cycle week 1) defines which cycle week a calendar week belongs to.

### Views

- **FR-4** Day view (start page): weekday, date, cycle week, lunch and dinner. Shows today by default, with buttons for previous day, next day and today.
- **FR-5** Week view: Monday to Sunday of a calendar week with lunch and dinner, the calendar week (KW) and the cycle week (Woche 1/2), and today highlighted. Shows the current week by default, with buttons for previous week, next week and this week.
- **FR-6** Empty slots are shown as not planned.

### Editing

- **FR-7** Plan editor: shows all 28 slots of both cycle weeks. Each slot can be set to a meal, a note, or empty (empty text field).
- **FR-8** When entering a meal, existing meals are suggested. A new name adds a new meal to the meal list.
- **FR-9** Meal list: shows all meals; meals can be renamed and deleted. A meal used in the plan cannot be deleted. Renaming a meal to the name of another meal shows an error.
- **FR-10** Meal names are unique (case-insensitive).

### Access

- **FR-11** All pages require login with the shared household password. A login stays valid on a device for 180 days after its last use. Logout is possible.
- **FR-12** The app can be installed as a PWA on Android and iOS phones and tablets. It opens on the day view.

## Non-functional requirements

- **NFR-1 Language:** UI in German (Swiss spelling without "ß", informal "du"). Code and docs in English. Terms are mapped in [glossary.md](glossary.md).
- **NFR-2 Mobile-first:** works on phones, tablets and desktop.
- **NFR-3 Security:** HTTPS only. Only the `public/` folder is reachable via the web. Login is throttled. CSRF, XSS and SQL injection protection (see `AGENTS.md`).
- **NFR-4 Online only:** pages need a connection. When offline, the app shows a short notice.
- **NFR-5 Hosting:** shared Apache/PHP 8.5 hosting, SQLite, no build step.
- **NFR-6 Concurrency:** if two people edit at the same time, the last save wins.
- **NFR-7 Time:** time zone Europe/Zurich, weeks start on Monday.

## Out of scope (MVP)

Swapping meals, prep time, recipes, shopping list, health balance, individual user accounts, multiple households, offline data, email, notifications.
