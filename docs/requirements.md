# Requirements

Terms: see [glossary.md](glossary.md). Decisions: see [decisions.md](decisions.md).

## Vision

A web app (installable as PWA) for one household that shows what is cooked today and this week, and what to buy for it. Each week is planned from two alternating week blueprints.

## Users

One household. All members share one password and have the same rights (view and edit).

## Roadmap

| Phase   | Scope                                                                                     |
|---------|-------------------------------------------------------------------------------------------|
| 1 (MVP) | Week blueprints, day view, week view, week blueprint editor, meal list, login, PWA        |
| 2       | Meal tags (protein source, prep time, leftovers, …) and hints in the day and week view    |
| 3       | Swap on the spot: change or skip a meal on a date of the current week                     |
| 4       | Planning: plan the next week from its week blueprint before the groceries are ordered     |
| 5       | Ingredients, recipe link, grocery list                                                    |
| Real data | Before production holds real data: reset production (backups are set up, D-52; see [decisions.md](decisions.md#open-points-before-or-during-implementation)) |
| 6       | Health balance of a week based on scientific recommendations (Swiss food pyramid, WHO), using the meal tags (draft: [Health balance](#health-balance-phase-6-draft)) |
| Later   | Nice to have, low priority: (a) while swiping, the previous / next day or week is already shown next to the content (loaded in the background), so the switch needs no wait; (b) more week blueprints (e.g. Woche 3 and 4), minimum and maximum still open; (c) when skipping a meal whose ingredients keep only briefly, suggest moving it to a later slot whose meal keeps longer and skipping that one instead; (d) an optional note for an ingredient of a meal (e.g. *Haselnüsse* with note *ganz* or *gemahlen*), so the same ingredient can appear more than once for a meal if the notes differ (or one has none); open: how notes are shown in the ingredient suggestions |

Phase 6 is only drafted, "Later" is not specified yet. Earlier phases must not block them (see [architecture.md](architecture.md#extension-points)).

## Functional requirements (MVP)

### Week blueprints

- **FR-1** There are two week blueprints (together: *Wochenvorlagen*), *Woche 1* and *Woche 2*, each Saturday to Friday (NFR-7). Each day has two meal times, lunch and dinner (28 slots).
- **FR-2** A slot contains either a meal from the meal list, a note (e.g. "Eltern kochen"), or nothing.
- **FR-3** Weeks use the week blueprints alternately: the first week the app is used is *Woche 1*, each later week uses the other week blueprint than the week before, unless the other one was chosen when planning it (FR-22). A week blueprint is only the starting point for weeks not planned yet (FR-25).

### Views

- **FR-4** Day view (start page): weekday, date, week blueprint (*Woche 1* / *Woche 2*), lunch and dinner. Today, tomorrow and yesterday are labelled *Heute*, *Morgen*, *Gestern*. Shows today by default, with buttons for previous day, next day and (on other days) today. Swiping left / right shows the next / previous day. Past meal times are shown in grey: lunch from 14:00, dinner from the next day. Days of a coming week not planned yet are marked (FR-26).
- **FR-5** Week view: Saturday to Friday with lunch and dinner, the week blueprint and the dates as title (e.g. *Woche 1 · 14.–20.11.*), and today highlighted. Past meal times in grey (FR-4). A coming week not planned yet is marked (FR-26). Shows the current week by default, with buttons for previous week, next week and (in other weeks) this week. Swiping left / right shows the next / previous week.
- **FR-6** Empty slots are shown as *Nicht geplant*.

### Editing

- **FR-7** Week blueprint editor (*Wochenvorlagen bearbeiten*, linked from the *Planen* page, FR-22): shows all 28 slots of both week blueprints. Each slot can be set to a meal, a note, or empty (empty text field).
- **FR-8** When entering a meal, existing meals are suggested. A name not in the meal list opens the new meal form (FR-9) with the name filled in; after saving, the meal is entered in the slot.
- **FR-9** Meal list: shows all meals with their number of uses in the week blueprints, and a button for a new meal. A meal opens a form to change its name, tags (FR-13), ingredients (FR-27) and recipe link (FR-29), or to delete it. A meal in use cannot be deleted (FR-21). Renaming a meal to the name of another meal shows an error.
- **FR-10** Meal names are unique (case-insensitive).

### Access

- **FR-11** All pages require login with the shared household password. A login stays valid on a device for 180 days after its last use. There is no logout.
- **FR-12** The app can be installed as a PWA on Android and iOS phones and tablets. It opens on the day view.

## Functional requirements (phase 2)

### Meal tags

- **FR-13** Every meal has exactly one value for each tag below; all tags are mandatory. In the meal form, no value is preselected.

| Tag (code) | UI label | Values (code: UI label) | Meaning |
|---|---|---|---|
| `protein_source` | Proteinquelle | `meat`: Fleisch, `poultry`: Geflügel, `fish`: Fisch, `legumes`: Hülsenfrüchte, `egg`: Ei, `dairy_cheese`: Milchprodukte/Käse, `vegan_tofu`: Tofu/Seitan, `none`: Keine Proteinquelle | Main protein source. `meat` is all meat except poultry (beef, veal, pork, lamb, game, sausages). `none` is discouraged (warning icon) |
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
  - *Zutaten evtl. nicht mehr frisch*: the meal is planned after its ingredients keep (FR-20), wherever it comes from (week blueprint, planning, swap).
- **FR-15** The day view also shows the protein source (as a large icon and as text), prep time and kid suitability of a meal. The meal list and the exchange options (FR-17) show the same three tags.

## Functional requirements (phase 3)

### Swap

Use cases: "I would rather eat X today", "ingredient Y was discounted", "ingredient Z for today's meal is used up". The groceries for a week (Saturday to Friday) arrive on its Saturday (or Friday evening).

- **FR-16** A swap changes the slot of one date of a planned week (FR-25) only. The day view and the week view show what is cooked on a date: the swap if there is one, otherwise the planned week (for a week not planned yet: its planning, FR-23, otherwise its week blueprint). A swapped meal is marked *getauscht*, a moved one *verschoben*. A skipped slot shows its note, or *Ausgelassen*. A slot whose meal was moved away shows *Verschoben auf* and the target (e.g. *Dienstag Mittag*), or only *Verschoben* once the target holds something else. Swaps are kept after their date.
- **FR-17** In the day view, slots that are not past (FR-4) of the current week, and of the next week once it is planned (FR-24), offer in a *⋯* menu:
  - *Anderes Gericht* (*Gericht planen* if the slot has no meal, e.g. a note):
    - *Gericht tauschen* (slot with a meal): exchange with a meal of the same week that is not past. The two slots exchange their meals. Only slots with a meal are offered, not those with the same meal.
    - *Gericht verschieben* (slot without a meal): move a meal of the same week that is not past to this slot. Its note is removed, and the other slot is then empty.
    - Or a meal from the meal list (*Aus der Liste*), filtered by prep time and protein source, or a new meal (as in FR-8). Only this slot changes.
  - *Auslassen*: only for a slot with a meal (FR-18).
  - *Zurücksetzen*: only for a swapped slot (FR-19).
- **FR-18** *Auslassen* empties the slot for this date, with an optional note (e.g. "Spontaner Besuch bei den Grosseltern").
- **FR-19** *Zurücksetzen* restores the planned week for this slot and for every slot linked to a restored slot by an exchange or a move, even if that slot was changed again later, except past slots. Below *Zurücksetzen*, a warning names the other slots it restores (e.g. *Setzt auch Donnerstag Abend zurück.*). A new swap on a slot replaces its previous swap.
- **FR-20** Freshness warnings, never blocking. The uncooked ingredients keep from the Saturday of the week until Monday (`short`), Thursday (`medium`) or all week (`long`), i.e. the upper end of the `shelf_life` range.
  - At each exchange and move option (also when planning, FR-23): a meal would move to a later date after its ingredients keep.
  - At *Aus der Liste* and *Auslassen* (not when planning, nothing is bought yet): the current meal is `short` or `medium`, so its ingredients would be left over.
  - The hint *Zutaten evtl. nicht mehr frisch* (FR-14).
- **FR-21** A meal cannot be deleted while it is in a week blueprint, or from today on in a planned week, the planning of the next week or a swap. Deleting a meal empties it on past dates of planned weeks and removes past swaps to it, so the planned meal shows there again (D-51). The meal list counts uses in the week blueprints only.

## Functional requirements (phase 4)

### Planning

The groceries for a week are ordered on Wednesday or Thursday before it. Before that, the week must be planned.

- **FR-22** *Planen* (page title *Nächste Woche planen*): shows the next week, Saturday to Friday, starting from the week blueprint in turn (FR-3). The other week blueprint can be chosen instead; this discards the changes made to the week so far, and a warning says so if there are any. The page links to *Wochenvorlagen bearbeiten* (FR-7). Planning is possible at any time, for the next week only.
- **FR-23** Each slot offers in a *⋯* menu the actions of FR-17 to FR-19, for the slots of the next week: *Gericht tauschen*, *Gericht verschieben*, *Aus der Liste* or a new meal, *Auslassen*, and *Zurücksetzen*, which restores the week blueprint. Until the week is planned, changes are marked as in FR-16 (e.g. *getauscht*, *Ausgelassen*), also in the day and week view. Once it is planned, they are no longer marked (a slot emptied by a move, or skipped without a note, then shows *Nicht geplant*); only swaps are.
- **FR-24** *Planung abschliessen* makes the next week a planned week, after a confirmation that says it cannot be reopened. Afterwards, changes are only possible as swaps in the day view (FR-16 to FR-19), and the *Planen* page says that the next week is planned and the week after can be planned from Saturday on.
- **FR-25** A planned week keeps what was planned: later changes to the week blueprints do not affect it. A week not planned by its Saturday becomes a planned week with its planning so far. Only the first week the app is used keeps following the week blueprints, as they are usually entered during that week.
- **FR-26** The day and week view show *Planung offen* at the top for a coming week not planned yet, for the next week with a link to *Planen*. Its meals are not shown in grey (grey means past, FR-4).

## Functional requirements (phase 5)

### Ingredients and recipe link

- **FR-27** A meal has zero or more ingredients, each only a name (no quantity, e.g. *Hackfleisch*). In the meal form, each ingredient is a row with name and category; *+ Zutat* adds a row, an empty name removes it. Names already used are suggested, except those already entered for the meal. Ingredient names are compared case-insensitively; a name entered twice for a meal counts once.
- **FR-28** Each ingredient name has one category, which applies to all meals using it. It is chosen when the name is used for the first time; for a known name it is selected automatically, or can stay empty. Changing it changes it for all meals. Categories, in this order: *Früchte & Gemüse*, *Fleisch & Fisch*, *Milchprodukte, Eier & frische Fertiggerichte*, *Brot & Backwaren*, *Pasta, Würzmittel & Konserven*, *Snacks & Süssigkeiten*, *Tiefkühlprodukte*, *Getränke, Kaffee & Tee*, *Wein, Bier & Spirituosen*, *Sonstige*.
- **FR-29** A meal has an optional recipe link (an `http` or `https` URL). The day view shows it as *Rezept*, opening in a new tab.
- **FR-30** The meal list marks meals without ingredients (*Keine Zutaten*).

### Grocery list

The groceries for a week are ordered after planning it (FR-24), from the grocery list.

- **FR-31** *Einkaufsliste* of a planned week (FR-24, FR-25), linked from the week view of a planned week and from the *Planen* page once the next week is planned. It combines the ingredients of all meals cooked in that week (FR-16: including swaps, without skipped slots and notes), each ingredient once, grouped by category in the order of FR-28 and sorted alphabetically. Below each ingredient, the meals using it, with *2×* if a meal is cooked in two slots (*3×*, …).
- **FR-32** Ingredients can be ticked off. Ticks belong to their week and are kept while the app is open, also on reload; closing the app clears them.
- **FR-33** Below the list, *Ohne Zutaten:* names the meals of the week without ingredients.

## Health balance (phase 6, draft)

Not specified yet. Recorded so that the data it needs is in place before real data (D-51).

- Rates a week: in the week blueprint editor, when planning the next week, and for swaps in the current week; for past weeks too if this is little effort.
- Only lunch and dinner are known, so it rates the main meals with the existing tags.
- Household rules per week, based on the recommendations below:
  - `meat` (all meat except poultry, including processed meat): at most 1.
  - `poultry`: 1–2.
  - `fish`: 1–2.
  - `legumes`: at least 2.
- A `reheats_well` meal in consecutive slots (FR-14) counts once.
- Open: how the rating is shown, whether `vegetable_level` is rated, and how slots without a meal (notes, skipped) count.

Recommendations (per person):

- Swiss (SGE/BLV 2024): meat at most 2–3 times a week, including poultry and processed meat, processed meat sparingly; legumes at least once a week; 3 portions of grain products a day, at least half wholegrain; 2–3 portions of dairy a day. No figure for fish found, so 1–2 is a household rule. The household rules are stricter: meat and poultry together at most 3, legumes at least 2.
- WHO: at least 400 g of fruit and vegetables a day; whole grains, legumes and nuts; poultry and fish rather than red meat, processed meat only in limited amounts; salt below 5 g a day; free sugars below 10 % of energy; fat below 30 % of energy (saturated below 10 %, trans below 1 %).
- Not measurable without breakfast, snacks and quantities: fruit, dairy, nuts, the wholegrain share, salt, sugar and fat.

Sources: [Schweizer Ernährungsempfehlungen, Kurzversion (BLV)](https://www.blv.admin.ch/dam/blv/de/dokumente/lebensmittel-und-ernaehrung/ernaehrung/merkblatt-schweizer-ernaehrungsempfehlung-kurz.pdf.download.pdf/Schweizer%20Ern%C3%A4hrungsempfehlungen_Kurzversion_DE.pdf), [Südostschweiz: SGE on the 2024 recommendations](https://www.suedostschweiz.ch/leben-freizeit/lebensmittel-pyramide-abwechslungsreich-und-nachhaltig-neue-ernaehrungsempfehlungen-24595) (processed meat sparingly), [WHO: Healthy diet](https://www.who.int/news-room/fact-sheets/detail/healthy-diet), [WHO Europe: Nutrition for a healthy life](https://who.int/europe/news-room/fact-sheets/item/nutrition---maintaining-a-healthy-lifestyle).

## Non-functional requirements

- **NFR-1 Language:** UI in German (Swiss spelling without "ß", informal "du"). Code and docs in English. Terms are mapped in [glossary.md](glossary.md).
- **NFR-2 Mobile-first:** works on phones, tablets and desktop.
- **NFR-3 Security:** HTTPS only. Only the `public/` folder is reachable via the web. Login is throttled. CSRF, XSS and SQL injection protection (see `AGENTS.md`).
- **NFR-4 Online only:** pages need a connection. When offline, the app shows a short notice.
- **NFR-5 Hosting:** shared Apache/PHP 8.5 hosting, SQLite, no build step.
- **NFR-6 Concurrency:** if two people edit at the same time, the last save wins.
- **NFR-7 Time:** time zone Europe/Zurich. Weeks run Saturday to Friday, as the groceries arrive on Saturday.

## Out of scope (phases 1–5)

Recipes stored in the app (other than a link), ingredient quantities, health balance, a "freezable" tag, individual user accounts, multiple households, offline data, email, notifications.
