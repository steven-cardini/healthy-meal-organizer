-- Database schema (SSOT). Applied once to an empty database, see docs/architecture.md#data-model.

PRAGMA user_version = 1;

CREATE TABLE meal (
    id       INTEGER PRIMARY KEY,
    name     TEXT NOT NULL CHECK (length(name) BETWEEN 1 AND 100),
    name_key TEXT NOT NULL UNIQUE
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
