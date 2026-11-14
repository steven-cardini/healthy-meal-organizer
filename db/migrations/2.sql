-- Schema version 1 → 2: advance_prep (FR-34).

ALTER TABLE meal ADD COLUMN advance_prep INTEGER NOT NULL DEFAULT 0 CHECK (advance_prep IN (0, 1));

PRAGMA user_version = 2;
