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
| New meal            | Neues Gericht      | `new_meal`           | Form to add a meal with its name and all tags. | 2 |
| Note                | Notiz              | `note`               | Short text in a slot instead of a meal, e.g. "Eltern kochen". | 1 |
| Not planned         | Nicht geplant      | –                    | Display text for an empty slot. | 1 |
| Day view            | Heute / Tag        | `day`                | Page with the meals of one day (default: today). | 1 |
| Week view           | Woche              | `week`               | Page with the meals of one calendar week (default: current week). | 1 |
| Plan editor         | Plan bearbeiten    | `plan`               | Page to fill the 28 slots. Tab label: *Plan*. | 1 |
| Household           | Haushalt           | –                    | The group of users sharing one plan and one password. | 1 |
| Shared password     | Passwort           | `password_hash`      | The single household password. | 1 |
| Log in              | Anmelden           | `login`              | Start a session on a device. | 1 |
| Tag                 | Eigenschaft        | `MEAL_TAGS`          | Property of a meal with exactly one value. Tags and values: see [requirements.md](requirements.md#meal-tags). | 2 |
| Protein source      | Proteinquelle      | `protein_source`     | Main protein source of a meal. | 2 |
| Heaviness           | Bekömmlichkeit     | `heaviness`          | How long a meal takes to digest. | 2 |
| Prep time           | Zubereitungszeit   | `prep_time`          | Time and effort to prepare a meal (category). | 2 |
| Kid suitability     | Kindertauglichkeit | `child_factor`       | How the children accept a meal. | 2 |
| Shelf life          | Haltbarkeit        | `shelf_life`         | How long the uncooked ingredients keep after delivery. | 2 |
| Leftovers           | Reste              | `leftovers`          | Whether the cooked meal reheats well the next day. | 2 |
| Vegetable level     | Gemüse             | `vegetable_level`    | Whether a meal contains enough vegetables. | 2 |
| Hint                | –                  | `slot_hints`         | Short note below a meal in the day and week view, e.g. "Rohkost dazu". | 2 |
| Summary tags        | –                  | `SUMMARY_TAGS`       | Tags shown in the day view and the meal list: protein source, prep time, kid suitability. | 2 |
| Past                | vorbei             | `past`               | Meal time that is over: lunch from 14:00, dinner from the next day. | 3 |
| Planning week       | –                  | `planning_week`      | Saturday to Friday. The groceries for it arrive on its Saturday (or Friday evening). | 3 |
| Swap                | Anderes Gericht / getauscht | `swap`      | One-time change of a slot on a specific date: another meal, or skipped. A swapped meal is marked *getauscht*. | 3 |
| Exchange            | Tauschen           | `exchange`           | Swap in which two slots of the planning week exchange their meals. | 3 |
| Skip                | Auslassen          | `skip`               | Swap that empties a slot on a date, with an optional note. | 3 |
| Reset               | Zurücksetzen       | `reset`              | Remove a swap, so the plan slot applies again. | 3 |
| Entry               | –                  | `entry`              | What a slot holds on a date: the swap if there is one, otherwise the plan slot. | 3 |
| Week planning       | –                  | –                    | Changing or skipping meals for the coming planning week only. | 4 |
| Recipe              | Rezept             | `recipe`             | Ingredients and preparation steps of a meal. | 5 |
| Ingredient          | Zutat              | `ingredient`         | Food item with quantity and unit in a recipe. | 5 |
| Servings            | Portionen          | `servings`           | Number of portions a recipe yields. | 5 |
| Shopping list       | Einkaufsliste      | `shopping_list`      | Ingredients needed for a period of the plan, combined. | 5 |
| Health balance      | Ausgewogenheit     | `health_balance`     | How well meals match dietary recommendations. | 6 |
| Food pyramid        | Lebensmittelpyramide | `food_pyramid`     | Swiss dietary recommendation (SGE); in Germany often called "Ernährungspyramide". | 6 |
| Food group          | Lebensmittelgruppe | `food_group`         | Category of the food pyramid, e.g. vegetables, protein. | 6 |
