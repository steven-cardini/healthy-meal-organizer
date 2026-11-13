-- Database schema (SSOT). Applied once to an empty database, see docs/architecture.md#data-model.

PRAGMA user_version = 1;

-- Tag values: see MEAL_TAGS in src/app.php and docs/requirements.md#meal-tags.
CREATE TABLE meal (
    id              INTEGER PRIMARY KEY,
    name            TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    name_key        TEXT NOT NULL UNIQUE,
    protein_source  TEXT NOT NULL CHECK (protein_source IN
                        ('meat', 'poultry', 'fish', 'legumes', 'egg', 'dairy_cheese', 'tofu_meat_substitute', 'none')),
    heaviness       TEXT NOT NULL CHECK (heaviness IN ('light', 'medium', 'heavy')),
    prep_time       TEXT NOT NULL CHECK (prep_time IN ('quick', 'medium', 'slow')),
    child_factor    TEXT NOT NULL CHECK (child_factor IN ('high_flyer', 'deconstructed', 'acquired_taste')),
    shelf_life      TEXT NOT NULL CHECK (shelf_life IN ('short', 'medium', 'long')),
    leftovers       TEXT NOT NULL CHECK (leftovers IN ('reheats_well', 'eat_fresh')),
    vegetable_level TEXT NOT NULL CHECK (vegetable_level IN ('main_component', 'side_dish', 'raw_veggies_needed')),
    recipe_url      TEXT CHECK (length(recipe_url) <= 2000 AND (recipe_url LIKE 'http://%' OR recipe_url LIKE 'https://%'))
);

-- One row per ingredient name; its category applies to all meals (docs/decisions.md D-45).
-- Categories: see INGREDIENT_CATEGORIES in src/app.php.
CREATE TABLE ingredient (
    id       INTEGER PRIMARY KEY,
    name     TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    name_key TEXT NOT NULL UNIQUE,
    category TEXT NOT NULL CHECK (category IN ('fruit_vegetables', 'meat_fish', 'dairy_eggs_ready_meals',
                 'bread_pastries', 'pasta_condiments_canned', 'snacks_sweets', 'frozen', 'drinks_coffee_tea',
                 'wine_beer_spirits', 'other'))
);

-- position: order in the meal form.
CREATE TABLE meal_ingredient (
    meal_id       INTEGER NOT NULL REFERENCES meal (id) ON DELETE CASCADE,
    ingredient_id INTEGER NOT NULL REFERENCES ingredient (id) ON DELETE RESTRICT,
    position      INTEGER NOT NULL,
    PRIMARY KEY (meal_id, ingredient_id)
);

CREATE INDEX meal_ingredient_ingredient_id ON meal_ingredient (ingredient_id);

-- Week blueprints (FR-1): any number in the database, 1 and 2 in the code (docs/decisions.md D-53). day: 1 = first day of the week (WEEK_START, Saturday).
CREATE TABLE blueprint_slot (
    blueprint INTEGER NOT NULL CHECK (blueprint >= 1),
    day       INTEGER NOT NULL CHECK (day BETWEEN 1 AND 7),
    meal_time TEXT    NOT NULL CHECK (meal_time IN ('lunch', 'dinner')),
    meal_id   INTEGER REFERENCES meal (id) ON DELETE RESTRICT,
    note      TEXT CHECK (length(note) BETWEEN 1 AND 100),
    PRIMARY KEY (blueprint, day, meal_time),
    CHECK ((meal_id IS NULL) <> (note IS NULL))
);

CREATE INDEX blueprint_slot_meal_id ON blueprint_slot (meal_id);

-- A planned week, or the next week while planning it from the other week blueprint. See docs/architecture.md#planning-and-swap.
CREATE TABLE week (
    start_date TEXT    PRIMARY KEY CHECK (start_date GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'),
    blueprint  INTEGER NOT NULL CHECK (blueprint >= 1),
    planned    INTEGER NOT NULL CHECK (planned IN (0, 1))
);

-- The slot of a date: swap = 0 for a planned week or the planning of the next week, 1 for a swap.
-- Neither meal_id nor note: empty or skipped. partner_*: the other slot of an exchange or a move; without meal_id: the
-- meal was moved away. moved: the meal was moved here.
CREATE TABLE date_slot (
    date              TEXT    NOT NULL CHECK (date GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'),
    meal_time         TEXT    NOT NULL CHECK (meal_time IN ('lunch', 'dinner')),
    swap              INTEGER NOT NULL CHECK (swap IN (0, 1)),
    meal_id           INTEGER REFERENCES meal (id) ON DELETE RESTRICT,
    note              TEXT CHECK (length(note) BETWEEN 1 AND 100),
    partner_date      TEXT,
    partner_meal_time TEXT CHECK (partner_meal_time IN ('lunch', 'dinner')),
    moved             INTEGER NOT NULL DEFAULT 0 CHECK (moved IN (0, 1)),
    PRIMARY KEY (date, meal_time, swap),
    CHECK (meal_id IS NULL OR note IS NULL),
    CHECK ((partner_date IS NULL) = (partner_meal_time IS NULL)),
    CHECK (partner_date IS NULL OR note IS NULL),
    CHECK (moved = 0 OR (meal_id IS NOT NULL AND partner_date IS NOT NULL))
);

CREATE INDEX date_slot_meal_id ON date_slot (meal_id);

CREATE TABLE login_failure (
    ip        TEXT    NOT NULL,
    failed_at INTEGER NOT NULL
);

CREATE INDEX login_failure_ip ON login_failure (ip, failed_at);
