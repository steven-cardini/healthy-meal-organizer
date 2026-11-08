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

CREATE TABLE login_failure (
    ip        TEXT    NOT NULL,
    failed_at INTEGER NOT NULL
);

CREATE INDEX login_failure_ip ON login_failure (ip, failed_at);
