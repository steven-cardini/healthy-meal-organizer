-- Database schema (SSOT). Applied once to an empty database, see docs/architecture.md#data-model.

PRAGMA user_version = 1;

-- Tag values: see MEAL_TAGS in src/app.php and docs/requirements.md#meal-tags.
CREATE TABLE meal (
    id              INTEGER PRIMARY KEY,
    name            TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    name_key        TEXT NOT NULL UNIQUE,
    protein_source  TEXT NOT NULL CHECK (protein_source IN
                        ('meat', 'poultry', 'fish', 'legumes', 'egg', 'dairy_cheese', 'vegan_tofu', 'none')),
    heaviness       TEXT NOT NULL CHECK (heaviness IN ('light', 'medium', 'heavy')),
    prep_time       TEXT NOT NULL CHECK (prep_time IN ('quick', 'medium', 'slow')),
    child_factor    TEXT NOT NULL CHECK (child_factor IN ('high_flyer', 'deconstructed', 'acquired_taste')),
    shelf_life      TEXT NOT NULL CHECK (shelf_life IN ('short', 'medium', 'long')),
    leftovers       TEXT NOT NULL CHECK (leftovers IN ('reheats_well', 'eat_fresh')),
    vegetable_level TEXT NOT NULL CHECK (vegetable_level IN ('main_component', 'side_dish', 'raw_veggies_needed'))
);

CREATE TABLE plan_slot (
    cycle_day INTEGER NOT NULL CHECK (cycle_day BETWEEN 1 AND 14),
    meal_time TEXT    NOT NULL CHECK (meal_time IN ('lunch', 'dinner')),
    meal_id   INTEGER REFERENCES meal (id) ON DELETE RESTRICT,
    note      TEXT CHECK (length(note) BETWEEN 1 AND 100),
    PRIMARY KEY (cycle_day, meal_time),
    CHECK ((meal_id IS NULL) <> (note IS NULL))
);

CREATE INDEX plan_slot_meal_id ON plan_slot (meal_id);

-- One-time change of a date's slot, see docs/architecture.md#swap. Neither meal_id nor note: skipped.
-- partner_*: the other slot of an exchange or a move; without meal_id: the meal was moved away.
-- moved: the meal was moved here.
CREATE TABLE swap (
    date              TEXT NOT NULL CHECK (date GLOB '[0-9][0-9][0-9][0-9]-[0-9][0-9]-[0-9][0-9]'),
    meal_time         TEXT NOT NULL CHECK (meal_time IN ('lunch', 'dinner')),
    meal_id           INTEGER REFERENCES meal (id) ON DELETE RESTRICT,
    note              TEXT CHECK (length(note) BETWEEN 1 AND 100),
    partner_date      TEXT,
    partner_meal_time TEXT CHECK (partner_meal_time IN ('lunch', 'dinner')),
    moved             INTEGER NOT NULL DEFAULT 0 CHECK (moved IN (0, 1)),
    PRIMARY KEY (date, meal_time),
    CHECK (meal_id IS NULL OR note IS NULL),
    CHECK ((partner_date IS NULL) = (partner_meal_time IS NULL)),
    CHECK (partner_date IS NULL OR note IS NULL),
    CHECK (moved = 0 OR (meal_id IS NOT NULL AND partner_date IS NOT NULL))
);

CREATE INDEX swap_meal_id ON swap (meal_id);

CREATE TABLE login_failure (
    ip        TEXT    NOT NULL,
    failed_at INTEGER NOT NULL
);

CREATE INDEX login_failure_ip ON login_failure (ip, failed_at);
