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
| 2       | Meal tags (protein source, prep time, leftovers, …) and hints in the day and week view    |
| 3       | Swap on the spot: change or skip a meal on a date of the current planning week            |
| 4       | Week planning: change or skip meals for the coming planning week only                     |
| 5       | Recipes, shopping list                                                                    |
| 6       | Health balance based on scientific recommendations (e.g. Swiss food pyramid), using the meal tags |
| Later   | Nice to have, low priority: (a) while swiping, the previous / next day or week is already shown next to the content (loaded in the background), so the switch needs no wait; (b) flexible cycle length: more cycle weeks (e.g. Woche 3 and 4), minimum and maximum still open; (c) when skipping a meal whose ingredients keep only briefly, suggest moving it to a later slot whose meal keeps longer and skipping that one instead |

Phases 4 to 6 and "Later" are not specified yet. Earlier phases must not block them (see [architecture.md](architecture.md#extension-points)).

## Functional requirements (MVP)

### Meal plan

- **FR-1** The meal plan has 14 days: cycle week 1 and cycle week 2, Monday to Sunday each. Each day has two meal times, lunch and dinner (28 slots).
- **FR-2** A slot contains either a meal from the meal list, a note (e.g. "Eltern kochen"), or nothing.
- **FR-3** The plan repeats every 14 days. The cycle start date (a Monday, first day of cycle week 1) defines which cycle week a calendar week belongs to.

### Views

- **FR-4** Day view (start page): weekday, date, cycle week, lunch and dinner. Today, tomorrow and yesterday are labelled *Heute*, *Morgen*, *Gestern*. Shows today by default, with buttons for previous day, next day and (on other days) today. Swiping left / right shows the next / previous day.
- **FR-5** Week view: Monday to Sunday of a calendar week with lunch and dinner, the calendar week (KW) and the cycle week (Woche 1/2), and today highlighted. Shows the current week by default, with buttons for previous week, next week and (in other weeks) this week. Swiping left / right shows the next / previous week.
- **FR-6** Empty slots are shown as not planned, with a link to the slot in the plan editor.

### Editing

- **FR-7** Plan editor: shows all 28 slots of both cycle weeks. Each slot can be set to a meal, a note, or empty (empty text field).
- **FR-8** When entering a meal, existing meals are suggested. A name not in the meal list opens the new meal form (FR-9) with the name filled in; after saving, the meal is entered in the slot.
- **FR-9** Meal list: shows all meals with their number of uses in the plan, and a button for a new meal. A meal opens a form to change its name and tags (FR-13) or to delete it. A meal used in the plan cannot be deleted. Renaming a meal to the name of another meal shows an error.
- **FR-10** Meal names are unique (case-insensitive).

### Access

- **FR-11** All pages require login with the shared household password. A login stays valid on a device for 180 days after its last use. There is no logout.
- **FR-12** The app can be installed as a PWA on Android and iOS phones and tablets. It opens on the day view.

## Functional requirements (phase 2)

### Meal tags

- **FR-13** Every meal has exactly one value for each tag below; all tags are mandatory. In the meal form, no value is preselected.

| Tag (code) | UI label | Values (code: UI label) | Meaning |
|---|---|---|---|
| `protein_source` | Proteinquelle | `meat`: Fleisch, `poultry`: Geflügel, `fish`: Fisch, `legumes`: Hülsenfrüchte, `egg`: Ei, `dairy_cheese`: Milchprodukte/Käse, `vegan_tofu`: Tofu/Seitan, `none`: Keine (möglichst vermeiden) | Main protein source. `meat` is all meat except poultry (beef, veal, pork, lamb, game, sausages). `none` is discouraged |
| `heaviness` | Bekömmlichkeit | `light`: Leicht, `medium`: Mittel, `heavy`: Schwer | Digestion time: 1.5–2.5 h, 3–4 h, 4–6 h or more |
| `prep_time` | Zubereitungszeit | `quick`: Schnell (≤ 20 Min.), `medium`: Mittel (20–40 Min.), `slow`: Lang (> 40 Min.) | Time and effort to prepare |
| `child_factor` | Kindertauglichkeit | `high_flyer`: Alle mögen es, `deconstructed`: Baukasten (einzeln servierbar), `acquired_taste`: Gewöhnungsbedürftig | How the children accept it. Baukasten: components can be served separately. Gewöhnungsbedürftig: children get a neutral side dish and raw vegetables |
| `shelf_life` | Haltbarkeit | `short`: Kurz (1–2 Tage), `medium`: Mittel (3–5 Tage), `long`: Lang (> 5 Tage) | How long the **uncooked** ingredients keep after delivery; the most perishable main ingredient decides |
| `leftovers` | Reste | `reheats_well`: Gut aufwärmbar, `eat_fresh`: Frisch essen | Whether the **cooked** meal still tastes good when reheated the next day |
| `vegetable_level` | Gemüse | `main_component`: Genug enthalten, `side_dish`: Gemüsebeilage kochen, `raw_veggies_needed`: Rohkost dazu | Whether the meal contains enough vegetables, and if not, what to add |

- **FR-14** The day and week view show hints below a meal, each with an icon and text:
  - *Doppelte Menge für den Abend* / *für morgen Mittag*: the meal is `reheats_well` and the same meal is planned in the next slot (lunch → dinner, dinner → next day's lunch). Slots are compared by date, including swaps (FR-16).
  - *Reste vom Mittagessen* / *vom Vorabend*: the same `reheats_well` meal is planned in the slot before. Then no other hint is shown.
  - *Lange Zubereitung*: `prep_time` is `slow`.
  - *Gemüsebeilage kochen* / *Rohkost dazu*: `vegetable_level` is `side_dish` / `raw_veggies_needed`.
- **FR-15** The day view also shows the protein source (as a large icon and as text), prep time and kid suitability of a meal. The meal list shows the same three tags.

## Functional requirements (phase 3)

### Swap

Use cases: "I would rather eat X today", "ingredient Y was discounted", "ingredient Z for today's meal is used up". The groceries for a **planning week** (Saturday to Friday) arrive on its Saturday (or Friday evening).

- **FR-16** A swap changes the slot of one date only; the plan stays as it is. The day view and the week view show what is cooked on a date: the swap if there is one, otherwise the plan slot. A swapped meal is marked *getauscht*; a skipped slot shows its note, or *Ausgelassen*. Swaps are kept after their date.
- **FR-17** In the day view, slots from today until Friday of the current planning week offer:
  - *Anderes Gericht* (*Gericht wählen* if the slot has no meal):
    - Exchange with a meal planned from today until Friday (*Tauschen bis Freitag*): the two slots exchange their meals. Only slots with a meal are offered, not those with the same meal. Not offered if the slot has no meal.
    - Or a meal from the meal list (*Aus der Liste*), filtered by prep time and protein source, or a new meal (as in FR-8). Only this slot changes.
  - *Auslassen*: only for a slot with a meal (FR-18).
  - *Zurücksetzen*: only for a swapped slot (FR-19).
  - An empty slot also keeps its link to the plan editor (FR-6).
- **FR-18** *Auslassen* empties the slot for this date, with an optional note (e.g. "Spontaner Besuch bei den Grosseltern").
- **FR-19** *Zurücksetzen* restores the plan for this slot and for every slot that still holds the result of an exchange with a restored slot. A new swap on a slot replaces its previous swap.
- **FR-20** Freshness warnings, shown before choosing, never blocking. The uncooked ingredients keep from the Saturday of the planning week until Monday (`short`), Thursday (`medium`) or all week (`long`), i.e. the upper end of the `shelf_life` range.
  - At each exchange option: a meal would move to a later date after its ingredients keep.
  - At *Aus der Liste* and *Auslassen*: the current meal is `short` or `medium`, so its ingredients would be left over.
- **FR-21** A meal cannot be deleted while it is in the plan or in a swap from today on. Deleting a meal also deletes its past swaps. The meal list counts uses in the plan only.

## Non-functional requirements

- **NFR-1 Language:** UI in German (Swiss spelling without "ß", informal "du"). Code and docs in English. Terms are mapped in [glossary.md](glossary.md).
- **NFR-2 Mobile-first:** works on phones, tablets and desktop.
- **NFR-3 Security:** HTTPS only. Only the `public/` folder is reachable via the web. Login is throttled. CSRF, XSS and SQL injection protection (see `AGENTS.md`).
- **NFR-4 Online only:** pages need a connection. When offline, the app shows a short notice.
- **NFR-5 Hosting:** shared Apache/PHP 8.5 hosting, SQLite, no build step.
- **NFR-6 Concurrency:** if two people edit at the same time, the last save wins.
- **NFR-7 Time:** time zone Europe/Zurich, weeks start on Monday.

## Out of scope (phases 1–3)

Week planning, recipes, shopping list, health balance, a "freezable" tag, individual user accounts, multiple households, offline data, email, notifications.
