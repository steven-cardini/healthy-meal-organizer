# Glossary

Code and docs use the English term, the UI uses the German term. Phase: when the term becomes relevant (see [requirements.md](requirements.md#roadmap)).

**Caution:** English *meal* means the dish (German *Gericht*). German *Mahlzeit* means the meal time (lunch/dinner), so it maps to `meal_time` in code.

| English (code/docs) | German (UI)        | Code identifier      | Definition | Phase |
|---------------------|--------------------|----------------------|------------|-------|
| Meal plan           | Menüplan           | `meal_plan`          | The 14-day plan of what is cooked, repeated forever. | 1 |
| Cycle               | Zyklus             | `cycle`              | The 14-day period after which the meal plan repeats. | 1 |
| Cycle start date    | Startdatum         | `cycle_start_date`   | Monday on which cycle day 1 falls. Defines the cycle week of every date. | 1 |
| Cycle week          | Woche 1 / Woche 2  | `cycle_week`         | First or second week of the cycle (1 or 2). | 1 |
| Cycle day           | –                  | `cycle_day`          | Day within the cycle, 1–14 (1 = Monday of cycle week 1). | 1 |
| Calendar week       | Kalenderwoche (KW) | `calendar_week`      | Real week Monday–Sunday (ISO number). Not the same as the cycle week. | 1 |
| Meal time           | Mahlzeit           | `meal_time`          | Lunch or dinner. | 1 |
| Lunch               | Mittagessen        | `lunch`              | Midday meal time. | 1 |
| Dinner              | Abendessen         | `dinner`             | Evening meal time. | 1 |
| Slot                | –                  | `plan_slot`          | One meal time on one cycle day (28 in total). Holds a meal, a note, or nothing. | 1 |
| Meal                | Gericht            | `meal`               | Reusable dish in the meal list, e.g. "Älplermagronen". | 1 |
| Meal list           | Gerichte           | `meals`              | All meals of the household. | 1 |
| Note                | Notiz              | `note`               | Short text in a slot instead of a meal, e.g. "Eltern kochen". | 1 |
| Not planned         | Nicht geplant      | –                    | Display text for an empty slot. | 1 |
| Day view            | Heute / Tag        | `day`                | Page with the meals of one day (default: today). | 1 |
| Week view           | Woche              | `week`               | Page with the meals of one calendar week (default: current week). | 1 |
| Plan editor         | Plan bearbeiten    | `plan`               | Page to fill the 28 slots. | 1 |
| Household           | Haushalt           | –                    | The group of users sharing one plan and one password. | 1 |
| Shared password     | Passwort           | `password_hash`      | The single household password. | 1 |
| Log in / Log out    | Anmelden / Abmelden | `login` / `logout`  | Start / end a session on a device. | 1 |
| Swap                | Tauschen           | `swap`               | One-time replacement of the planned meal on a specific date. | 2 |
| Prep time           | Zubereitungszeit   | `prep_minutes`       | Minutes needed to prepare a meal. Used to find quicker meals. | 2 |
| Recipe              | Rezept             | `recipe`             | Ingredients and preparation steps of a meal. | 2 |
| Ingredient          | Zutat              | `ingredient`         | Food item with quantity and unit in a recipe. | 2 |
| Servings            | Portionen          | `servings`           | Number of portions a recipe yields. | 2 |
| Shopping list       | Einkaufsliste      | `shopping_list`      | Ingredients needed for a period of the plan, combined. | 2 |
| Health balance      | Ausgewogenheit     | `health_balance`     | How well meals match dietary recommendations. | 3 |
| Food pyramid        | Lebensmittelpyramide | `food_pyramid`     | Swiss dietary recommendation (SGE); in Germany often called "Ernährungspyramide". | 3 |
| Food group          | Lebensmittelgruppe | `food_group`         | Category of the food pyramid, e.g. vegetables, protein. | 3 |
